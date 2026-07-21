<?php
/**
 * Intent-aware dynamic provider resolver.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Dynamic;

use AdamBot\Helpers\Logger;
use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\KnowledgeSettings;
use AdamBot\Knowledge\Search\KeywordMatcher;

defined( 'ABSPATH' ) || exit;

/** Selects only relevant lazy providers and stops at the first live match. */
final class ProviderResolver {
	/** @var DynamicProviderRegistry */
	private $registry;
	/** @var IntentDetector */
	private $intent_detector;
	/** @var KnowledgeSettings */
	private $settings;
	/** @var KeywordMatcher */
	private $matcher;
	/** @var Logger */
	private $logger;

	public function __construct( DynamicProviderRegistry $registry, IntentDetector $intent_detector, KnowledgeSettings $settings, KeywordMatcher $matcher, Logger $logger ) {
		$this->registry        = $registry;
		$this->intent_detector = $intent_detector;
		$this->settings        = $settings;
		$this->matcher         = $matcher;
		$this->logger          = $logger;
	}

	public function search( string $query ): DynamicSearchResult {
		$started = microtime( true );
		$match   = $this->intent_detector->detect( $query );
		$intent  = (string) $match['intent'];
		if ( Intent::KNOWLEDGE === $intent || Intent::WEBSITE === $intent ) {
			return new DynamicSearchResult( array(), $intent, (int) $match['confidence'], '', '', $this->elapsed( $started ) );
		}

		$attempted = array();
		foreach ( $this->registry->getProvidersForIntent( $intent ) as $provider ) {
			if ( ! $this->settings->isSourceEnabled( $provider->getKey() ) ) {
				continue;
			}
			$attempted[] = $provider->getKey();
			$provider_started = microtime( true );
			$results     = $this->providerSearch( $provider, $query, $intent );
			do_action( 'adam_bot_dynamic_provider_observed', $provider->getKey(), count( $results ), $this->elapsed( $provider_started ) );
			if ( empty( $results ) ) {
				continue;
			}
			try {
				$suggestions = $provider->getSuggestions( $query, $intent );
			} catch ( \Throwable $exception ) {
				$this->logger->error( 'Dynamic provider suggestions failed.', array( 'provider' => $provider->getKey(), 'intent' => $intent, 'error_type' => get_class( $exception ), 'error' => $exception->getMessage() ) );
				$suggestions = array();
			}
			if ( ! empty( $suggestions ) ) {
				$results = array_map( static function ( KnowledgeResult $result ) use ( $suggestions ): KnowledgeResult { return $result->withRelated( $suggestions ); }, $results );
			}
			$fallback = count( $attempted ) > 1 ? $provider->getKey() : '';
			return new DynamicSearchResult( $results, $intent, (int) $match['confidence'], $provider->getKey(), $fallback, $this->elapsed( $started ), $this->cacheTtl( $provider ) );
		}

		return new DynamicSearchResult( array(), $intent, (int) $match['confidence'], $attempted[0] ?? '', 'static', $this->elapsed( $started ) );
	}

	public function getCacheSignature(): string {
		return $this->registry->getCacheSignature();
	}

	/** @return array<int,KnowledgeResult> */
	private function providerSearch( DynamicProviderInterface $provider, string $query, string $intent ): array {
		$key    = 'adam_bot_dynamic_' . $this->settings->getCacheVersion() . '_' . sanitize_key( $provider->getKey() ) . '_' . md5( $this->matcher->normalize( $query ) . '|' . $intent );
		$cached = $this->cacheGet( $key );
		if ( is_array( $cached ) ) {
			$restored = array();
			foreach ( $cached as $row ) {
				if ( is_array( $row ) ) { $restored[] = KnowledgeResult::fromArray( $row ); }
			}
			return $restored;
		}

		try {
			$results = $provider->search( $query, $intent );
		} catch ( \Throwable $exception ) {
			$this->logger->error( 'Dynamic knowledge provider failed.', array( 'provider' => $provider->getKey(), 'intent' => $intent, 'error_type' => get_class( $exception ), 'error' => $exception->getMessage() ) );
			return array();
		}
		$results = array_values( array_filter( is_array( $results ) ? $results : array(), static function ( $result ): bool { return $result instanceof KnowledgeResult; } ) );
		$this->cacheSet( $key, array_map( static function ( KnowledgeResult $result ): array { return $result->toArray(); }, $results ), $this->cacheTtl( $provider ) );
		return $results;
	}

	private function cacheTtl( DynamicProviderInterface $provider ): int {
		try {
			return max( 10, min( 900, $provider->getCacheTtl() ) );
		} catch ( \Throwable $exception ) {
			return 120;
		}
	}

	private function elapsed( float $started ): int {
		return (int) round( ( microtime( true ) - $started ) * 1000 );
	}

	/** @return mixed */
	private function cacheGet( string $key ) {
		if ( function_exists( 'wp_cache_get' ) ) {
			$found = false;
			$value = wp_cache_get( $key, 'adam_bot', false, $found );
			if ( $found ) { return $value; }
		}
		return get_transient( $key );
	}

	/** @param mixed $value Cached value. */
	private function cacheSet( string $key, $value, int $ttl ): void {
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $key, $value, 'adam_bot', $ttl );
		}
		set_transient( $key, $value, $ttl );
	}
}
