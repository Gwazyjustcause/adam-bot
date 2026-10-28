<?php
/** Run with: php tests/website-answers.php */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'OBJECT', 'OBJECT' );
$posts = array();
$meta = array();
$allowed = true;
$fail_insert = false;

function current_user_can( $capability ) { global $allowed; return $allowed; }
function add_action( ...$args ) {}
function __( $text, $domain ) { return $text; }
function sanitize_key( $text ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $text ) ); }
function sanitize_text_field( $text ) { return strip_tags( $text ); }
function absint( $value ) { return abs( (int) $value ); }
function esc_url_raw( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof RuntimeException; }
function get_permalink( $id ) { return 'https://example.test/' . $id . '/'; }
function get_page_by_path( $path, $output, $type ) {
	global $posts;
	foreach ( $posts as $post ) if ( $type === $post->post_type && $path === ( $post->post_name ?? '' ) ) return $post;
	return null;
}
function get_posts( $args ) {
	global $posts, $meta;
	$matches = array_filter( $posts, static function ( $post ) use ( $args, $meta ) {
		if ( $post->post_type !== $args['post_type'] || ! in_array( $post->post_status, (array) $args['post_status'], true ) ) return false;
		if ( isset( $args['meta_key'] ) && ( $meta[$post->ID][$args['meta_key']] ?? '' ) !== $args['meta_value'] ) return false;
		if ( isset( $args['post_parent'] ) && $post->post_parent !== $args['post_parent'] ) return false;
		return ! isset( $args['title'] ) || $post->post_title === $args['title'];
	} );
	return array_slice( array_values( $matches ), 0, $args['posts_per_page'] );
}
function wp_insert_post( $data, $return_error ) {
	global $posts, $meta, $fail_insert;
	if ( $fail_insert ) return new RuntimeException( 'Database unavailable' );
	$id = count( $posts ) + 1;
	$meta[$id] = $data['meta_input'];
	unset( $data['meta_input'] );
	$posts[$id] = (object) array_merge( $data, array( 'ID' => $id ) );
	return $id;
}
function check( $value, $message ) {
	if ( ! $value ) throw new RuntimeException( $message );
}

require dirname( __DIR__ ) . '/includes/Knowledge/EntrySchema.php';
require dirname( __DIR__ ) . '/includes/Guided/FlowSchema.php';
require dirname( __DIR__ ) . '/includes/Guided/WebsiteAnswers.php';

use AdamBot\Guided\FlowSchema;
use AdamBot\Guided\WebsiteAnswers;
use AdamBot\Knowledge\EntrySchema;

foreach ( array( 'membership', 'events', 'airsoft', 'teams', 'fields', 'about', 'partners', 'help' ) as $root ) {
	$id = count( $posts ) + 1;
	$posts[$id] = (object) array( 'ID' => $id, 'post_type' => FlowSchema::POST_TYPE, 'post_status' => 'publish', 'post_title' => $root, 'post_parent' => 0 );
	$meta[$id][FlowSchema::MIGRATION_KEY_META] = 'root-' . $root;
}
foreach ( array( 'associa-te', 'quem-somos', 'equipas', 'campos', 'comunidade', 'contactos' ) as $slug ) {
	$id = count( $posts ) + 1;
	$posts[$id] = (object) array( 'ID' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug, 'post_password' => '' );
}
$initial = count( $posts );
$seeder = new WebsiteAnswers();
$allowed = false;
$seeder->populate();
check( count( $posts ) === $initial, 'Non-administrators must not populate content.' );
$allowed = true;
$page = get_page_by_path( 'campos', OBJECT, 'page' );
$page->post_password = 'private';
$contact = get_page_by_path( 'contactos', OBJECT, 'page' );
$contact->post_status = 'draft';
$community = get_page_by_path( 'comunidade', OBJECT, 'page' );
$community->post_name = 'moved';
$posts[2]->post_status = 'draft'; // Hidden events root.
$seeder->populate();
check( count( $posts ) === $initial + 9, 'Unavailable pages and hidden roots must be skipped.' );
$page->post_password = '';
$contact->post_status = 'publish';
$community->post_name = 'comunidade';
$posts[2]->post_status = 'publish';
$fail_insert = true;
$seeder->populate();
check( count( $posts ) === $initial + 9, 'Failed inserts must not leave partial records.' );
$fail_insert = false;
$seeder->populate();
check( count( $posts ) === $initial + 14, 'Missing answers must be retried when pages become available.' );

$answers = array_slice( $posts, $initial, null, true );
foreach ( $answers as $id => $answer ) {
	$values = $meta[$id];
	check( 'publish' === $answer->post_status && $answer->post_parent > 0, 'Answers must be published beneath a topic.' );
	check( ! empty( $values[FlowSchema::DIRECT_ANSWER_META] ), 'Every answer needs helpful text.' );
	$action = $values[FlowSchema::ACTIONS_META][0] ?? array();
	check( 'page' === ( $action['type'] ?? '' ) && 'publish' === $posts[$action['page_id']]->post_status, 'Each answer must link to a published local page.' );
	check( $values[EntrySchema::SOURCE_POST_META] === $action['page_id'], 'The action must lead to the source page.' );
}
$ids = array_keys( $answers );
$meta[$ids[0]][FlowSchema::DIRECT_ANSWER_META] = 'Resposta revista pelo administrador.';
$posts[$ids[1]]->post_status = 'draft';
$posts[$ids[2]]->post_status = 'trash';
$before = serialize( array( $posts, $meta ) );
$seeder->populate();
check( $before === serialize( array( $posts, $meta ) ), 'Repeated setup must preserve edits, drafts, trash and ordering without duplicates.' );

// Also respect a manually authored answer with the same title in the same topic.
unset( $meta[$ids[3]][FlowSchema::MIGRATION_KEY_META] );
$before = serialize( array( $posts, $meta ) );
$seeder->populate();
check( $before === serialize( array( $posts, $meta ) ), 'An existing editorial answer must not be duplicated.' );
echo "Website answers: 14 linked answers; access, unavailable pages, retry and editorial preservation checks passed.\n";
