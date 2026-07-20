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

		if ( ! empty( $context ) ) {
			$message .= ' ' . wp_json_encode( $context );
		}

		error_log( '[ADAM BOT] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
