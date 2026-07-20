<?php
/**
 * Development logger.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a single logging boundary for plugin diagnostics.
 */
final class Logger {
	/**
	 * Whether logging is enabled.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Creates a logger that follows the WordPress debug setting by default.
	 *
	 * @param bool|null $enabled Optional explicit enabled state.
	 */
	public function __construct( ?bool $enabled = null ) {
		$this->enabled = null === $enabled
			? ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			: $enabled;
	}

	/**
	 * Writes a diagnostic message when development logging is enabled.
	 *
	 * @param string               $message Diagnostic message.
	 * @param array<string, mixed> $context Optional structured context.
	 * @return void
	 */
	public function log( string $message, array $context = array() ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$this->write( $message, $context );
	}

	/**
	 * Writes operational metadata without requiring debug mode.
	 *
	 * @param string               $message Diagnostic message.
	 * @param array<string, mixed> $context Optional structured context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->write( $message, $context );
	}

	/**
	 * Writes an operational failure even when debug diagnostics are disabled.
	 *
	 * @param string               $message Diagnostic message.
	 * @param array<string, mixed> $context Optional structured context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->write( $message, $context );
	}

	/**
	 * Writes redacted structured data to the PHP error log.
	 *
	 * @param string               $message Diagnostic message.
	 * @param array<string, mixed> $context Optional structured context.
	 * @return void
	 */
	private function write( string $message, array $context ): void {
		if ( ! empty( $context ) ) {
			$encoded = wp_json_encode( $this->redact( $context ) );

			if ( false !== $encoded ) {
				$message .= ' ' . $encoded;
			}
		}

		error_log( '[ADAM BOT] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Removes secrets from diagnostic context recursively.
	 *
	 * @param array<string, mixed> $context Context data.
	 * @return array<string, mixed>
	 */
	private function redact( array $context ): array {
		$redacted = array();

		foreach ( $context as $key => $value ) {
			$key_string = (string) $key;

			if ( preg_match( '/api[_-]?key|secret|authorization|access[_-]?token|refresh[_-]?token/i', $key_string ) ) {
				$redacted[ $key ] = '[redacted]';
				continue;
			}

			if ( is_array( $value ) ) {
				$redacted[ $key ] = $this->redact( $value );
				continue;
			}

			if ( is_string( $value ) ) {
				$redacted[ $key ] = preg_replace( '/sk-[A-Za-z0-9_-]{8,}/', '[redacted]', $value );
				continue;
			}

			$redacted[ $key ] = $value;
		}

		return $redacted;
	}
}
