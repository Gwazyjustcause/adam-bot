<?php
/**
 * ADAM BOT lifecycle and deterministic knowledge-engine smoke test.
 *
 * Run with: php tests/plugin-smoke.php public|admin|login
 *
 * @package AdamBot
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'WP_DEBUG', false );
define( 'SCRIPT_DEBUG', true );
define( 'DAY_IN_SECONDS', 86400 );

$test_mode                  = $argv[1] ?? 'public';
$test_is_admin              = 'admin' === $test_mode;
$test_is_login              = 'login' === $test_mode;
$test_hooks                 = array();
$test_routes                = array();
$test_options               = array(
	'adam_bot_knowledge_settings' => array(
		'enabled_sources' => array( 'faq', 'page', 'membership', 'event', 'manual', 'custom' ),
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
		'high_confidence'        => 0,
		'medium_confidence'      => 0,
		'low_confidence'         => 0,
		'no_confidence'          => 0,
		'questions'              => array(),
	),
);
$test_transients            = array();
$test_posts_calls           = 0;
$test_http_calls            = 0;
$test_assets                = array();
$test_admin                 = array( 'settings' => array(), 'menus' => array(), 'submenus' => array() );
$test_post_types            = array();
$test_activation_callbacks  = array();
$test_post_meta             = array(
	201 => array(
		'_adam_bot_category' => 'Membership',
		'_adam_bot_priority' => '90',
		'_adam_bot_enabled'  => '1',
	),
	203 => array(
		'_adam_bot_category' => 'Rules',
		'_adam_bot_enabled'  => '1',
	),
	301 => array(
		'event_start_date' => '2026-08-01 10:00:00',
		'event_location'   => 'Campo do Mondego',
		'event_price'      => '€5',
	),
);
$test_posts                 = array(
	(object) array(
		'ID' => 101, 'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'About ADAM',
		'post_content' => 'ADAM is an airsoft sports association in Mondego.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID' => 201, 'post_type' => 'adam_bot_faq', 'post_status' => 'publish',
		'post_title' => 'Membership Prices',
		'post_content' => 'Sócio Efetivo costs €22 per year. Sócio Aderente costs €12 per year.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID' => 203, 'post_type' => 'adam_bot_knowledge', 'post_status' => 'publish',
		'post_title' => 'Chronograph Rules',
		'post_content' => 'The official chronograph limit is 1.3 joules. Eye protection is mandatory.',
		'post_excerpt' => '',
	),
	(object) array(
		'ID' => 301, 'post_type' => 'event', 'post_status' => 'publish',
		'post_title' => 'Summer Game',
		'post_content' => 'A published ADAM airsoft game.',
		'post_excerpt' => '',
	),
);
$test_membership_items      = array(
	array(
		'title' => 'Membership Renewal',
		'content' => 'Membership renewals are completed through the official ADAM renewal area.',
		'category' => 'Membership',
		'url' => 'https://example.test/renew-membership',
		'priority' => 10,
		'enabled' => true,
	),
);
$test_event_items           = array();

$_SERVER['REMOTE_ADDR'] = '203.0.113.25';

final class WP_REST_Server {
	public const CREATABLE = 'POST';
}

final class WP_REST_Request {
	/** @var array<string, mixed> */
	private $params;

	/** @param array<string, mixed> $params Request parameters. */
	public function __construct( array $params = array() ) {
		$this->params = $params;
	}

	/** @return mixed */
	public function get_param( string $key ) {
		return $this->params[ $key ] ?? null;
	}
}

final class WP_REST_Response {
	/** @var mixed */
	private $data;
	/** @var int */
	private $status;

	/** @param mixed $data Response payload. */
	public function __construct( $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	/** @return mixed */
	public function get_data() {
		return $this->data;
	}

	public function get_status(): int {
		return $this->status;
	}
}

/** Fails the current boundary with a useful message. */
function test_assert( bool $condition, string $message ): void {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, 'FAIL: ' . $message . "\n" );
	exit( 1 );
}

