<?php
/**
 * Frontend component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Frontend;

use AdamBot\UX\ExperienceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public chat interface.
 */
final class Frontend {
	/** @var ExperienceSettings */
	private $experience_settings;

	/** @param ExperienceSettings $experience_settings Public experience settings. */
	public function __construct( ExperienceSettings $experience_settings ) {
		$this->experience_settings = $experience_settings;
	}

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

		$quick_actions = $this->experience_settings->all()['quick_actions'];

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
