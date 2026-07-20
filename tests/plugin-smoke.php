<?php
/**
 * Minimal lifecycle smoke test for environments without WordPress installed.
 *
 * Run with: php tests/plugin-smoke.php public|admin
 *
 * @package AdamBot
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'WP_DEBUG', true );

$test_mode       = $argv[1] ?? 'public';
$test_hooks      = array();
$test_routes     = array();
$test_assets     = array();
$test_textdomain = array();
$test_is_admin   = 'admin' === $test_mode;
$test_is_login   = 'login' === $test_mode;

/**
 * Minimal REST server fixture.
 */
final class WP_REST_Server {
	public const CREATABLE = 'POST';
}

/**
 * Minimal REST request fixture.
 */
final class WP_REST_Request {
}

/**
 * Minimal REST response fixture.
 */
final class WP_REST_Response {
	/**
	 * Response payload.
	 *
	 * @var array<string, mixed>
	 */
	private $data;

	/**
	 * Creates the response.
	 *
	 * @param array<string, mixed> $data Response payload.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * Gets the response payload.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		return $this->data;
	}
}

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
 * WordPress fixture for plugin_basename().
 *
 * @param string $file Plugin file.
 * @return string
 */
function plugin_basename( string $file ): string {
	return 'adam-bot/' . basename( $file );
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

/**
 * WordPress fixture for is_login().
 *
 * @return bool
 */
function is_login(): bool {
	global $test_is_login;

	return $test_is_login;
}

/**
 * Records textdomain loading.
 *
 * @param string $domain Textdomain.
 * @param bool   $deprecated Deprecated path argument.
 * @param string $path Relative language path.
 * @return bool
 */
function load_plugin_textdomain( string $domain, bool $deprecated, string $path ): bool {
	global $test_textdomain;

	$test_textdomain = compact( 'domain', 'deprecated', 'path' );

	return true;
}

/**
 * Records REST route registration.
 *
 * @param string               $namespace Route namespace.
 * @param string               $route Route path.
 * @param array<string, mixed> $args Route arguments.
 * @return bool
 */
function register_rest_route( string $namespace, string $route, array $args ): bool {
	global $test_routes;

	$test_routes[ $namespace . $route ] = $args;

	return true;
}

/**
 * Translation fixture.
 *
 * @param string $text Source text.
 * @return string
 */
function __( string $text, string $domain = 'default' ): string {
	unset( $domain );

	return $text;
}

/**
 * HTML escaping fixture.
 *
 * @param string $value HTML value.
 * @return string
 */
function esc_html( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * Echoes an escaped translated HTML value.
 *
 * @param string $text Source text.
 * @param string $domain Textdomain.
 * @return void
 */
function esc_html_e( string $text, string $domain = 'default' ): void {
	echo esc_html( __( $text, $domain ) );
}

/**
 * Echoes an escaped translated attribute value.
 *
 * @param string $text Source text.
 * @param string $domain Textdomain.
 * @return void
 */
function esc_attr_e( string $text, string $domain = 'default' ): void {
	echo esc_attr( __( $text, $domain ) );
}

/**
 * REST response fixture.
 *
 * @param array<string, mixed> $data Response payload.
 * @return WP_REST_Response
 */
function rest_ensure_response( array $data ): WP_REST_Response {
	return new WP_REST_Response( $data );
}

/**
 * Attribute escaping fixture.
 *
 * @param string $value Attribute value.
 * @return string
 */
function esc_attr( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * URL escaping fixture.
 *
 * @param string $url URL.
 * @return string
 */
function esc_url_raw( string $url ): string {
	return $url;
}

/**
 * REST URL fixture.
 *
 * @param string $path REST path.
 * @return string
 */
function rest_url( string $path ): string {
	return 'https://example.test/wp-json/' . $path;
}

/**
 * Nonce fixture.
 *
 * @return string
 */
function wp_create_nonce(): string {
	return 'test-nonce';
}

/**
 * Records a registered stylesheet.
 *
 * @param string              $handle Handle.
 * @param string              $src Source URL.
 * @param array<int, string>  $dependencies Dependencies.
 * @param string|bool|null    $version Version.
 * @return bool
 */
function wp_register_style( string $handle, string $src, array $dependencies, $version ): bool {
	global $test_assets;

	$test_assets['styles'][ $handle ] = compact( 'src', 'dependencies', 'version' );

	return true;
}

/**
 * Records a registered script.
 *
 * @param string             $handle Handle.
 * @param string             $src Source URL.
 * @param array<int, string> $dependencies Dependencies.
 * @param string|bool|null   $version Version.
 * @param bool               $in_footer Whether the script is footer-bound.
 * @return bool
 */
function wp_register_script( string $handle, string $src, array $dependencies, $version, bool $in_footer ): bool {
	global $test_assets;

	$test_assets['scripts'][ $handle ] = compact( 'src', 'dependencies', 'version', 'in_footer' );

	return true;
}

/**
 * Records localized script data.
 *
 * @param string               $handle Script handle.
 * @param string               $object_name JavaScript object name.
 * @param array<string, mixed> $data Localized data.
 * @return bool
 */
function wp_localize_script( string $handle, string $object_name, array $data ): bool {
	global $test_assets;

	$test_assets['localized'][ $handle ] = compact( 'object_name', 'data' );

	return true;
}

/**
 * Records a stylesheet enqueue.
 *
 * @param string $handle Handle.
 * @return void
 */
function wp_enqueue_style( string $handle ): void {
	global $test_assets;

	$test_assets['enqueued_styles'][] = $handle;
}

/**
 * Records a script enqueue.
 *
 * @param string $handle Handle.
 * @return void
 */
function wp_enqueue_script( string $handle ): void {
	global $test_assets;

	$test_assets['enqueued_scripts'][] = $handle;
}

require dirname( __DIR__ ) . '/adam-bot.php';

if ( empty( $test_hooks['init'][0]['callback'] ) || empty( $test_hooks['rest_api_init'][0]['callback'] ) ) {
	fwrite( STDERR, "Core hooks were not registered.\n" );
	exit( 1 );
}

call_user_func( $test_hooks['init'][0]['callback'] );
call_user_func( $test_hooks['rest_api_init'][0]['callback'] );

if ( 'adam-bot' !== ( $test_textdomain['domain'] ?? '' ) ) {
	fwrite( STDERR, "Textdomain was not loaded.\n" );
	exit( 1 );
}

$route = $test_routes['adam-bot/v1/chat'] ?? array();

if ( WP_REST_Server::CREATABLE !== ( $route['methods'] ?? '' ) || empty( $route['callback'] ) ) {
	fwrite( STDERR, "REST route was not registered correctly.\n" );
	exit( 1 );
}

$response = call_user_func( $route['callback'], new WP_REST_Request() );

if ( array( 'success' => true, 'message' => 'API ready' ) !== $response->get_data() ) {
	fwrite( STDERR, "REST response did not match the readiness contract.\n" );
	exit( 1 );
}

if ( 'public' === $test_mode ) {
	if ( empty( $test_hooks['wp_enqueue_scripts'][0]['callback'] ) || empty( $test_hooks['wp_footer'][0]['callback'] ) ) {
		fwrite( STDERR, "Frontend hooks were not registered.\n" );
		exit( 1 );
	}

	call_user_func( $test_hooks['wp_enqueue_scripts'][0]['callback'] );

	ob_start();
	call_user_func( $test_hooks['wp_footer'][0]['callback'] );
	$widget = ob_get_clean();

	if (
		false === strpos( $widget, 'id="adam-bot-root"' )
		|| false === strpos( $widget, 'data-adam-launcher' )
		|| false === strpos( $widget, 'Olá!' )
		|| false === strpos( $widget, 'Pergunte ao ADAM BOT...' )
	) {
		fwrite( STDERR, "Frontend widget was not rendered correctly.\n" );
		exit( 1 );
	}

	if ( empty( $test_assets['styles']['adam-bot'] ) || empty( $test_assets['scripts']['adam-bot'] ) ) {
		fwrite( STDERR, "Frontend assets were not registered.\n" );
		exit( 1 );
	}

	$style_version = (string) $test_assets['styles']['adam-bot']['version'];
	$settings      = $test_assets['localized']['adam-bot']['data'] ?? array();

	if ( ! ctype_digit( $style_version ) ) {
		fwrite( STDERR, "Development assets were not versioned with filemtime().\n" );
		exit( 1 );
	}

	if ( 'test-nonce' !== ( $settings['nonce'] ?? '' ) ) {
		fwrite( STDERR, "REST nonce was not provided to the frontend script.\n" );
		exit( 1 );
	}

	if ( 'https://example.test/wp-json/adam-bot/v1/chat' !== ( $settings['restUrl'] ?? '' ) ) {
		fwrite( STDERR, "REST chat URL was not provided to the frontend script.\n" );
		exit( 1 );
	}
} elseif ( isset( $test_hooks['wp_enqueue_scripts'] ) || isset( $test_hooks['wp_footer'] ) ) {
	fwrite( STDERR, "Frontend hooks were registered on a protected screen.\n" );
	exit( 1 );
}

echo sprintf( "PASS: %s request boundary.\n", $test_mode );
