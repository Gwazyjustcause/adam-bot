<?php
/**
 * Dynamic provider resolution result.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Dynamic;

use AdamBot\Knowledge\DTO\KnowledgeResult;

defined( 'ABSPATH' ) || exit;

/** Immutable diagnostics and results returned by ProviderResolver. */
final class DynamicSearchResult {
	/** @var array<int,KnowledgeResult> */
	private $results;
	/** @var string */
	private $intent;
	/** @var int */
	private $intent_confidence;
	/** @var string */
	private $selected_provider;
	/** @var string */
	private $fallback_provider;
	/** @var int */
	private $duration_ms;
	/** @var int */
	private $cache_ttl;

	/** @param array<int,KnowledgeResult> $results Results. */
	public function __construct( array $results, string $intent, int $intent_confidence, string $selected_provider = '', string $fallback_provider = '', int $duration_ms = 0, int $cache_ttl = 120 ) {
		$this->results           = array_values( array_filter( $results, static function ( $result ): bool { return $result instanceof KnowledgeResult; } ) );
		$this->intent            = sanitize_key( $intent );
		$this->intent_confidence = max( 0, min( 100, $intent_confidence ) );
		$this->selected_provider = sanitize_key( $selected_provider );
		$this->fallback_provider = sanitize_key( $fallback_provider );
		$this->duration_ms       = max( 0, $duration_ms );
		$this->cache_ttl         = max( 10, min( 900, $cache_ttl ) );
	}

	/** @return array<int,KnowledgeResult> */
	public function getResults(): array { return $this->results; }
	public function getIntent(): string { return $this->intent; }
	public function getIntentConfidence(): int { return $this->intent_confidence; }
	public function getSelectedProvider(): string { return $this->selected_provider; }
	public function getFallbackProvider(): string { return $this->fallback_provider; }
	public function getDurationMs(): int { return $this->duration_ms; }
	public function getCacheTtl(): int { return $this->cache_ttl; }
}
