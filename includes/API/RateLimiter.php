<?php
/**
 * Public chat rate limiter.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\API;

defined( 'ABSPATH' ) || exit;

/**
 * Implements a small fixed-window, IP-based request limit with transients.
 */
final class RateLimiter {
	/** @var int */
	private $limit;

	/** @var int */
	private $window;

	/**
	 * Creates the limiter.
	 *
	 * @param int $limit Maximum requests in one window.
	 * @param int $window Window duration in seconds.
	 */
	public function __construct( int $limit = 20, int $window = 300 ) {
		$this->limit  = max( 1, $limit );
		$this->window = max( 1, $window );
	}

	/**
	 * Consumes one request for the current remote address.
	 *
	 * Forwarded headers are intentionally ignored because they are spoofable
	 * unless a trusted proxy has been configured outside this plugin.
	 *
	 * @return bool True when the request is allowed.
	 */
	public function consume(): bool {
		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: 'unknown';

		if ( false === filter_var( $address, FILTER_VALIDATE_IP ) ) {
			$address = 'unknown';
		}

		$key    = 'adam_bot_rate_' . hash_hmac( 'sha256', $address, wp_salt( 'auth' ) );
		$record = get_transient( $key );
		$now    = time();

		if (
			! is_array( $record )
			|| ! isset( $record['count'], $record['reset'] )
			|| (int) $record['reset'] <= $now
		) {
			set_transient(
				$key,
				array(
					'count' => 1,
					'reset' => $now + $this->window,
				),
				$this->window
			);

			return true;
		}

		if ( (int) $record['count'] >= $this->limit ) {
			return false;
		}

		$record['count'] = (int) $record['count'] + 1;
		$ttl             = max( 1, (int) $record['reset'] - $now );
		set_transient( $key, $record, $ttl );

		return true;
	}
}
