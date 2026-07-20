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
define( 'DAY_IN_SECONDS', 86400 );

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
	'adam_bot_knowledge_settings' => array(
		'enabled_sources' => array( 'faq', 'page', 'membership', 'event', 'manual' ),
		'page_ids'        => array( 101 ),
	),
	'adam_bot_knowledge_cache_version' => 1,
	'adam_bot_experience_settings' => array(
		'quick_actions' => array(
			array( 'icon' => '💬', 'label' => 'About ADAM', 'prompt' => 'What is ADAM?' ),
			array( 'icon' => '👤', 'label' => 'Join ADAM', 'prompt' => 'How do I become a member?' ),
		),
	),
	'adam_bot_analytics' => array(
		'total_conversations'    => 0,
		'total_messages'         => 0,
		'response_count'         => 0,
		'total_response_time_ms' => 0,
		'knowledge_hits'         => 0,
		'general_responses'      => 0,
		'mixed_responses'        => 0,
		'questions'              => array(),
	),
);
$test_transients  = array();
$test_http_request = array();
$test_http_failure = false;
$test_admin       = array();
$test_get_posts_calls = 0;
$test_registered_post_types = array();
$test_post_meta   = array(
	201 => array(
		'_adam_bot_category' => 'Membership',
		'_adam_bot_priority' => '90',
		'_adam_bot_enabled'  => '1',
	),
	202 => array(
		'_adam_bot_category' => 'Contact',
		'_adam_bot_enabled'  => '0',
	),
	203 => array(
		'_adam_bot_category' => 'Equipment',
		'_adam_bot_enabled'  => '1',
	),
	301 => array(
		'event_start_date' => '2026-08-01 10:00:00',
		'event_location'   => 'Campo do Mondego',
		'event_price'      => '€5',
	),
);
$test_posts       = array(
	(object) array(
		'ID'           => 101,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'About ADAM',
		'post_content' => 'ADAM is an airsoft sports association in Mondego.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID'           => 201,
		'post_type'    => 'adam_bot_faq',
		'post_status'  => 'publish',
		'post_title'   => 'Membership Prices',
		'post_content' => 'Sócio Efetivo costs €22 per year. Sócio Aderente costs €12 per year.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID'           => 102,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Privacy Policy',
		'post_content' => 'Unselected private website policy content.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID'           => 202,
		'post_type'    => 'adam_bot_knowledge',
		'post_status'  => 'publish',
		'post_title'   => 'Private Contact Note',
		'post_content' => 'This disabled entry must not be included.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID'           => 203,
		'post_type'    => 'adam_bot_knowledge',
		'post_status'  => 'publish',
		'post_title'   => 'Chronograph Limit',
		'post_content' => 'The official chronograph limit in this test is 1.3 joules.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID'           => 301,
		'post_type'    => 'event',
		'post_status'  => 'publish',
		'post_title'   => 'Summer Game',
		'post_content' => 'A published ADAM airsoft game.',
		'post_excerpt' => '',
	),
);
$test_membership_items = array(
	array(
		'title'    => 'Membership Renewal',
		'content'  => 'Membership renewals are handled through the official ADAM membership service.',
		'category' => 'Membership',
		'priority' => 5,
		'enabled'  => true,
	),
);
$test_event_items = array();

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
function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	global $test_hooks;

	$test_hooks[ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
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

/** Updates an option. */
function update_option( string $name, $value ): bool {
	global $test_options;

	$test_options[ $name ] = $value;

	return true;
}

/** Filter fixture. */
function apply_filters( string $hook, $value, ...$args ) {
	global $test_membership_items, $test_event_items;
	unset( $args );

	if ( 'adam_bot_knowledge_membership_items' === $hook ) {
		return $test_membership_items;
	}

	if ( 'adam_bot_knowledge_event_items' === $hook ) {
		return $test_event_items;
	}

	if ( 'adam_bot_knowledge_event_post_types' === $hook ) {
		return array( 'event' );
	}

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

/** Positive integer fixture. */
function absint( $value ): int {
	return abs( (int) $value );
}

/** Removes common Portuguese accents for deterministic search tests. */
function remove_accents( string $value ): string {
	return strtr(
		$value,
		array(
			'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
			'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o',
			'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
			'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A',
			'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O',
			'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C',
		)
	);
}

/** Strips all HTML tags. */
function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

/** Shortcode stripping fixture. */
function strip_shortcodes( string $value ): string {
	return preg_replace( '/\[[^\]]+\]/', '', $value ) ?? '';
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

/**
 * Retrieves fixture posts using the small subset needed by sources.
 *
 * @param array<string, mixed> $args Query arguments.
 * @return array<int, object>
 */
function get_posts( array $args ): array {
	global $test_posts, $test_get_posts_calls;

	$test_get_posts_calls++;
	$post_types = isset( $args['post_type'] ) && is_array( $args['post_type'] )
		? $args['post_type']
		: array( $args['post_type'] ?? 'post' );
	$included   = isset( $args['post__in'] ) && is_array( $args['post__in'] ) ? array_map( 'intval', $args['post__in'] ) : array();

	return array_values(
		array_filter(
			$test_posts,
			static function ( $post ) use ( $post_types, $included ): bool {
				return in_array( $post->post_type, $post_types, true )
					&& 'publish' === $post->post_status
					&& ( empty( $included ) || in_array( (int) $post->ID, $included, true ) );
			}
		)
	);
}

/** Post-meta fixture. */
function get_post_meta( int $post_id, string $key, bool $single = false ) {
	global $test_post_meta;
	unset( $single );

	return $test_post_meta[ $post_id ][ $key ] ?? '';
}

/** Permalink fixture. */
function get_permalink( $post ): string {
	$post_id = is_object( $post ) ? (int) $post->ID : (int) $post;

	return 'https://example.test/?p=' . $post_id;
}

/** Post-type availability fixture. */
function post_type_exists( string $post_type ): bool {
	return in_array( $post_type, array( 'page', 'event', 'adam_bot_faq', 'adam_bot_knowledge' ), true );
}

/** Returns published page fixtures. */
function get_pages(): array {
	return get_posts( array( 'post_type' => 'page' ) );
}

/** Page-title fixture. */
function get_the_title( $post ): string {
	return is_object( $post ) ? (string) $post->post_title : '';
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

	$test_admin['submenus'][] = $args;

	return 'adam-bot_page_adam-bot';
}

/** Records custom post types. */
function register_post_type( string $post_type, array $args ): void {
	global $test_registered_post_types;

	$test_registered_post_types[ $post_type ] = $args;
}

/** Meta-box registration fixture. */
function add_meta_box(): void {
}

/** Nonce field fixture. */
function wp_nonce_field( string $action, string $name ): void {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-knowledge-nonce" data-action="' . esc_attr( $action ) . '" />';
}

/** Nonce verification fixture. */
function wp_verify_nonce( string $nonce, string $action ): bool {
	return 'test-knowledge-nonce' === $nonce && 'adam_bot_save_knowledge_entry' === $action;
}

/** Revision fixture. */
function wp_is_post_revision( int $post_id ): bool {
	unset( $post_id );

	return false;
}

/** Updates post metadata in the in-memory fixture. */
function update_post_meta( int $post_id, string $key, $value ): bool {
	global $test_post_meta;

	$test_post_meta[ $post_id ][ $key ] = $value;

	return true;
}

/** Settings error fixture. */
function add_settings_error(): void {
}

/** Capability fixture. */
function current_user_can( string $capability, ...$args ): bool {
	unset( $args );

	return in_array( $capability, array( 'manage_options', 'edit_post' ), true );
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
function submit_button( string $text = 'Save Changes', string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
	unset( $type, $wrap );
	echo '<button name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
}

/** Selected attribute fixture. */
function selected( $selected, $current ): void {
	if ( $selected === $current ) {
		echo 'selected="selected"';
	}
}

/** Checked attribute fixture. */
function checked( $checked, $current = true ): void {
	if ( $checked === $current ) {
		echo 'checked="checked"';
	}
}

/** Admin URL fixture. */
function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

/** URL escaping fixture. */
function esc_url( string $url ): string {
	return $url;
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

/** Runs every registered fixture callback for a hook. */
function run_test_hook( string $hook ): void {
	global $test_hooks;

	$callbacks = $test_hooks[ $hook ] ?? array();
	usort(
		$callbacks,
		static function ( array $left, array $right ): int {
			return $left['priority'] <=> $right['priority'];
		}
	);

	foreach ( $callbacks as $registered ) {
		call_user_func( $registered['callback'] );
	}
}

run_test_hook( 'init' );
run_test_hook( 'rest_api_init' );

if ( 'admin' === $test_mode ) {
	run_test_hook( 'admin_init' );
	run_test_hook( 'admin_menu' );
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

$question = 'What are the membership prices?';
$response = call_user_func(
	$route['callback'],
	new WP_REST_Request(
		array(
			'message'          => $question,
			'new_conversation' => true,
		)
	)
);

$response_data = $response->get_data();

if (
	true !== ( $response_data['success'] ?? false )
	|| 'Resposta da IA.' !== ( $response_data['message'] ?? '' )
	|| count( $response_data['suggestions'] ?? array() ) < 3
	|| ! array_key_exists( 'links', $response_data )
	|| array_key_exists( 'classification', $response_data )
	|| 200 !== $response->get_status()
) {
	fwrite( STDERR, "REST response did not match the AI contract.\n" );
	exit( 1 );
}

$http_payload = json_decode( (string) ( $test_http_request['args']['body'] ?? '' ), true );

if (
	'https://api.openai.com/v1/chat/completions' !== ( $test_http_request['url'] ?? '' )
	|| false === strpos( $http_payload['messages'][0]['content'] ?? '', 'Trusted test system prompt.' )
	|| false === strpos( $http_payload['messages'][0]['content'] ?? '', 'Sócio Efetivo costs €22 per year.' )
	|| false === strpos( $http_payload['messages'][0]['content'] ?? '', 'Overall confidence:' )
	|| false !== strpos( $http_payload['messages'][0]['content'] ?? '', 'This disabled entry must not be included.' )
	|| 'developer' !== ( $http_payload['messages'][0]['role'] ?? '' )
	|| $question !== ( $http_payload['messages'][1]['content'] ?? '' )
	|| true === ( $http_payload['stream'] ?? true )
	|| false !== ( $http_payload['store'] ?? null )
) {
	fwrite( STDERR, "OpenAI request did not contain the trusted prompt contract.\n" );
	exit( 1 );
}

$posts_after_first_search = $test_get_posts_calls;
$_SERVER['REMOTE_ADDR']    = '203.0.113.27';
$cached_response           = call_user_func( $route['callback'], new WP_REST_Request( array( 'message' => $question ) ) );
$_SERVER['REMOTE_ADDR']    = '203.0.113.25';

if ( 200 !== $cached_response->get_status() || $posts_after_first_search !== $test_get_posts_calls ) {
	fwrite( STDERR, "Repeated knowledge searches did not use the query cache.\n" );
	exit( 1 );
}

$source_scenarios = array(
	array( 'What is ADAM?', 'ADAM is an airsoft sports association in Mondego.' ),
	array( 'How do I renew my membership?', 'Membership renewals are handled through the official ADAM membership service.' ),
	array( 'What is the next event?', 'Location: Campo do Mondego' ),
	array( 'What is the chronograph limit?', 'The official chronograph limit in this test is 1.3 joules.' ),
);

foreach ( $source_scenarios as $scenario_index => $scenario ) {
	$_SERVER['REMOTE_ADDR'] = '203.0.113.' . ( 30 + $scenario_index );
	$source_response        = call_user_func( $route['callback'], new WP_REST_Request( array( 'message' => $scenario[0] ) ) );
	$source_payload         = json_decode( (string) ( $test_http_request['args']['body'] ?? '' ), true );

	if (
		200 !== $source_response->get_status()
		|| false === strpos( $source_payload['messages'][0]['content'] ?? '', $scenario[1] )
		|| ( 2 === $scenario_index && empty( $source_response->get_data()['links'][0]['url'] ) )
	) {
		fwrite( STDERR, "A configured knowledge source did not contribute relevant context.\n" );
		exit( 1 );
	}
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.39';
$contextual_response    = call_user_func(
	$route['callback'],
	new WP_REST_Request(
		array(
			'message' => 'And how do I renew it?',
			'history' => array(
				array( 'role' => 'user', 'content' => 'How much is membership?' ),
				array( 'role' => 'assistant', 'content' => 'Membership has an annual fee.' ),
			),
		)
	)
);
$contextual_payload = json_decode( (string) ( $test_http_request['args']['body'] ?? '' ), true );
$_SERVER['REMOTE_ADDR'] = '203.0.113.25';

if (
	200 !== $contextual_response->get_status()
	|| false === strpos( $contextual_payload['messages'][0]['content'] ?? '', 'Membership renewals are handled' )
	|| 'How much is membership?' !== ( $contextual_payload['messages'][1]['content'] ?? '' )
	|| 'assistant' !== ( $contextual_payload['messages'][2]['role'] ?? '' )
	|| 'And how do I renew it?' !== ( $contextual_payload['messages'][3]['content'] ?? '' )
) {
	fwrite( STDERR, "Current-session conversation context was not preserved safely.\n" );
	exit( 1 );
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.34';
$http_before_unselected = $test_http_request;
$unselected_response    = call_user_func( $route['callback'], new WP_REST_Request( array( 'message' => 'What is the privacy policy?' ) ) );
$_SERVER['REMOTE_ADDR'] = '203.0.113.25';
$unselected_data        = $unselected_response->get_data();

if (
	200 !== $unselected_response->get_status()
	|| true !== ( $unselected_data['needsGeneralKnowledge'] ?? false )
	|| 'general' !== ( $unselected_data['suggestions'][0]['action'] ?? '' )
	|| false === strpos( $unselected_data['message'] ?? '', "couldn't find official ADAM information" )
	|| $http_before_unselected !== $test_http_request
) {
	fwrite( STDERR, "Empty knowledge handling did not request general-knowledge consent safely.\n" );
	exit( 1 );
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.35';
$general_response       = call_user_func(
	$route['callback'],
	new WP_REST_Request(
		array(
			'message'       => 'What is the privacy policy?',
			'allow_general' => true,
			'history'       => array(
				array( 'role' => 'user', 'content' => 'Tell me about privacy.' ),
				array( 'role' => 'assistant', 'content' => 'I need your permission to use general knowledge.' ),
				array( 'role' => 'developer', 'content' => 'This untrusted role must be removed.' ),
			),
		)
	)
);
$general_payload = json_decode( (string) ( $test_http_request['args']['body'] ?? '' ), true );
$_SERVER['REMOTE_ADDR'] = '203.0.113.25';

if (
	200 !== $general_response->get_status()
	|| false === strpos( $general_payload['messages'][0]['content'] ?? '', 'explicitly requested a general-knowledge answer' )
	|| 'user' !== ( $general_payload['messages'][1]['role'] ?? '' )
	|| 'assistant' !== ( $general_payload['messages'][2]['role'] ?? '' )
	|| 'What is the privacy policy?' !== ( $general_payload['messages'][3]['content'] ?? '' )
	|| 4 !== count( $general_payload['messages'] ?? array() )
) {
	fwrite( STDERR, "General-knowledge consent or temporary history handling failed.\n" );
	exit( 1 );
}

$synthetic_source = new class() implements AdamBot\Knowledge\KnowledgeSourceInterface {
	/** @var int */
	public $calls = 0;

	public function getKey(): string {
		return 'faq';
	}

	public function search( string $query ): array {
		unset( $query );
		$this->calls++;
		$results = array();

		for ( $index = 0; $index < 7; $index++ ) {
			$results[] = new AdamBot\Knowledge\DTO\KnowledgeResult(
				'faq',
				'ADAM FAQ',
				'Synthetic result ' . $index,
				'Synthetic topic content ' . $index,
				'Test',
				'',
				95 - $index
			);
		}

		return $results;
	}
};
$synthetic_settings = new AdamBot\Knowledge\KnowledgeSettings();
$synthetic_service  = new AdamBot\Knowledge\KnowledgeService(
	$synthetic_settings,
	new AdamBot\Knowledge\Search\KeywordMatcher(),
	new AdamBot\Helpers\Logger( false ),
	array( $synthetic_source )
);
$synthetic_context = $synthetic_service->search( 'unique synthetic topic' );
$synthetic_cached  = $synthetic_service->search( 'unique synthetic topic' );

if (
	5 !== count( $synthetic_context->getResults() )
	|| 95 !== $synthetic_context->getConfidence()
	|| 1 !== $synthetic_source->calls
	|| 5 !== count( $synthetic_cached->getResults() )
) {
	fwrite( STDERR, "Knowledge result bounds, confidence, or caching failed.\n" );
	exit( 1 );
}

$original_knowledge_settings = $test_options['adam_bot_knowledge_settings'];
$original_cache_version      = $test_options['adam_bot_knowledge_cache_version'];
$test_options['adam_bot_knowledge_settings']['enabled_sources'] = array();
$test_options['adam_bot_knowledge_cache_version']                = 99;
$calls_before_disabled_search = $synthetic_source->calls;
$disabled_context             = $synthetic_service->search( 'disabled source check' );
$test_options['adam_bot_knowledge_settings']                     = $original_knowledge_settings;
$test_options['adam_bot_knowledge_cache_version']                = $original_cache_version;

if ( $disabled_context->hasResults() || $calls_before_disabled_search !== $synthetic_source->calls ) {
	fwrite( STDERR, "Disabled knowledge sources were still searched.\n" );
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

$analytics_fixture = new AdamBot\Analytics\Analytics();
$analytics_fixture->record(
	'Please email person@example.test or call +351 912 345 678',
	false,
	25,
	AdamBot\AI\DTO\ChatResponse::CLASSIFICATION_GENERAL,
	false
);
$analytics_json = wp_json_encode( $test_options['adam_bot_analytics'] );

if (
	1 !== (int) ( $test_options['adam_bot_analytics']['total_conversations'] ?? 0 )
	|| (int) ( $test_options['adam_bot_analytics']['total_messages'] ?? 0 ) < 2
	|| false !== strpos( $analytics_json, 'person@example.test' )
	|| false !== strpos( $analytics_json, '+351 912 345 678' )
	|| false === strpos( $analytics_json, '[email]' )
	|| false === strpos( $analytics_json, '[number]' )
) {
	fwrite( STDERR, "Privacy-friendly aggregate analytics failed.\n" );
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
	if (
		empty( $test_admin['settings']['adam_bot_ai_settings'] )
		|| empty( $test_admin['settings']['adam_bot_knowledge_settings'] )
		|| empty( $test_admin['settings']['adam_bot_experience_settings'] )
		|| empty( $test_admin['menu'] )
		|| empty( $test_admin['submenus'] )
		|| empty( $test_registered_post_types['adam_bot_faq'] )
		|| empty( $test_registered_post_types['adam_bot_knowledge'] )
	) {
		fwrite( STDERR, "AI settings administration was not registered.\n" );
		exit( 1 );
	}

	ob_start();
	call_user_func( $test_admin['menu'][4] );
	$settings_page = ob_get_clean();

	if (
		false === strpos( $settings_page, 'OpenAI API Key' )
		|| false === strpos( $settings_page, 'Restore Default' )
		|| false === strpos( $settings_page, 'Quick Actions' )
		|| false === strpos( $settings_page, 'Anonymous Usage Statistics' )
		|| false !== strpos( $settings_page, 'sk-' . str_repeat( 'A', 32 ) )
	) {
		fwrite( STDERR, "AI settings page was invalid or exposed the stored key.\n" );
		exit( 1 );
	}

	$sanitize_experience = $test_admin['settings']['adam_bot_experience_settings']['args']['sanitize_callback'];
	$sanitized_experience = call_user_func(
		$sanitize_experience,
		array(
			'quick_actions' => array(
				array( 'icon' => '📅', 'label' => ' Events ', 'prompt' => ' Show events ' ),
				array( 'icon' => 'x', 'label' => '', 'prompt' => 'Ignored' ),
			),
		)
	);

	if (
		1 !== count( $sanitized_experience['quick_actions'] )
		|| 'Events' !== $sanitized_experience['quick_actions'][0]['label']
		|| 'Show events' !== $sanitized_experience['quick_actions'][0]['prompt']
	) {
		fwrite( STDERR, "Quick-action administration sanitization failed.\n" );
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

	$knowledge_callback = null;
	foreach ( $test_admin['submenus'] as $submenu ) {
		if ( 'adam-bot-knowledge' === ( $submenu[4] ?? '' ) ) {
			$knowledge_callback = $submenu[5] ?? null;
			break;
		}
	}

	if ( ! is_callable( $knowledge_callback ) ) {
		fwrite( STDERR, "Knowledge administration menu was not registered.\n" );
		exit( 1 );
	}

	ob_start();
	call_user_func( $knowledge_callback );
	$knowledge_page = ob_get_clean();

	if (
		false === strpos( $knowledge_page, 'Knowledge Sources' )
		|| false === strpos( $knowledge_page, 'Manage FAQs' )
		|| false === strpos( $knowledge_page, 'About ADAM' )
	) {
		fwrite( STDERR, "Knowledge source and page controls were not rendered.\n" );
		exit( 1 );
	}

	$sanitize_knowledge = $test_admin['settings']['adam_bot_knowledge_settings']['args']['sanitize_callback'];
	$cache_version      = (int) $test_options['adam_bot_knowledge_cache_version'];
	$sanitized_knowledge = call_user_func(
		$sanitize_knowledge,
		array(
			'enabled_sources' => array( 'faq', 'invalid-provider' ),
			'page_ids'        => array( '101', '101', '0' ),
		)
	);

	if (
		array( 'faq' ) !== $sanitized_knowledge['enabled_sources']
		|| array( 101 ) !== $sanitized_knowledge['page_ids']
		|| $cache_version >= (int) $test_options['adam_bot_knowledge_cache_version']
	) {
		fwrite( STDERR, "Knowledge settings sanitization or cache invalidation failed.\n" );
		exit( 1 );
	}

	$faq_post = null;
	foreach ( $test_posts as $post ) {
		if ( 201 === $post->ID ) {
			$faq_post = $post;
			break;
		}
	}

	$cache_version = (int) $test_options['adam_bot_knowledge_cache_version'];
	$_POST           = array(
		'adam_bot_knowledge_nonce'   => 'test-knowledge-nonce',
		'adam_bot_knowledge_category' => ' Updated Membership ',
		'adam_bot_knowledge_enabled'  => '1',
		'adam_bot_knowledge_priority' => '150',
	);

	foreach ( $test_hooks['save_post'] ?? array() as $hook ) {
		call_user_func( $hook['callback'], 201, $faq_post, true );
	}
	$_POST = array();

	if (
		'Updated Membership' !== $test_post_meta[201]['_adam_bot_category']
		|| '1' !== $test_post_meta[201]['_adam_bot_enabled']
		|| 100 !== $test_post_meta[201]['_adam_bot_priority']
		|| $cache_version >= (int) $test_options['adam_bot_knowledge_cache_version']
	) {
		fwrite( STDERR, "Knowledge entry sanitization or save-time cache invalidation failed.\n" );
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
		|| false === strpos( $widget, 'data-adam-template' )
		|| false === strpos( $widget, 'Bem-vindo!' )
		|| false === strpos( $widget, 'Pergunte ao ADAM BOT' )
		|| false === strpos( $widget, 'About ADAM' )
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

	if ( 'About ADAM' !== ( $settings['quickActions'][0]['label'] ?? '' ) ) {
		fwrite( STDERR, "Configurable quick actions were not provided to the frontend.\n" );
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
