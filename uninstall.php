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
