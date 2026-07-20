<?php
/**
 * Main plugin controller.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

use AdamBot\API\API;
use AdamBot\Chat\Chat;
use AdamBot\Frontend\Frontend;
use AdamBot\Helpers\Logger;
use AdamBot\Services\ChatService;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates the plugin components.
 */
final class Plugin {
	/**
	 * Registered plugin components.
	 *
	 * @var array<int, object>
	 */
	private $components = array();

	/**
	 * Loads dependencies and registers plugin hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$logger       = new Logger();
		$chat_service = new ChatService( $logger );

		$this->components = array(
			new Frontend(),
			new API( $chat_service ),
			new Chat( $chat_service ),
			new Assets(),
		);

		foreach ( $this->components as $component ) {
			$component->register_hooks();
		}
	}

	/**
	 * Loads translations for the plugin.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'adam-bot',
			false,
			dirname( plugin_basename( ADAM_BOT_FILE ) ) . '/languages'
		);
	}
}
