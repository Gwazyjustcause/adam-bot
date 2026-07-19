<?php
/**
 * Plugin Name:       ADAM BOT
 * Plugin URI:        https://airsoftmondego.pt/
 * Description:       A modern virtual assistant shell for the public-facing ADAM website.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            ADAM
 * Text Domain:       adam-bot
 * Domain Path:       /languages
 *
 * @package AdamBot
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'ADAM_BOT_VERSION', '1.0.0' );
define( 'ADAM_BOT_FILE', __FILE__ );
define( 'ADAM_BOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADAM_BOT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Loads ADAM BOT classes from the includes directory.
 *
 * @param string $class Fully-qualified class name.
 * @return void
 */
function adam_bot_autoload( string $class ): void {
	$namespace = 'AdamBot\\';

	if ( 0 !== strpos( $class, $namespace ) ) {
		return;
	}

	$relative_class = substr( $class, strlen( $namespace ) );
	$file           = ADAM_BOT_PATH . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

spl_autoload_register( 'adam_bot_autoload' );

/**
 * Boots the plugin after all plugins have loaded.
 *
 * @return void
 */
function adam_bot_boot(): void {
	AdamBot\Plugin::instance()->boot();
}

add_action( 'plugins_loaded', 'adam_bot_boot' );
