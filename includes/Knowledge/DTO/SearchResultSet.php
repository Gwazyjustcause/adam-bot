<?php
/**
 * Deterministic knowledge search result set.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\DTO;

defined( 'ABSPATH' ) || exit;

/**
 * Contains ranked answers, page fallbacks, confidence, and lightweight context.
 */
final class SearchResultSet {
	/** @var array<int, KnowledgeResult> */
	private $results;

	/** @var array<int, KnowledgeResult> */
	private $fallback_results;

	/** @var int */
	private $confidence;

	/** @var string */
	private $confidence_level;

	/** @var string */
	private $topic;

	/** @var string */
	private $matched_provider;

	/** @var array<int, string> */
	private $matched_keywords;

	/** @var int */
	private $response_time_ms;

	/** @var string */
	private $intent;

	/** @var int */
	private $intent_confidence;

	/** @var string */
	private $fallback_provider;

	/** @var int */
	private $provider_duration_ms;

	/** @var int */
	private $provider_result_count;

	/** @var bool */
	private $dynamic;

	/**
	 * @param array<int, KnowledgeResult> $results Ranked answer candidates.
	 * @param array<int, KnowledgeResult> $fallback_results Ranked navigable fallbacks.
	 * @param int                         $confidence Score from 0 to 100.
	 * @param string                      $confidence_level high, medium, low, or none.
	 * @param string                      $topic Lightweight conversation topic.
	 * @param string                      $matched_provider Winning provider key.
	 * @param array<int, string>          $matched_keywords Matched query terms.
	 * @param int                         $response_time_ms Search time.
	 * @param array<string,mixed>         $diagnostics Intent and provider diagnostics.
	 */
	public function __construct(
		array $results = array(),
		array $fallback_results = array(),
		int $confidence = 0,
		string $confidence_level = 'none',
		string $topic = '',
		string $matched_provider = '',
		array $matched_keywords = array(),
		int $response_time_ms = 0,
		array $diagnostics = array()
	) {
		$this->results            = $this->filterResults( $results );
		$this->fallback_results   = $this->filterResults( $fallback_results );
		$this->confidence         = max( 0, min( 100, $confidence ) );
		$this->confidence_level   = in_array( $confidence_level, array( 'high', 'medium', 'low', 'none' ), true ) ? $confidence_level : 'none';
		$this->topic              = sanitize_key( $topic );
		$this->matched_provider   = sanitize_key( $matched_provider );
		$this->matched_keywords   = array_values( array_unique( array_map( 'sanitize_key', $matched_keywords ) ) );
		$this->response_time_ms   = max( 0, $response_time_ms );
		$this->intent             = sanitize_key( (string) ( $diagnostics['intent'] ?? 'knowledge_question' ) );
		$this->intent_confidence  = max( 0, min( 100, (int) ( $diagnostics['intent_confidence'] ?? 0 ) ) );
		$this->fallback_provider  = sanitize_key( (string) ( $diagnostics['fallback_provider'] ?? '' ) );
		$this->provider_duration_ms = max( 0, (int) ( $diagnostics['provider_duration_ms'] ?? 0 ) );
		$this->provider_result_count = max( 0, (int) ( $diagnostics['provider_result_count'] ?? count( $this->results ) ) );
		$this->dynamic            = ! empty( $diagnostics['dynamic'] );
	}

	/** @return array<int, KnowledgeResult> */
	public function getResults(): array {
		return $this->results;
	}

	/** @return array<int, KnowledgeResult> */
	public function getFallbackResults(): array {
		return $this->fallback_results;
	}

	/** @return KnowledgeResult|null */
	public function getTopResult(): ?KnowledgeResult {
		return $this->results[0] ?? null;
	}

	/** @return bool */
	public function hasResults(): bool {
		return ! empty( $this->results );
	}

	/** @return int */
	public function getConfidence(): int {
		return $this->confidence;
	}

	/** @return string */
	public function getConfidenceLevel(): string {
		return $this->confidence_level;
	}

	/** @return string */
	public function getTopic(): string {
		return $this->topic;
	}

	/** @return string */
	public function getMatchedProvider(): string {
		return $this->matched_provider;
	}

	/** @return array<int, string> */
	public function getMatchedKeywords(): array {
		return $this->matched_keywords;
	}

	/** @return int */
	public function getResponseTimeMs(): int {
		return $this->response_time_ms;
	}

	public function getIntent(): string { return $this->intent; }
	public function getIntentConfidence(): int { return $this->intent_confidence; }
	public function getFallbackProvider(): string { return $this->fallback_provider; }
	public function getProviderDurationMs(): int { return $this->provider_duration_ms; }
	public function getProviderResultCount(): int { return $this->provider_result_count; }
	public function isDynamic(): bool { return $this->dynamic; }

	/** @return array<string, mixed> */
	public function toArray(): array {
		return array(
			'results'            => array_map( static function ( KnowledgeResult $result ): array { return $result->toArray(); }, $this->results ),
			'fallback_results'   => array_map( static function ( KnowledgeResult $result ): array { return $result->toArray(); }, $this->fallback_results ),
			'confidence'         => $this->confidence,
			'confidence_level'   => $this->confidence_level,
			'topic'              => $this->topic,
			'matched_provider'   => $this->matched_provider,
			'matched_keywords'   => $this->matched_keywords,
			'response_time_ms'   => $this->response_time_ms,
			'diagnostics'        => array(
				'intent'                => $this->intent,
				'intent_confidence'     => $this->intent_confidence,
				'fallback_provider'     => $this->fallback_provider,
				'provider_duration_ms'  => $this->provider_duration_ms,
				'provider_result_count' => $this->provider_result_count,
				'dynamic'               => $this->dynamic,
			),
		);
	}

	/** @param array<string, mixed> $data Cached data. */
	public static function fromArray( array $data, int $response_time_ms = 0 ): self {
		return new self(
			self::restoreResults( $data['results'] ?? array() ),
			self::restoreResults( $data['fallback_results'] ?? array() ),
			(int) ( $data['confidence'] ?? 0 ),
			(string) ( $data['confidence_level'] ?? 'none' ),
			(string) ( $data['topic'] ?? '' ),
			(string) ( $data['matched_provider'] ?? '' ),
			isset( $data['matched_keywords'] ) && is_array( $data['matched_keywords'] ) ? $data['matched_keywords'] : array(),
			$response_time_ms,
			isset( $data['diagnostics'] ) && is_array( $data['diagnostics'] ) ? $data['diagnostics'] : array()
		);
	}

	/** @return array<int, KnowledgeResult> */
	private function filterResults( array $results ): array {
		return array_values( array_filter( $results, static function ( $result ): bool { return $result instanceof KnowledgeResult; } ) );
	}

	/** @return array<int, KnowledgeResult> */
	private static function restoreResults( $rows ): array {
		$results = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( is_array( $row ) ) {
					$results[] = KnowledgeResult::fromArray( $row );
				}
			}
		}

		return $results;
	}
}
