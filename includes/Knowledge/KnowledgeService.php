<?php
/**
 * Knowledge search orchestration.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

use AdamBot\Helpers\Logger;
use AdamBot\Knowledge\DTO\KnowledgeContext;
use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\Search\KeywordMatcher;

defined( 'ABSPATH' ) || exit;

/**
 * Searches enabled sources, ranks results, bounds context, and caches queries.
 */
final class KnowledgeService {
	/** Maximum results sent to the prompt builder. */
	private const RESULT_LIMIT = 5;

	/** Minimum accepted relevance score. */
	private const MINIMUM_SCORE = 20;

	/** Search-result cache lifetime in seconds. */
	private const CACHE_TTL = 600;

	/** @var KnowledgeSettings */
	private $settings;

	/** @var KeywordMatcher */
	private $matcher;

	/** @var Logger */
	private $logger;

	/** @var array<int, KnowledgeSourceInterface> */
	private $sources;

	/**
	 * Creates the service.
	 *
	 * @param KnowledgeSettings                    $settings Knowledge settings.
	 * @param KeywordMatcher                       $matcher Query normalizer.
	 * @param Logger                               $logger Internal logger.
	 * @param array<int, KnowledgeSourceInterface> $sources Registered sources.
	 */
	public function __construct(
		KnowledgeSettings $settings,
		KeywordMatcher $matcher,
		Logger $logger,
		array $sources
	) {
		$this->settings = $settings;
		$this->matcher  = $matcher;
		$this->logger   = $logger;
		$this->sources  = array_values(
			array_filter(
				$sources,
				static function ( $source ): bool {
					return $source instanceof KnowledgeSourceInterface;
				}
			)
		);
	}

	/**
	 * Searches all enabled sources and returns at most five confident results.
	 *
	 * @param string $query Sanitized user question.
	 * @return KnowledgeContext
	 */
	public function search( string $query ): KnowledgeContext {
		$normalized = $this->matcher->normalize( $query );

		if ( '' === $normalized ) {
			return new KnowledgeContext();
		}

		$cache_key = 'adam_bot_knowledge_' . $this->settings->getCacheVersion() . '_' . md5( $normalized );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return KnowledgeContext::fromArray( $cached );
		}

		$started_at      = microtime( true );
		$enabled_sources = 0;
		$results         = array();

		foreach ( $this->sources as $source ) {
			if ( ! $this->settings->isSourceEnabled( $source->getKey() ) ) {
				continue;
			}

			$enabled_sources++;

			try {
				foreach ( $source->search( $query ) as $result ) {
					if ( $result instanceof KnowledgeResult && $result->getScore() >= self::MINIMUM_SCORE ) {
						$results[] = $result;
					}
				}
			} catch ( \Throwable $exception ) {
				$this->logger->error(
					'Knowledge source search failed.',
					array(
						'source'     => $source->getKey(),
						'error_type' => get_class( $exception ),
						'error'      => $exception->getMessage(),
					)
				);
			}
		}

		usort(
			$results,
			static function ( KnowledgeResult $left, KnowledgeResult $right ): int {
				return $right->getScore() <=> $left->getScore();
			}
		);

		$results = $this->deduplicate( $results );
		$results = array_slice( $results, 0, self::RESULT_LIMIT );
		$context = new KnowledgeContext(
			$results,
			empty( $results ) ? 0 : $results[0]->getScore()
		);

		set_transient( $cache_key, $context->toArray(), self::CACHE_TTL );

		$this->logger->info(
			'Knowledge search completed.',
			array(
				'enabled_sources'  => $enabled_sources,
				'result_count'     => count( $results ),
				'confidence'       => $context->getConfidence(),
				'response_time_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
			)
		);

		return $context;
	}

	/**
	 * Removes duplicate content contributed by overlapping sources.
	 *
	 * @param array<int, KnowledgeResult> $results Ranked results.
	 * @return array<int, KnowledgeResult>
	 */
	private function deduplicate( array $results ): array {
		$seen   = array();
		$unique = array();

		foreach ( $results as $result ) {
			$key = md5( $this->matcher->normalize( $result->getTitle() . ' ' . $result->getContent() ) );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $result;
		}

		return $unique;
	}
}
