<?php
/**
 * Plugin Name:       ADAM BOT
 * Plugin URI:        https://airsoftmondego.pt/
 * Description:       Accessible ADAM virtual assistant powered by the local Knowledge Response Engine.
 * Version:           1.5.0
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

define( 'ADAM_BOT_VERSION', '1.5.0' );
define( 'ADAM_BOT_FILE', __FILE__ );
define( 'ADAM_BOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADAM_BOT_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
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
);

if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( ADAM_BOT_FILE, array( AdamBot\Knowledge\KnowledgeSettings::class, 'activate' ) );
	register_activation_hook( ADAM_BOT_FILE, array( AdamBot\UX\ExperienceSettings::class, 'activate' ) );
	register_activation_hook( ADAM_BOT_FILE, array( AdamBot\Analytics\Analytics::class, 'activate' ) );
}

( new AdamBot\Core\Plugin() )->run();
