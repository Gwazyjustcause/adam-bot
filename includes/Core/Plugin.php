<?php
/**
 * Main plugin controller.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

use AdamBot\Admin\SettingsPage;
use AdamBot\Analytics\Analytics;
use AdamBot\AI\Providers\ProviderFactory;
use AdamBot\AI\Services\AIService;
use AdamBot\AI\Services\PromptBuilder;
use AdamBot\AI\Settings\AISettings;
use AdamBot\API\API;
use AdamBot\API\RateLimiter;
use AdamBot\Frontend\Frontend;
use AdamBot\Helpers\Logger;
use AdamBot\Knowledge\KnowledgeAdmin;
use AdamBot\Knowledge\KnowledgeService;
use AdamBot\Knowledge\KnowledgeSettings;
use AdamBot\Knowledge\Search\KeywordMatcher;
use AdamBot\Knowledge\Sources\EventSource;
use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;
use AdamBot\Knowledge\Sources\MembershipSource;
use AdamBot\Knowledge\Sources\PageSource;
use AdamBot\UX\ExperienceSettings;

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

		$settings            = new AISettings();
		$knowledge_settings  = new KnowledgeSettings();
		$experience_settings = new ExperienceSettings();
		$analytics            = new Analytics();
		$logger               = new Logger();

		$settings->ensureDefaults();
		$knowledge_settings->ensureDefaults();
		$experience_settings->ensureDefaults();
		$analytics->ensureDefaults();

		$matcher           = new KeywordMatcher();
		$knowledge_service = new KnowledgeService(
			$knowledge_settings,
			$matcher,
			$logger,
			array(
				new FAQSource( $matcher ),
				new PageSource( $matcher, $knowledge_settings ),
				new MembershipSource( $matcher ),
				new EventSource( $matcher ),
				new ManualSource( $matcher ),
			)
		);
		$prompt_builder   = new PromptBuilder( $settings, $knowledge_service );
		$provider_factory = new ProviderFactory( $settings );
		$ai_service       = new AIService( $settings, $provider_factory, $prompt_builder, $logger );

		$this->components = array(
			new Frontend( $experience_settings ),
			new API( $ai_service, new RateLimiter(), $analytics ),
			new SettingsPage( $settings, $experience_settings, $analytics ),
			new KnowledgeAdmin( $knowledge_settings ),
			new Assets( $experience_settings ),
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
