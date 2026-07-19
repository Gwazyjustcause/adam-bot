<?php
/**
 * Minimal lifecycle smoke test for environments without WordPress installed.
 *
 * Run with: php tests/plugin-smoke.php public|admin|login
 *
 * @package AdamBot
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

$test_mode     = $argv[1] ?? 'public';
$test_hooks    = array();
$test_is_admin = 'admin' === $test_mode;
$pagenow      = 'login' === $test_mode ? 'wp-login.php' : 'index.php';

/**
 * WordPress fixture for plugin_dir_path().
 *
 * @param string $file Plugin file.
 * @return string
 */
function plugin_dir_path( string $file ): string {
	return dirname( $file ) . DIRECTORY_SEPARATOR;
}

/**
 * WordPress fixture for plugin_dir_url().
 *
 * @return string
 */
function plugin_dir_url(): string {
	return 'https://example.test/wp-content/plugins/adam-bot/';
}

/**
 * Records hook registrations.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Hook callback.
 * @param int      $priority Hook priority.
 * @return void
 */
function add_action( string $hook, callable $callback, int $priority = 10 ): void {
	global $test_hooks;

	$test_hooks[ $hook ][] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
}

/**
 * WordPress fixture for is_admin().
 *
 * @return bool
 */
function is_admin(): bool {
	global $test_is_admin;

	return $test_is_admin;
}

require dirname( __DIR__ ) . '/adam-bot.php';

if ( empty( $test_hooks['plugins_loaded'][0]['callback'] ) ) {
	fwrite( STDERR, "Plugin bootstrap hook was not registered.\n" );
	exit( 1 );
}

call_user_func( $test_hooks['plugins_loaded'][0]['callback'] );

$frontend_registered = isset( $test_hooks['wp_enqueue_scripts'], $test_hooks['wp_footer'] );
$should_register      = 'public' === $test_mode;

if ( $frontend_registered !== $should_register ) {
	fwrite( STDERR, sprintf( "Unexpected frontend hook state for %s mode.\n", $test_mode ) );
	exit( 1 );
}

echo sprintf( "PASS: %s request boundary.\n", $test_mode );
