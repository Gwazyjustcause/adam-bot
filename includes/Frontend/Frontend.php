<?php
/**
 * Frontend component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the public mount point for future interfaces.
 */
final class Frontend {
	/**
	 * Registers frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'render_mount_point' ) );
	}

	/**
	 * Renders the future chatbot mount point.
	 *
	 * @return void
	 */
	public function render_mount_point(): void {
		printf( '<div id="%s"></div>', esc_attr( 'adam-bot-root' ) );
	}
}
