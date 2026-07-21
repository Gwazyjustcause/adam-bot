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
use AdamBot\API\API;
use AdamBot\API\RateLimiter;
use AdamBot\Frontend\Frontend;
use AdamBot\Helpers\Logger;
use AdamBot\Knowledge\KnowledgeAdmin;
use AdamBot\Knowledge\KnowledgeSettings;
use AdamBot\Knowledge\DuplicateDetector;
use AdamBot\Knowledge\ImportExport;
use AdamBot\Knowledge\RevisionManager;
use AdamBot\Knowledge\Search\KeywordMatcher;
use AdamBot\Knowledge\Search\ResultRanker;
use AdamBot\Knowledge\Search\SearchService;
use AdamBot\Knowledge\Response\ResponseFormatter;
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

		$knowledge_settings  = new KnowledgeSettings();
		$experience_settings = new ExperienceSettings();
		$analytics            = new Analytics();
		$logger               = new Logger();

		$knowledge_settings->ensureDefaults();
		$experience_settings->ensureDefaults();
		$analytics->ensureDefaults();

		$matcher        = new KeywordMatcher();
		$result_ranker  = new ResultRanker( $matcher );
		$search_service = new SearchService(
			$knowledge_settings,
			$result_ranker,
			$matcher,
			$logger,
			array(
				new FAQSource(),
				new PageSource( $knowledge_settings ),
				new MembershipSource(),
				new EventSource(),
				new ManualSource(),
			)
		);
		$response_formatter = new ResponseFormatter( $matcher );
		$knowledge_admin    = new KnowledgeAdmin( $knowledge_settings, $search_service, $response_formatter, new DuplicateDetector( $matcher ) );

		$this->components = array(
			new Frontend( $experience_settings ),
			new API( $search_service, $response_formatter, new RateLimiter(), $analytics ),
			new SettingsPage( $experience_settings, $analytics, $knowledge_admin ),
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
