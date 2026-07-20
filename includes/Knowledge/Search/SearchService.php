<?php
/**
 * Deterministic knowledge-provider search orchestration.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Search;

use AdamBot\Helpers\Logger;
use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\DTO\SearchResultSet;
use AdamBot\Knowledge\KnowledgeProviderInterface;
use AdamBot\Knowledge\KnowledgeSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Queries providers, centralizes ranking, caches results, and records diagnostics.
 */
final class SearchService {
	/** Maximum answer candidates. */
	private const RESULT_LIMIT = 5;

	/** Maximum fallback page suggestions. */
	private const FALLBACK_LIMIT = 3;

	/** Cached deterministic search lifetime. */
	private const CACHE_TTL = 600;

	/** @var KnowledgeSettings */
	private $settings;

	/** @var ResultRanker */
	private $ranker;

	/** @var KeywordMatcher */
	private $matcher;

	/** @var Logger */
	private $logger;

	/** @var array<int, KnowledgeProviderInterface> */
	private $providers;

	/**
	 * @param KnowledgeSettings                      $settings Knowledge settings.
	 * @param ResultRanker                           $ranker Central ranker.
	 * @param KeywordMatcher                         $matcher Text normalizer.
	 * @param Logger                                 $logger Structured logger.
	 * @param array<int, KnowledgeProviderInterface> $providers Registered providers.
	 */
	public function __construct(
		KnowledgeSettings $settings,
		ResultRanker $ranker,
		KeywordMatcher $matcher,
		Logger $logger,
		array $providers
	) {
		$this->settings  = $settings;
		$this->ranker    = $ranker;
		$this->matcher   = $matcher;
		$this->logger    = $logger;
		$this->providers = $this->filterProviders( $providers );
	}

	/**
	 * Runs the User → Search → Rank pipeline.
	 *
	 * @param string               $query Sanitized user question.
	 * @param array<string, mixed> $session_context Current topic and recently shown IDs.
	 * @return SearchResultSet
	 */
	public function search( string $query, array $session_context = array() ): SearchResultSet {
		$started_at        = microtime( true );
		$normalized_query  = $this->matcher->normalize( $query );
		$topic             = sanitize_key( (string) ( $session_context['topic'] ?? '' ) );
		$recent_result_ids = $this->sanitizeResultIds( $session_context['recent_result_ids'] ?? array() );
		$resolved_query    = $this->resolveContext( $query, $topic );

		if ( '' === $normalized_query ) {
			return new SearchResultSet( array(), array(), 0, 'none', $topic );
		}

		$cache_key = 'adam_bot_response_' . $this->settings->getCacheVersion() . '_' . md5(
			$this->matcher->normalize( $resolved_query ) . '|' . $topic . '|' . implode( ',', $recent_result_ids )
		);
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			$result = SearchResultSet::fromArray( $cached, $this->elapsedMilliseconds( $started_at ) );
			$this->logSearch( $query, $resolved_query, $result, true );
			return $result;
		}

		$candidates        = array();
		$enabled_providers = 0;
		$providers         = apply_filters( 'adam_bot_knowledge_providers', $this->providers, $query );
		$providers         = $this->filterProviders( is_array( $providers ) ? $providers : $this->providers );

		foreach ( $providers as $provider ) {
			if ( ! $this->settings->isSourceEnabled( $provider->getKey() ) ) {
				continue;
			}

			$enabled_providers++;

			try {
				foreach ( $provider->search( $resolved_query ) as $candidate ) {
					if ( $candidate instanceof KnowledgeResult ) {
						$candidates[] = $candidate;
					}
				}
			} catch ( \Throwable $exception ) {
				$this->logger->error(
					'Knowledge provider search failed.',
					array(
						'provider'   => $provider->getKey(),
						'error_type' => get_class( $exception ),
						'error'      => $exception->getMessage(),
					)
				);
			}
		}

		$candidates = $this->deduplicate( $candidates );
		$ranked     = $this->ranker->rank( $resolved_query, $candidates, $topic, $recent_result_ids );
		$relevant   = array_values(
			array_filter(
				$ranked,
				static function ( KnowledgeResult $result ): bool {
					return $result->getScore() >= 15;
				}
			)
		);
		$relevant   = array_slice( $relevant, 0, self::RESULT_LIMIT );
		$top        = $relevant[0] ?? null;
		$confidence = $top instanceof KnowledgeResult ? $top->getScore() : 0;
		$topic      = $this->inferTopic( $query, $top, $topic );
		$fallbacks  = $this->fallbackResults( $ranked );
		$result     = new SearchResultSet(
			$relevant,
			$fallbacks,
			$confidence,
			$this->confidenceLevel( $confidence ),
			$topic,
			$top instanceof KnowledgeResult ? $top->getSource() : '',
			$top instanceof KnowledgeResult ? $top->getMatchedKeywords() : array(),
			$this->elapsedMilliseconds( $started_at )
		);

