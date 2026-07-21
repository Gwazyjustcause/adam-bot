<?php
/**
 * Main plugin controller.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

use AdamBot\Admin\SettingsPage;
use AdamBot\Admin\ProductionAdmin;
use AdamBot\Analytics\Analytics;
use AdamBot\Analytics\ProviderMonitor;
use AdamBot\Analytics\SearchInsights;
use AdamBot\API\API;
use AdamBot\API\RateLimiter;
use AdamBot\Frontend\Frontend;
use AdamBot\Helpers\Logger;
use AdamBot\Knowledge\KnowledgeAdmin;
use AdamBot\Knowledge\KnowledgeSettings;
use AdamBot\Knowledge\DuplicateDetector;
use AdamBot\Knowledge\ImportExport;
use AdamBot\Knowledge\RevisionManager;
use AdamBot\Knowledge\Dynamic\BuiltInProviders;
use AdamBot\Knowledge\Dynamic\DynamicProviderRegistry;
use AdamBot\Knowledge\Dynamic\IntentDetector;
use AdamBot\Knowledge\Dynamic\ProviderResolver;
use AdamBot\Knowledge\Search\KeywordMatcher;
use AdamBot\Knowledge\Search\ResultRanker;
use AdamBot\Knowledge\Search\SearchService;
use AdamBot\Knowledge\Response\ResponseFormatter;
use AdamBot\Knowledge\Sources\EventSource;
use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;
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

	/** @var DynamicProviderRegistry */
	private $dynamic_providers;

	/** @var bool */
	private $initialized = false;

	public function __construct( ?DynamicProviderRegistry $dynamic_providers = null ) {
		$this->dynamic_providers = $dynamic_providers ?: new DynamicProviderRegistry();
	}

	/**
	 * Loads dependencies and registers plugin hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'init', array( $this, 'initialize' ), 1 );
	}

	/**
	 * Builds services only after WordPress has started init and loaded the domain.
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		BuiltInProviders::register( $this->dynamic_providers );
		$this->dynamic_providers->register_hooks();
		$this->dynamic_providers->discover();

		$knowledge_settings  = new KnowledgeSettings();
		$experience_settings = new ExperienceSettings();
		$analytics            = new Analytics();
		$provider_monitor     = new ProviderMonitor();
		$logger               = new Logger();

		$knowledge_settings->ensureDefaults();
		$experience_settings->ensureDefaults();
		$analytics->ensureDefaults();

		$matcher        = new KeywordMatcher();
		$result_ranker  = new ResultRanker( $matcher );
		$intent_detector  = new IntentDetector( $matcher );
		$duplicate_detector = new DuplicateDetector( $matcher );
		$provider_resolver = new ProviderResolver( $this->dynamic_providers, $intent_detector, $knowledge_settings, $matcher, $logger );
		$search_service = new SearchService(
			$knowledge_settings,
			$result_ranker,
			$matcher,
			$logger,
			array(
				new FAQSource(),
				new PageSource( $knowledge_settings ),
				new ManualSource(),
				new EventSource(),
			),
			$provider_resolver
		);
		$response_formatter = new ResponseFormatter( $matcher );
		$knowledge_admin    = new KnowledgeAdmin( $knowledge_settings, $search_service, $response_formatter, $duplicate_detector );
		$search_insights    = new SearchInsights( $duplicate_detector, $intent_detector );

		$this->components = array(
			new Frontend( $experience_settings ),
			new API( $search_service, $response_formatter, new RateLimiter(), $analytics, $knowledge_settings ),
			new SettingsPage( $experience_settings, $analytics, $knowledge_admin, $search_insights ),
			new ProductionAdmin( $analytics, $this->dynamic_providers, $provider_monitor ),
			$provider_monitor,
			new Maintenance( $knowledge_settings, $search_service, $analytics ),
			$knowledge_admin,
			new RevisionManager(),
			new ImportExport(),
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
