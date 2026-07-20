<?php
/**
 * ADAM BOT uninstall routine.
 *
 * Removes the plugin's persisted AI configuration, including its API key.
 *
 * @package AdamBot
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'adam_bot_ai_settings' );