/** Runs callbacks registered for a WordPress hook. */
function run_test_hook( string $hook ): void {
	global $test_hooks;
	$callbacks = $test_hooks[ $hook ] ?? array();
	usort( $callbacks, static function ( array $left, array $right ): int { return $left['priority'] <=> $right['priority']; } );

	foreach ( $callbacks as $registered ) {
		call_user_func( $registered['callback'] );
	}
}

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	global $test_hooks;
	$test_hooks[ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
	return true;
}

function register_activation_hook( string $file, $callback ): void {
	global $test_activation_callbacks;
	unset( $file );
	$test_activation_callbacks[] = $callback;
}

function plugin_dir_path( string $file ): string { return dirname( $file ) . DIRECTORY_SEPARATOR; }
function plugin_dir_url( string $file ): string { unset( $file ); return 'https://example.test/wp-content/plugins/adam-bot/'; }
function plugin_basename( string $file ): string { return basename( $file ); }
function load_plugin_textdomain( string $domain, bool $deprecated = false, string $path = '' ): bool { unset( $domain, $deprecated, $path ); return true; }
function is_admin(): bool { global $test_is_admin; return $test_is_admin; }
function is_login(): bool { global $test_is_login; return $test_is_login; }

function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
function esc_html__( string $text, string $domain = '' ): string { return esc_html( __( $text, $domain ) ); }
function esc_attr__( string $text, string $domain = '' ): string { return esc_attr( __( $text, $domain ) ); }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( string $text ): string { return esc_html( $text ); }
function esc_url( string $url ): string { return $url; }
function esc_url_raw( string $url ): string { return $url; }
function esc_textarea( string $text ): string { return esc_html( $text ); }
function esc_html_e( string $text, string $domain = '' ): void { echo esc_html__( $text, $domain ); }
function esc_attr_e( string $text, string $domain = '' ): void { echo esc_attr__( $text, $domain ); }

function get_option( string $name, $default = false ) { global $test_options; return $test_options[ $name ] ?? $default; }
function add_option( string $name, $value, string $deprecated = '', string $autoload = 'yes' ): bool { global $test_options; unset( $deprecated, $autoload ); if ( array_key_exists( $name, $test_options ) ) { return false; } $test_options[ $name ] = $value; return true; }
function update_option( string $name, $value, bool $autoload = true ): bool { global $test_options; unset( $autoload ); $test_options[ $name ] = $value; return true; }

function apply_filters( string $hook, $value, ...$args ) {
	global $test_membership_items, $test_event_items;
	unset( $args );
	if ( 'adam_bot_knowledge_membership_items' === $hook ) { return $test_membership_items; }
	if ( 'adam_bot_knowledge_event_items' === $hook ) { return $test_event_items; }
	if ( 'adam_bot_knowledge_event_post_types' === $hook ) { return array( 'event' ); }
	if ( 'adam_bot_knowledge_provider_registry' === $hook ) { $value['custom'] = 'Custom provider'; }
	return $value;
}

