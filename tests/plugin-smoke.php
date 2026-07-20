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
$test_options    = array(
	'adam_bot_ai_settings' => array(
		'provider'       => 'openai',
		'openai_api_key' => 'sk-' . str_repeat( 'A', 32 ),
		'model'          => 'gpt-5.6-terra',
		'temperature'    => 0.3,
		'max_tokens'     => 500,
		'timeout'        => 20,
		'system_prompt'  => 'Trusted test system prompt.',
	),
);
$test_transients  = array();
$test_http_request = array();
$test_http_failure = false;
$test_admin       = array();

$_SERVER['REMOTE_ADDR'] = '203.0.113.25';

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
	/** @var array<string, mixed> */
	private $params;

	/**
	 * @param array<string, mixed> $params Request parameters.
	 */
	public function __construct( array $params = array() ) {
		$this->params = $params;
	}

	/**
	 * @param string $key Parameter name.
	 * @return mixed
	 */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}
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

	/** @var int */
	private $status;

	/**
	 * Creates the response.
	 *
	 * @param array<string, mixed> $data Response payload.
	 * @param int                  $status HTTP status.
	 */
	public function __construct( array $data, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	/**
	 * Gets the response payload.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		return $this->data;
	}

	/** @return int */
	public function get_status(): int {
		return $this->status;
	}
}

/** Minimal WordPress error fixture. */
final class WP_Error {
	/** @var string */
	private $message;

	public function __construct( string $message ) {
		$this->message = $message;
	}

	public function get_error_message(): string {
		return $this->message;
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
 * Returns a stored option.
 *
 * @param string $name Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( string $name, $default = false ) {
	global $test_options;

	return $test_options[ $name ] ?? $default;
}

/**
 * Adds an option when it does not exist.
 *
 * @param string $name Option name.
 * @param mixed  $value Option value.
 * @return bool
 */
function add_option( string $name, $value ): bool {
	global $test_options;

	if ( array_key_exists( $name, $test_options ) ) {
		return false;
	}

	$test_options[ $name ] = $value;

	return true;
}

/** Filter fixture. */
function apply_filters( string $hook, $value ) {
	unset( $hook );

	return $value;
}

/** Key sanitizer fixture. */
function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? '';
}

/** Text sanitizer fixture. */
function sanitize_text_field( string $value ): string {
	return trim( strip_tags( $value ) );
}

/** Textarea sanitizer fixture. */
function sanitize_textarea_field( string $value ): string {
	return trim( strip_tags( $value ) );
}

/** Unslash fixture. */
function wp_unslash( string $value ): string {
	return stripslashes( $value );
}

/** Salt fixture. */
function wp_salt( string $scheme = 'auth' ): string {
	return 'test-' . $scheme . '-salt';
}

/** Gets a transient fixture. */
function get_transient( string $key ) {
	global $test_transients;

	return $test_transients[ $key ] ?? false;
}

/** Sets a transient fixture. */
function set_transient( string $key, $value, int $expiration ): bool {
	global $test_transients;
	unset( $expiration );

	$test_transients[ $key ] = $value;

	return true;
}

/** JSON encoding fixture. */
function wp_json_encode( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/**
 * OpenAI HTTP fixture.
 *
 * @param string               $url Request URL.
 * @param array<string, mixed> $args Request arguments.
 * @return array<string, mixed>
 */
function wp_remote_post( string $url, array $args ): array {
	global $test_http_request, $test_http_failure;

	$test_http_request = compact( 'url', 'args' );

	if ( $test_http_failure ) {
		return array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode(
				array(
					'error' => array(
						'type'    => 'authentication_error',
						'message' => 'Rejected secret sk-THIS_MUST_NEVER_BE_PUBLIC.',
					),
				)
			),
		);
	}

	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'choices' => array(
					array(
						'message' => array( 'content' => 'Resposta da IA.' ),
					),
				),
				'usage'   => array(
					'prompt_tokens'     => 10,
					'completion_tokens' => 4,
					'total_tokens'      => 14,
				),
			)
		),
	);
}

