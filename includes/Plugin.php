<?php
/**
 * Main plugin controller.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot;

use AdamBot\Chat\FakeResponseService;
use AdamBot\Frontend\Assets;
use AdamBot\Frontend\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates the public-facing plugin modules.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether the plugin has already registered its hooks.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Gets the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers frontend modules for public requests only.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted || ! $this->is_public_request() ) {
			return;
		}

		$this->booted = true;

		$fake_response_service = new FakeResponseService();
		$assets                = new Assets( $fake_response_service );
		$widget                = new Widget();

		$assets->register_hooks();
		$widget->register_hooks();
	}

	/**
	 * Determines whether the current request can display the widget.
	 *
	 * @return bool
	 */
	private function is_public_request(): bool {
		global $pagenow;

		if ( is_admin() ) {
			return false;
		}

		return ! isset( $pagenow ) || 'wp-login.php' !== $pagenow;
	}

	/**
	 * Prevents direct construction.
	 */
	private function __construct() {
	}
}