		set_transient( $cache_key, $result->toArray(), self::CACHE_TTL );

		$this->logger->info(
			'Knowledge response search completed.',
			array(
				'search_query'      => $query,
				'resolved_query'    => $resolved_query,
				'provider_count'    => $enabled_providers,
				'matched_provider'  => $result->getMatchedProvider(),
				'matched_keywords'  => $result->getMatchedKeywords(),
				'confidence'        => $result->getConfidence(),
				'confidence_level'  => $result->getConfidenceLevel(),
				'response_time_ms'  => $result->getResponseTimeMs(),
				'cache_hit'         => false,
			)
		);

		return $result;
	}

	/** @return string */
	private function resolveContext( string $query, string $topic ): string {
		if ( '' === $topic || $this->ranker->countMeaningfulTerms( $query ) > 2 ) {
			return $query;
		}

		$normalized = $this->matcher->normalize( $query );
		foreach ( $this->ranker->getTopicTerms( $topic ) as $term ) {
			if ( false !== strpos( $normalized, $term ) ) {
				return $query;
			}
		}

		return trim( $query . ' ' . $topic );
	}

	/** @return string */
	private function confidenceLevel( int $confidence ): string {
		if ( $confidence >= 65 ) {
			return 'high';
		}

		if ( $confidence >= 35 ) {
			return 'medium';
		}

		return $confidence >= 15 ? 'low' : 'none';
	}

	/** @return string */
	private function inferTopic( string $query, ?KnowledgeResult $top, string $current_topic ): string {
		if ( $top instanceof KnowledgeResult ) {
			if ( 'membership' === $top->getSource() ) {
				return 'membership';
			}

			if ( 'event' === $top->getSource() ) {
				return 'events';
			}
		}

		$value = $this->matcher->normalize(
			$query . ' ' . ( $top instanceof KnowledgeResult ? $top->getTitle() . ' ' . $top->getCategory() : '' )
		);
		$patterns = array(
			'membership' => '/\b(membership|member|membro|socio|quota|renov|beneficio|aderente|efetivo|inscri)/',
			'events'     => '/\b(event|evento|jogo|agenda|partida)/',
			'rules'      => '/\b(rule|regra|safety|seguranca|joule|potencia|limite)/',
			'contact'    => '/\b(contact|contacto|telefone|email|morada)/',
			'airsoft'    => '/\b(airsoft|replica|equipamento|protecao)/',
			'about'      => '/\b(about|adam|associacao|organizacao)/',
		);

		foreach ( $patterns as $topic => $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return $topic;
			}
		}

		return $current_topic;
	}

	/** @return array<int, KnowledgeResult> */
	private function fallbackResults( array $ranked ): array {
		$fallbacks = array();

		foreach ( $ranked as $result ) {
			if ( $result instanceof KnowledgeResult && '' !== $result->getUrl() ) {
				$fallbacks[] = $result;
			}

			if ( self::FALLBACK_LIMIT === count( $fallbacks ) ) {
				break;
			}
		}

		return $fallbacks;
	}

	/** @return array<int, KnowledgeResult> */
	private function deduplicate( array $results ): array {
		$seen   = array();
		$unique = array();

		foreach ( $results as $result ) {
			$key = $result->getId();
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $result;
		}

		return $unique;
	}

	/** @return array<int, KnowledgeProviderInterface> */
	private function filterProviders( array $providers ): array {
		return array_values(
			array_filter(
				$providers,
				static function ( $provider ): bool {
					return $provider instanceof KnowledgeProviderInterface;
				}
			)
		);
	}

	/** @return array<int, string> */
	private function sanitizeResultIds( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_slice(
			array_values(
				array_filter(
					array_map(
						static function ( $id ): string {
							$id = strtolower( (string) $id );
							return 1 === preg_match( '/^[a-f0-9]{32}$/', $id ) ? $id : '';
						},
						$ids
					)
				)
			),
			-5
		);
	}

	/** @return void */
	private function logSearch( string $query, string $resolved_query, SearchResultSet $result, bool $cache_hit ): void {
		$this->logger->info(
			'Knowledge response search completed.',
			array(
				'search_query'     => $query,
				'resolved_query'   => $resolved_query,
				'matched_provider' => $result->getMatchedProvider(),
				'matched_keywords' => $result->getMatchedKeywords(),
				'confidence'       => $result->getConfidence(),
				'confidence_level' => $result->getConfidenceLevel(),
				'response_time_ms' => $result->getResponseTimeMs(),
				'cache_hit'        => $cache_hit,
			)
		);
	}

	/** @return int */
	private function elapsedMilliseconds( float $started_at ): int {
		return (int) round( ( microtime( true ) - $started_at ) * 1000 );
	}
}
