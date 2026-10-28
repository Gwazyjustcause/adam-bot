<?php
/** Run with php tests/guided-website.php. No WordPress database or HTTP required. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
define( 'OBJECT', 'OBJECT' );
spl_autoload_register( static function ( $class ) {
	if ( 0 !== strpos( $class, 'AdamBot\\' ) ) return;
	require dirname( __DIR__ ) . '/includes/' . str_replace( '\\', '/', substr( $class, 8 ) ) . '.php';
} );

class WP_REST_Request {
	public $params = array();
	public $route;
	public function __construct( $method = 'GET', $route = '' ) { $this->route = $route; }
	public function set_param( $key, $value ) { $this->params[$key] = $value; }
	public function get_param( $key ) { return $this->params[$key] ?? null; }
}
class WP_REST_Response {
	private $data; private $status;
	public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}
function sanitize_key( $value ) { return $value; }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function sanitize_textarea_field( $value ) { return strip_tags( $value ); }
function esc_url_raw( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain ) { return $text; }
function home_url( $path ) { return 'https://example.test' . $path; }
function rest_url( $path ) { return home_url( '/wp-json/' . $path ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function strip_shortcodes( $value ) { return preg_replace( '/\[[^]]+\]/', '', $value ); }
function wp_trim_words( $value, $limit, $more ) { $words = preg_split( '/\s+/', trim( $value ) ); return implode( ' ', array_slice( $words, 0, $limit ) ) . ( count( $words ) > $limit ? $more : '' ); }
function get_option( $key, $default = null ) { global $options; return $options[$key] ?? $default; }
function get_post( $id ) { global $posts; return $posts[$id] ?? null; }
function get_post_meta( $id, $key, $single = true ) { global $meta; return $meta[$id][$key] ?? ''; }
function get_page_by_path( $path, $output, $type ) { global $posts; foreach ( $posts as $post ) if ( $type === $post->post_type && $path === ( $post->post_name ?? '' ) ) return $post; return null; }
function get_permalink( $id ) { return home_url( '/' . get_post( $id )->post_name . '/' ); }
function get_posts( $args ) { global $posts; return array_values( array_filter( $posts, static function ( $post ) use ( $args ) { return $post->post_type === $args['post_type'] && $post->post_status === $args['post_status'] && $post->post_parent === $args['post_parent']; } ) ); }
function is_wp_error( $value ) { return $value instanceof RuntimeException; }
function rest_do_request( $request ) {
	global $directory, $failure;
	check( 10 === $request->get_param( 'per_page' ) && 1 === $request->get_param( 'page' ), 'Directory requests must be bounded.' );
	if ( 'throw' === $failure ) throw new RuntimeException( 'Unavailable' );
	return new WP_REST_Response( $directory[$request->route] ?? array(), 'missing' === $failure ? 404 : 200 );
}
function check( $value, $message ) { if ( ! $value ) throw new RuntimeException( $message ); }
function node( $id, $parent, $key ) {
	global $posts, $meta;
	$posts[$id] = (object) array( 'ID' => $id, 'post_type' => 'adam_bot_flow', 'post_status' => 'publish', 'post_parent' => $parent, 'post_title' => $key, 'post_content' => 'Outdated imported text' );
	$meta[$id] = array( '_adam_bot_flow_migration_key' => $key, '_adam_bot_flow_node_type' => 0 === $parent ? 'menu' : 'dynamic', '_adam_bot_flow_actions' => array( array( 'type' => 'url', 'url' => 'https://example.test/instituicoes/', 'label' => 'Wrong destination' ) ) );
}
function page( $id, $slug, $content ) {
	global $posts;
	$posts[$id] = (object) array( 'ID' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => 0, 'post_name' => $slug, 'post_content' => $content, 'post_password' => '' );
}
$posts = $meta = $options = array();
$failure = '';
node( 1, 0, 'root-teams' ); node( 2, 1, 'dynamic-teams' ); node( 3, 1, 'group-old-teams' );
node( 4, 0, 'root-partners' ); node( 5, 4, 'group-old-partners' );
node( 6, 0, 'root-fields' ); node( 7, 6, 'dynamic-fields' );
page( 10, 'equipas', '<p>Equipas da região Centro.</p>' );
page( 11, 'contactos', '<p>Envia a tua proposta pelo formulário.</p>' );
page( 12, 'campos', '<p>Consulta localização e regras.</p>' );
$directory = array(
	'/adam-comunidade/v1/equipas' => array(
		array( 'name' => 'CAT', 'description' => '', 'short_description' => 'Equipa focada em CQB.', 'url' => home_url( '/equipas/cat/' ), 'municipality' => 'Soure', 'district' => 'Coimbra', 'members' => 8, 'recruitment_status' => 'recruiting', 'email' => 'never-display@example.test' ),
		array( 'name' => 'Invalid link', 'url' => 'javascript:alert(1)' ),
		array( 'name' => 'External link', 'url' => 'https://other.test/team/' ),
	),
	'/adam-comunidade/v1/campos' => array( array( 'name' => 'Campo', 'description' => 'Regras do campo.', 'url' => home_url( '/campos/campo/' ) ) ),
);
$api = new AdamBot\Guided\GuidedNavigationAPI( new AdamBot\Knowledge\Dynamic\DynamicProviderRegistry(), new AdamBot\Analytics\Analytics(), new AdamBot\API\RateLimiter() );
function response( $id ) { global $api; $request = new WP_REST_Request(); $request->set_param( 'id', $id ); return $api->get_node( $request )->get_data(); }
$before = serialize( array( $posts, $meta, $options ) );
foreach ( array( 1, 2, 3 ) as $id ) {
	$data = response( $id );
	check( 'https://example.test/equipas/' === $data['actions'][0]['destination']['url'], 'Teams must link to Equipas, never partners or institutions.' );
	check( 1 === count( $data['items'] ) && 'Equipa focada em CQB.' === $data['items'][0]['description'], 'The API must return public descriptions and exclude unsafe links.' );
	check( 'https://example.test/equipas/cat/' === $data['items'][0]['url'] && false !== strpos( $data['items'][0]['details'], 'A recrutar' ), 'Canonical profile and recruitment details are required.' );
	check( false === strpos( json_encode( $data ), 'never-display' ), 'Only allowlisted public fields may be exposed.' );
}
check( home_url( '/contactos/' ) === response( 5 )['actions'][0]['destination']['url'], 'Partnerships must lead to Contactos.' );
check( 'Regras do campo.' === response( 7 )['items'][0]['description'], 'Fields must use the field endpoint.' );
$directory['/adam-comunidade/v1/equipas'][0]['short_description'] = 'Descrição atualizada.';
check( 'Descrição atualizada.' === response( 2 )['items'][0]['description'], 'Website changes must appear without population or indexing.' );
check( $before === serialize( array( $posts, $meta, $options ) ), 'Reading website content must not write bot records.' );
$options['adam_comunidade_settings']['teams_page_id'] = 10;
$posts[10]->post_name = 'equipas-renomeadas';
check( home_url( '/equipas-renomeadas/' ) === response( 2 )['actions'][0]['destination']['url'], 'Renamed managed pages must use their stored ID.' );
$posts[10]->post_status = 'draft';
check( empty( response( 2 )['actions'] ), 'Do not link draft pages.' );
$posts[10]->post_status = 'publish'; $posts[10]->post_password = 'secret';
check( empty( response( 2 )['actions'] ), 'Do not link password-protected pages.' );
foreach ( array( 'throw', 'missing' ) as $failure ) check( empty( response( 2 )['items'] ), 'Unavailable API must degrade gracefully.' );
$failure = '';
$meta[2]['_adam_bot_flow_migration_status'] = 'reviewed';
check( 'Outdated imported text' === response( 2 )['content'], 'Explicit editorial reviews must be preserved.' );
unset( $meta[2]['_adam_bot_flow_migration_key'], $meta[2]['_adam_bot_flow_migration_status'] );
check( 'Outdated imported text' === response( 2 )['content'], 'Custom authored nodes must be preserved.' );
echo "PASS: guided website destinations, live descriptions, profile links, no writes and failure handling.\n";
