<?php
/**
 * Optional ADAM Interface integration.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Opts only ADAM BOT-owned screens into the shared visual framework.
 *
 * ADAM Interface remains optional. BOT keeps its standalone styles and
 * behavior when the shared plugin is unavailable.
 */
final class InterfaceIntegration {
	/** Registers integration hooks without creating a hard dependency. */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enable_admin_theme' ), 1 );
	}

	/**
	 * Enables shared assets only for ADAM BOT admin screens.
	 *
	 * @param string $hook_suffix Current WordPress admin hook suffix.
	 */
	public function enable_admin_theme( string $hook_suffix = '' ): void {
		if ( ! $this->is_adam_bot_screen( $hook_suffix ) ) {
			return;
		}

		if ( function_exists( 'adam_interface_enable_admin_theme' ) ) {
			adam_interface_enable_admin_theme();
		}
	}

	/** Returns whether the current screen belongs to ADAM BOT. */
	private function is_adam_bot_screen( string $hook_suffix ): bool {
		if ( false !== strpos( $hook_suffix, 'adam-bot' ) ) {
			return true;
		}

		$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : '';
		$taxonomy  = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) ) : '';

		if ( 'adam_bot_knowledge' === $post_type || 'adam_bot_category' === $taxonomy ) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			if ( $screen && ( 'adam_bot_knowledge' === $screen->post_type || 'adam_bot_category' === $screen->taxonomy ) ) {
				return true;
			}
		}

		return 0 === strpos( $page, 'adam-bot' );
	}
}