function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? ''; }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( string $value ): string { return trim( strip_tags( $value ) ); }
function absint( $value ): int { return abs( (int) $value ); }
function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
function strip_shortcodes( string $value ): string { return preg_replace( '/\[[^\]]+\]/', '', $value ) ?? ''; }
function wp_unslash( string $value ): string { return stripslashes( $value ); }
function remove_accents( string $value ): string {
	return strtr( $value, array( 'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C' ) );
}
function wp_json_encode( $value ) { return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
function wp_parse_url( string $url, int $component = -1 ) { return -1 === $component ? parse_url( $url ) : parse_url( $url, $component ); }
function wp_salt( string $scheme = 'auth' ): string { return 'test-' . $scheme . '-salt'; }

function get_transient( string $key ) { global $test_transients; return $test_transients[ $key ] ?? false; }
function set_transient( string $key, $value, int $expiration ): bool { global $test_transients; unset( $expiration ); $test_transients[ $key ] = $value; return true; }

function get_posts( array $args ): array {
	global $test_posts, $test_posts_calls;
	$test_posts_calls++;
	$types    = is_array( $args['post_type'] ?? null ) ? $args['post_type'] : array( $args['post_type'] ?? 'post' );
	$included = is_array( $args['post__in'] ?? null ) ? array_map( 'intval', $args['post__in'] ) : array();
	return array_values( array_filter( $test_posts, static function ( $post ) use ( $types, $included ): bool {
		return in_array( $post->post_type, $types, true )
			&& 'publish' === $post->post_status
			&& ( empty( $included ) || in_array( (int) $post->ID, $included, true ) );
	} ) );
}
function get_post_meta( int $post_id, string $key, bool $single = false ) { global $test_post_meta; unset( $single ); return $test_post_meta[ $post_id ][ $key ] ?? ''; }
function update_post_meta( int $post_id, string $key, $value ): bool { global $test_post_meta; $test_post_meta[ $post_id ][ $key ] = $value; return true; }
function get_permalink( $post ): string { $id = is_object( $post ) ? (int) $post->ID : (int) $post; return 'https://example.test/?p=' . $id; }
function post_type_exists( string $post_type ): bool { return in_array( $post_type, array( 'page', 'event', 'adam_bot_faq', 'adam_bot_knowledge' ), true ); }
function get_pages(): array { return get_posts( array( 'post_type' => 'page' ) ); }
function get_the_title( $post ): string { return is_object( $post ) ? (string) $post->post_title : ''; }

function register_rest_route( string $namespace, string $route, array $args ): bool { global $test_routes; $test_routes[ $namespace . $route ] = $args; return true; }
function rest_url( string $path = '' ): string { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
function wp_create_nonce( string $action ): string { unset( $action ); return 'test-nonce'; }

function register_post_type( string $type, array $args ): void { global $test_post_types; $test_post_types[ $type ] = $args; }
function register_setting( string $group, string $option, array $args ): void { global $test_admin; $test_admin['settings'][ $option ] = compact( 'group', 'args' ); }
function add_menu_page( string $page_title, string $menu_title, string $capability, string $slug, $callback, string $icon = '', $position = null ): string { global $test_admin; unset( $page_title, $menu_title, $capability, $icon, $position ); $test_admin['menus'][ $slug ] = $callback; return $slug; }
function add_submenu_page( string $parent, string $page_title, string $menu_title, string $capability, string $slug, $callback ): string { global $test_admin; unset( $page_title, $menu_title, $capability ); $test_admin['submenus'][ $slug ] = compact( 'parent', 'callback' ); return $slug; }
function add_meta_box( ...$args ): void { unset( $args ); }
function current_user_can( string $capability, ...$args ): bool { unset( $capability, $args ); return true; }
function wp_die( string $message ): void { throw new RuntimeException( $message ); }
function settings_errors( string $setting = '' ): void { unset( $setting ); }
function settings_fields( string $group ): void { echo '<input type="hidden" value="' . esc_attr( $group ) . '">'; }
function submit_button( string $text = 'Save Changes', string $type = 'primary', string $name = 'submit', bool $wrap = true ): void { unset( $type, $wrap ); echo '<button name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>'; }
function checked( $checked, $current = true, bool $echo = true ): string { $value = $checked == $current ? 'checked="checked"' : ''; if ( $echo ) { echo $value; } return $value; }
function selected( $selected, $current = true, bool $echo = true ): string { $value = $selected == $current ? 'selected="selected"' : ''; if ( $echo ) { echo $value; } return $value; }
function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function wp_nonce_field( string $action, string $name ): void { unset( $action ); echo '<input name="' . esc_attr( $name ) . '" value="test-nonce">'; }
function wp_verify_nonce( string $nonce, string $action ): bool { unset( $action ); return 'test-nonce' === $nonce; }
function wp_is_post_revision( int $post_id ): bool { unset( $post_id ); return false; }

function wp_register_style( string $handle, string $src, array $deps, $version ): bool { global $test_assets; $test_assets['styles'][ $handle ] = compact( 'src', 'deps', 'version' ); return true; }
function wp_register_script( string $handle, string $src, array $deps, $version, bool $footer ): bool { global $test_assets; $test_assets['scripts'][ $handle ] = compact( 'src', 'deps', 'version', 'footer' ); return true; }
function wp_localize_script( string $handle, string $object, array $data ): bool { global $test_assets; $test_assets['localized'][ $handle ] = compact( 'object', 'data' ); return true; }
function wp_enqueue_style( string $handle ): void { global $test_assets; $test_assets['enqueued_styles'][] = $handle; }
function wp_enqueue_script( string $handle ): void { global $test_assets; $test_assets['enqueued_scripts'][] = $handle; }

/** This must never be reached in Phase 7. */
function wp_remote_post( string $url, array $args ): array { global $test_http_calls; unset( $url, $args ); $test_http_calls++; throw new RuntimeException( 'External HTTP is forbidden.' ); }

require dirname( __DIR__ ) . '/adam-bot.php';

run_test_hook( 'init' );
run_test_hook( 'rest_api_init' );
if ( 'admin' === $test_mode ) {
	run_test_hook( 'admin_init' );
	run_test_hook( 'admin_menu' );
}

$route = $test_routes['adam-bot/v1/chat'] ?? array();
test_assert( WP_REST_Server::CREATABLE === ( $route['methods'] ?? '' ), 'REST chat route was not registered.' );
test_assert( isset( $route['args']['context'] ) && ! isset( $route['args']['history'], $route['args']['allow_general'] ), 'REST contract still exposes AI-era context fields.' );

/** Sends one deterministic chat request. */
function test_chat( array $params ): WP_REST_Response {
	global $route;
	return call_user_func( $route['callback'], new WP_REST_Request( $params ) );
}

$prices = test_chat( array( 'message' => 'What are the membership prices?', 'new_conversation' => true ) );
$data   = $prices->get_data();
test_assert( 200 === $prices->get_status() && true === ( $data['success'] ?? false ), 'Knowledge response failed.' );
test_assert( false !== strpos( $data['message'] ?? '', '€22' ), 'Highest-ranked membership answer was not formatted.' );
test_assert( count( $data['suggestions'] ?? array() ) >= 2, 'Contextual suggestions were not returned.' );
test_assert( 'membership' === ( $data['context']['topic'] ?? '' ), 'Membership topic was not retained.' );
test_assert( ! isset( $data['confidence'], $data['classification'] ), 'Internal confidence leaked to the frontend.' );

$calls_after_first = $test_posts_calls;
$cached            = test_chat( array( 'message' => 'What are the membership prices?' ) );
test_assert( 200 === $cached->get_status() && $calls_after_first === $test_posts_calls, 'Session knowledge cache was not used.' );

$renewal      = test_chat( array( 'message' => 'Como renovo a quota?' ) )->get_data();
$renewal_json = wp_json_encode( $renewal );
test_assert( false !== strpos( $renewal_json, 'renewal area' ), 'Synonym ranking did not find membership renewal.' );
test_assert( false !== strpos( $renewal_json, 'renew-membership' ), 'Smart navigation button was not returned.' );

$events = test_chat( array( 'message' => 'What is the next event?' ) )->get_data();
test_assert( ! empty( $events['cards'][0]['title'] ) && 'Summer Game' === $events['cards'][0]['title'], 'Event result was not formatted as a card.' );
test_assert( false !== strpos( wp_json_encode( $events['cards'][0]['meta'] ?? array() ), 'Campo do Mondego' ), 'Event metadata was not structured.' );

$rules = test_chat( array( 'message' => 'What is the chronograph limit?' ) )->get_data();
test_assert( false !== strpos( $rules['message'] ?? '', '1.3 joules' ), 'Manual knowledge provider did not contribute its answer.' );

$contextual = test_chat(
	array(
		'message' => 'Quanto custa?',
		'context' => array( 'topic' => 'membership', 'recentResultIds' => $data['context']['recentResultIds'] ?? array() ),
	)
)->get_data();
test_assert( false !== strpos( $contextual['message'] ?? '', '€22' ), 'Lightweight topic context did not resolve the follow-up.' );
test_assert( 'membership' === ( $contextual['context']['topic'] ?? '' ), 'Follow-up response lost its topic.' );

$unknown = test_chat( array( 'message' => 'Where is the quantum banana tractor?' ) )->get_data();
test_assert( false !== strpos( $unknown['message'] ?? '', "couldn't find an answer" ), 'No-confidence response was not deterministic.' );
test_assert( ! isset( $unknown['needsGeneralKnowledge'] ), 'No-confidence response still offers general AI.' );

$oversized = test_chat( array( 'message' => str_repeat( 'x', 4001 ) ) );
test_assert( 400 === $oversized->get_status(), 'Oversized questions were not rejected.' );
test_assert( 0 === $test_http_calls, 'The chat pipeline attempted an external HTTP request.' );

$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
$limiter = new AdamBot\API\RateLimiter( 2, 300 );
test_assert( $limiter->consume() && $limiter->consume() && ! $limiter->consume(), 'Public rate limiting failed.' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.25';

$matcher   = new AdamBot\Knowledge\Search\KeywordMatcher();
$formatter = new AdamBot\Knowledge\Response\ResponseFormatter( $matcher );
$medium    = new AdamBot\Knowledge\DTO\KnowledgeResult( 'faq', 'FAQ', 'Quota', 'Pode renovar a quota online.', 'Membership', 'https://example.test/quota', 45, array( 'quota' ) );
$medium_set = new AdamBot\Knowledge\DTO\SearchResultSet( array( $medium ), array( $medium ), 45, 'medium', 'membership', 'faq', array( 'quota' ), 2 );
$medium_response = $formatter->format( $medium_set, 'Como renovo a quota?' )->toPublicArray();
test_assert( 0 === strpos( $medium_response['message'], 'Encontrei uma resposta' ), 'Medium-confidence preamble is missing.' );

$rich = new AdamBot\Knowledge\DTO\KnowledgeResult(
	'faq',
	'FAQ',
	'Tipos de sócio',
	"Pode escolher uma modalidade:\n- Sócio efetivo\n- Sócio aderente\n| Tipo | Quota |\n| --- | --- |\n| Efetivo | €22 |",
	'Membership',
	'',
	70,
	array( 'socio' )
);
$rich_set = new AdamBot\Knowledge\DTO\SearchResultSet( array( $rich ), array(), 70, 'high', 'membership', 'faq', array( 'socio' ), 1 );
$rich_message = $formatter->format( $rich_set, 'Que tipos de sócio existem?' )->toPublicArray()['message'];
test_assert( false !== strpos( $rich_message, '- Sócio efetivo' ) && false !== strpos( $rich_message, '| Tipo | Quota |' ), 'Rich list or table formatting regressed.' );

$low = $medium->withRank( 20, array( 'quota' ) );
$low_set = new AdamBot\Knowledge\DTO\SearchResultSet( array( $low ), array( $low ), 20, 'low', 'membership', 'faq', array( 'quota' ), 1 );
$low_response = $formatter->format( $low_set, 'quota' )->toPublicArray();
test_assert( false !== strpos( $low_response['message'], 'páginas relacionadas' ) && 1 === count( $low_response['links'] ), 'Low-confidence page suggestions failed.' );

$none_set = new AdamBot\Knowledge\DTO\SearchResultSet( array(), array(), 0, 'none', '', '', array(), 1 );
$none_response = $formatter->format( $none_set, 'xyz' )->toPublicArray();
test_assert( 0 === strpos( $none_response['message'], 'Não encontrei uma resposta'), 'Portuguese no-confidence copy is incorrect.' );

$synthetic_provider = new class() implements AdamBot\Knowledge\KnowledgeProviderInterface {
	/** @var int */
	public $calls = 0;
	public function getKey(): string { return 'custom'; }
	public function search( string $query ): array {
		unset( $query );
		$this->calls++;
		return array( new AdamBot\Knowledge\DTO\KnowledgeResult( 'custom', 'Custom', 'Benefícios de sócio', 'Os sócios têm benefícios em eventos.', 'Membership', 'https://example.test/benefits', 15 ) );
	}
};
$empty_provider = new class() implements AdamBot\Knowledge\KnowledgeProviderInterface {
	/** @var int */
	public $calls = 0;
	public function getKey(): string { return 'custom'; }
	public function search( string $query ): array { unset( $query ); $this->calls++; return array(); }
};
$settings = new AdamBot\Knowledge\KnowledgeSettings();
$ranker   = new AdamBot\Knowledge\Search\ResultRanker( $matcher );
$service  = new AdamBot\Knowledge\Search\SearchService( $settings, $ranker, $matcher, new AdamBot\Helpers\Logger( false ), array( $empty_provider, $synthetic_provider ) );
$custom   = $service->search( 'Quais são os benefícios de sócio?' );
$custom_cached = $service->search( 'Quais são os benefícios de sócio?' );
test_assert( $custom->hasResults() && 'custom' === $custom->getMatchedProvider(), 'Registered provider was not searched and ranked.' );
test_assert( 1 === $empty_provider->calls && 1 === $synthetic_provider->calls, 'SearchService did not query every registered provider.' );
test_assert( $custom_cached->hasResults(), 'Registered-provider search was not cached.' );

$analytics = new AdamBot\Analytics\Analytics();
$analytics->record( 'Email person@example.test or call +351 912 345 678', false, 25, 'none', false );
$analytics_json = wp_json_encode( $test_options['adam_bot_analytics'] );
test_assert( false === strpos( $analytics_json, 'person@example.test' ) && false === strpos( $analytics_json, '+351 912 345 678' ), 'Analytics retained personal data.' );
test_assert( false !== strpos( $analytics_json, '[email]' ) && false !== strpos( $analytics_json, '[number]' ), 'Analytics did not scrub common personal data.' );
test_assert( (int) ( $test_options['adam_bot_analytics']['high_confidence'] ?? 0 ) >= 1 && (int) ( $test_options['adam_bot_analytics']['no_confidence'] ?? 0 ) >= 1, 'Confidence analytics were not recorded.' );

$source_text = '';
$iterator    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/includes' ) );
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$source_text .= (string) file_get_contents( $file->getPathname() );
	}
}
test_assert( false === stripos( $source_text, 'OpenAI' ) && false === strpos( $source_text, 'wp_remote_post' ), 'External AI provider code remains in the runtime.' );

if ( 'admin' === $test_mode ) {
	test_assert( isset( $test_admin['settings']['adam_bot_experience_settings'], $test_admin['settings']['adam_bot_knowledge_settings'] ), 'Admin settings were not registered.' );
	test_assert( ! isset( $test_admin['settings']['adam_bot_ai_settings'] ), 'AI settings are still registered.' );
	test_assert( isset( $test_post_types['adam_bot_faq'], $test_post_types['adam_bot_knowledge'] ), 'Knowledge entry types were not registered.' );

	ob_start();
	call_user_func( $test_admin['menus']['adam-bot'] );
	$settings_page = ob_get_clean();
	test_assert( false !== strpos( $settings_page, 'Quick Actions' ) && false !== strpos( $settings_page, 'High confidence' ), 'Phase 7 settings page is incomplete.' );
	test_assert( false === stripos( $settings_page, 'OpenAI' ) && false === stripos( $settings_page, 'API Key' ), 'AI controls remain visible.' );

	$sanitize = $test_admin['settings']['adam_bot_experience_settings']['args']['sanitize_callback'];
	$clean    = call_user_func( $sanitize, array( 'quick_actions' => array( array( 'icon'=>'📅', 'label'=>' Events ', 'prompt'=>' Show events ' ) ) ) );
	test_assert( 'Events' === ( $clean['quick_actions'][0]['label'] ?? '' ), 'Quick-action settings sanitization failed.' );
}

if ( 'public' === $test_mode ) {
	test_assert( isset( $test_hooks['wp_enqueue_scripts'], $test_hooks['wp_footer'] ), 'Public frontend hooks were not registered.' );
	run_test_hook( 'wp_enqueue_scripts' );
	ob_start();
	run_test_hook( 'wp_footer' );
	$widget = ob_get_clean();
	test_assert( false !== strpos( $widget, 'data-adam-template' ) && false !== strpos( $widget, 'maxlength="4000"' ), 'Lazy widget markup is incomplete.' );
	$localized = $test_assets['localized']['adam-bot']['data'] ?? array();
	test_assert( 'https://example.test/wp-json/adam-bot/v1/chat' === ( $localized['restUrl'] ?? '' ), 'Frontend REST URL is incorrect.' );
	test_assert( ! isset( $localized['strings']['generalConsent'] ) && 'Eventos' === ( $localized['strings']['events'] ?? '' ), 'Frontend still exposes general-AI consent.' );
} else {
	test_assert( ! isset( $test_hooks['wp_enqueue_scripts'], $test_hooks['wp_footer'] ), 'Frontend hooks were registered on a protected screen.' );
}

echo sprintf( "PASS: %s Phase 7 boundary.\n", $test_mode );
