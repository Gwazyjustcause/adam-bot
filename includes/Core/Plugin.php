<?php
/**
 * Main plugin controller.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

use AdamBot\Admin\SettingsPage;
use AdamBot\AI\Providers\ProviderFactory;
use AdamBot\AI\Services\AIService;
use AdamBot\AI\Services\PromptBuilder;
use AdamBot\AI\Settings\AISettings;
use AdamBot\API\API;
use AdamBot\API\RateLimiter;
use AdamBot\Frontend\Frontend;
use AdamBot\Helpers\Logger;

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

		$settings = new AISettings();
		$logger   = new Logger();

		$settings->ensureDefaults();

		$prompt_builder   = new PromptBuilder( $settings );
		$provider_factory = new ProviderFactory( $settings );
		$ai_service       = new AIService( $settings, $provider_factory, $prompt_builder, $logger );

		$this->components = array(
			new Frontend(),
			new API( $ai_service, new RateLimiter() ),
			new SettingsPage( $settings ),
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
