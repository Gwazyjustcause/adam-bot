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
 * Renders the public chat interface.
 */
final class Frontend {
	/**
	 * Registers frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! $this->is_public_page() ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'render_widget' ) );
	}

	/**
	 * Renders the chat widget.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		if ( ! $this->is_public_page() ) {
			return;
		}

		require ADAM_BOT_PATH . 'templates/chat-widget.php';
	}

	/**
	 * Determines whether frontend output is allowed for the current request.
	 *
	 * @return bool
	 */
	private function is_public_page(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( function_exists( 'is_login' ) && is_login() ) {
			return false;
		}

		return 'wp-login.php' !== ( $GLOBALS['pagenow'] ?? '' );
	}
}