/** WordPress error predicate fixture. */
function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

/** HTTP status fixture. */
function wp_remote_retrieve_response_code( array $response ): int {
	return (int) ( $response['response']['code'] ?? 0 );
}

/** HTTP body fixture. */
function wp_remote_retrieve_body( array $response ): string {
	return (string) ( $response['body'] ?? '' );
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

/** Escaped translation fixture. */
function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( __( $text, $domain ) );
}

/** Textarea escaping fixture. */
function esc_textarea( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * Records the settings registration.
 *
 * @param string               $group Settings group.
 * @param string               $option Option name.
 * @param array<string, mixed> $args Registration arguments.
 * @return bool
 */
function register_setting( string $group, string $option, array $args ): bool {
	global $test_admin;

	$test_admin['settings'][ $option ] = compact( 'group', 'args' );

	return true;
}

/** Records the top-level menu. */
function add_menu_page( ...$args ): string {
	global $test_admin;

	$test_admin['menu'] = $args;

	return 'toplevel_page_adam-bot';
}

/** Records the submenu. */
function add_submenu_page( ...$args ): string {
	global $test_admin;

	$test_admin['submenu'] = $args;

	return 'adam-bot_page_adam-bot';
}

/** Settings error fixture. */
function add_settings_error(): void {
}

/** Capability fixture. */
function current_user_can( string $capability ): bool {
	return 'manage_options' === $capability;
}

/** Fatal admin fixture. */
function wp_die( string $message ): void {
	throw new RuntimeException( $message );
}

/** Settings errors renderer fixture. */
function settings_errors(): void {
}

/** Settings fields renderer fixture. */
function settings_fields( string $group ): void {
	echo '<input type="hidden" value="' . esc_attr( $group ) . '" />';
}

/** Submit button fixture. */
function submit_button( string $text, string $type, string $name, bool $wrap ): void {
	unset( $type, $wrap );
	echo '<button name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
}

/** Selected attribute fixture. */
function selected( $selected, $current ): void {
	if ( $selected === $current ) {
		echo 'selected="selected"';
	}
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

if ( 'admin' === $test_mode ) {
	call_user_func( $test_hooks['admin_init'][0]['callback'] );
	call_user_func( $test_hooks['admin_menu'][0]['callback'] );
}

if ( 'adam-bot' !== ( $test_textdomain['domain'] ?? '' ) ) {
	fwrite( STDERR, "Textdomain was not loaded.\n" );
	exit( 1 );
}

$route = $test_routes['adam-bot/v1/chat'] ?? array();

if ( WP_REST_Server::CREATABLE !== ( $route['methods'] ?? '' ) || empty( $route['callback'] ) ) {
	fwrite( STDERR, "REST route was not registered correctly.\n" );
	exit( 1 );
}

$message_args = $route['args']['message'] ?? array();

if ( empty( $message_args['required'] ) || empty( $message_args['validate_callback'] ) ) {
	fwrite( STDERR, "REST message validation was not registered.\n" );
	exit( 1 );
}

$response = call_user_func( $route['callback'], new WP_REST_Request( array( 'message' => 'Olá?' ) ) );

if ( array( 'success' => true, 'message' => 'Resposta da IA.' ) !== $response->get_data() || 200 !== $response->get_status() ) {
	fwrite( STDERR, "REST response did not match the AI contract.\n" );
	exit( 1 );
}

$http_payload = json_decode( (string) ( $test_http_request['args']['body'] ?? '' ), true );

if (
	'https://api.openai.com/v1/chat/completions' !== ( $test_http_request['url'] ?? '' )
	|| 'Trusted test system prompt.' !== ( $http_payload['messages'][0]['content'] ?? '' )
	|| 'developer' !== ( $http_payload['messages'][0]['role'] ?? '' )
	|| 'Olá?' !== ( $http_payload['messages'][1]['content'] ?? '' )
	|| true === ( $http_payload['stream'] ?? true )
) {
	fwrite( STDERR, "OpenAI request did not contain the trusted prompt contract.\n" );
	exit( 1 );
}

if ( false !== strpos( wp_json_encode( $response->get_data() ), 'sk-' ) ) {
	fwrite( STDERR, "REST response exposed an API key.\n" );
	exit( 1 );
}

$oversized = call_user_func(
	$route['callback'],
	new WP_REST_Request( array( 'message' => str_repeat( 'x', 4001 ) ) )
);

if ( 400 !== $oversized->get_status() || true === ( $oversized->get_data()['success'] ?? true ) ) {
	fwrite( STDERR, "Oversized prompts were not rejected.\n" );
	exit( 1 );
}

if ( 'public' === $test_mode ) {
	for ( $request_number = 1; $request_number < 20; $request_number++ ) {
		$allowed = call_user_func(
			$route['callback'],
			new WP_REST_Request( array( 'message' => 'Rate-limit test.' ) )
		);

		if ( 200 !== $allowed->get_status() ) {
			fwrite( STDERR, "The rate limiter blocked a request before the configured limit.\n" );
			exit( 1 );
		}
	}

	$limited = call_user_func(
		$route['callback'],
		new WP_REST_Request( array( 'message' => 'One request too many.' ) )
	);

	if ( 429 !== $limited->get_status() || false === strpos( $limited->get_data()['message'] ?? '', 'Too many requests' ) ) {
		fwrite( STDERR, "The 20-request rate limit was not enforced.\n" );
		exit( 1 );
	}

	$_SERVER['REMOTE_ADDR'] = '203.0.113.26';
	$test_http_failure      = true;
	$failed                 = call_user_func(
		$route['callback'],
		new WP_REST_Request( array( 'message' => 'Provider failure test.' ) )
	);
	$test_http_failure      = false;

	$failed_payload = wp_json_encode( $failed->get_data() );
	if (
		503 !== $failed->get_status()
		|| true === ( $failed->get_data()['success'] ?? true )
		|| false !== strpos( $failed_payload, 'authentication_error' )
		|| false !== strpos( $failed_payload, 'sk-' )
	) {
		fwrite( STDERR, "Provider failures were not converted to a safe public response.\n" );
		exit( 1 );
	}
}

if ( 'admin' === $test_mode ) {
	if ( empty( $test_admin['settings']['adam_bot_ai_settings'] ) || empty( $test_admin['menu'] ) || empty( $test_admin['submenu'] ) ) {
		fwrite( STDERR, "AI settings administration was not registered.\n" );
		exit( 1 );
	}

	ob_start();
	call_user_func( $test_admin['menu'][4] );
	$settings_page = ob_get_clean();

	if (
		false === strpos( $settings_page, 'OpenAI API Key' )
		|| false === strpos( $settings_page, 'Restore Default' )
		|| false !== strpos( $settings_page, 'sk-' . str_repeat( 'A', 32 ) )
	) {
		fwrite( STDERR, "AI settings page was invalid or exposed the stored key.\n" );
		exit( 1 );
	}

	$sanitize_settings = $test_admin['settings']['adam_bot_ai_settings']['args']['sanitize_callback'];
	$_POST['adam_bot_restore_prompt'] = '1';
	$restored = call_user_func(
		$sanitize_settings,
		array(
			'provider'       => 'ollama',
			'openai_api_key' => '',
			'model'          => 'gpt-5.6-terra',
			'temperature'    => '0.5',
			'max_tokens'     => '700',
			'system_prompt'  => 'Untrusted replacement.',
		)
	);
	unset( $_POST['adam_bot_restore_prompt'] );

	if (
		'openai' !== $restored['provider']
		|| AdamBot\AI\Settings\AISettings::DEFAULT_SYSTEM_PROMPT !== $restored['system_prompt']
		|| $test_options['adam_bot_ai_settings']['openai_api_key'] !== $restored['openai_api_key']
	) {
		fwrite( STDERR, "Settings sanitization or default-prompt restoration failed.\n" );
		exit( 1 );
	}
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
