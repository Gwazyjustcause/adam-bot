<?php
/**
 * Scheduled production maintenance.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

use AdamBot\Analytics\Analytics;
use AdamBot\Knowledge\KnowledgeSettings;
use AdamBot\Knowledge\Search\SearchService;

defined( 'ABSPATH' ) || exit;

/** Keeps caches and bounded analytics healthy through WordPress Cron. */
final class Maintenance {
	public const CRON_HOOK = 'adam_bot_daily_maintenance';
	public const STATUS_KEY = 'adam_bot_maintenance_status';

	/** @var KnowledgeSettings */ private $settings;
	/** @var SearchService */ private $search;
	/** @var Analytics */ private $analytics;

	public function __construct( KnowledgeSettings $settings, SearchService $search, Analytics $analytics ) {
		$this->settings  = $settings;
		$this->search    = $search;
		$this->analytics = $analytics;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'ensureScheduled' ), 30 );
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
	}

	/** @return void */
	public function ensureScheduled(): void {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_event' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600 ), 'daily', self::CRON_HOOK );
		}
	}

	/** @return void */
	public function run(): void {
		$started = microtime( true );
		try {
			$removed = $this->analytics->prune();
			$this->settings->bumpCacheVersion();
			if ( function_exists( 'delete_expired_transients' ) ) { delete_expired_transients( true ); }
			$rebuilt = 0;
			foreach ( $this->analytics->getCommonQuestions( 20 ) as $row ) {
				$question = sanitize_text_field( (string) ( $row['question'] ?? '' ) );
				if ( '' !== $question && false === strpos( $question, '[' ) ) {
					$this->search->search( $question );
					$rebuilt++;
				}
			}
			do_action( 'adam_bot_maintenance_optimize_storage' );
			update_option( self::STATUS_KEY, array( 'status' => 'success', 'ran_at' => gmdate( 'c' ), 'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ), 'removed' => $removed, 'rebuilt' => $rebuilt ), false );
		} catch ( \Throwable $exception ) {
			update_option( self::STATUS_KEY, array( 'status' => 'error', 'ran_at' => gmdate( 'c' ), 'message' => sanitize_text_field( $exception->getMessage() ) ), false );
		}
	}

	/** @return void */
	public static function activate(): void {
		if ( function_exists( 'wp_schedule_event' ) && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600 ), 'daily', self::CRON_HOOK );
		}
	}

	/** @return void */
	public static function deactivate(): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) { wp_clear_scheduled_hook( self::CRON_HOOK ); }
	}
}
