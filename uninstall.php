<?php
/**
 * ADAM BOT uninstall routine.
 *
 * Removes plugin settings while preserving administrator-authored content.
 *
 * @package AdamBot
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'adam_bot_ai_settings' );
delete_option( 'adam_bot_knowledge_settings' );
delete_option( 'adam_bot_knowledge_cache_version' );
delete_option( 'adam_bot_experience_settings' );
delete_option( 'adam_bot_analytics' );
delete_option( 'adam_bot_provider_health' );
delete_option( 'adam_bot_maintenance_status' );
delete_option( 'adam_bot_saved_searches' );
delete_option( 'adam_bot_site_index_status' );
delete_option( 'adam_bot_site_index_translation_queue' );
delete_option( 'adam_bot_knowledge_schema_version' );

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'adam_bot_daily_maintenance' );
	wp_clear_scheduled_hook( 'adam_bot_initial_site_index' );
	wp_clear_scheduled_hook( 'adam_bot_site_index_translation_batch' );
	wp_clear_scheduled_hook( 'adam_bot_migrate_legacy_knowledge' );
}
