<?php
/** Public read-only API for the guided ADAM BOT tree. */

declare(strict_types=1);

namespace AdamBot\Guided;

use AdamBot\Knowledge\Dynamic\DynamicProviderRegistry;
use AdamBot\Knowledge\EntrySchema;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/** Exposes published guided nodes without exposing editorial or migration data. */
final class GuidedNavigationAPI {
	private DynamicProviderRegistry $providers;

	public function __construct( DynamicProviderRegistry $providers ) {
		$this->providers = $providers;
	}

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$readable = defined( 'WP_REST_Server::READABLE' ) ? WP_REST_Server::READABLE : 'GET';
		$common = array(
			'methods' => $readable,
			'permission_callback' => '__return_true',
			'callback' => array( $this, 'get_node' ),
		);
		register_rest_route( 'adam-bot/v1', '/guided', $common );
		register_rest_route( 'adam-bot/v1', '/guided/(?P<id>\d+)', array_merge( $common, array( 'args' => array( 'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) ) ) ) );
	}

	/** Returns the root menu or one published node. */
	public function get_node( WP_REST_Request $request ): WP_REST_Response {
		$id = absint( $request->get_param( 'id' ) );
		if ( $id > 0 ) {
			$node = get_post( $id );
			if ( ! $this->is_public_node( $node ) ) return new WP_REST_Response( array( 'code' => 'guided_node_not_found', 'message' => __( 'Nó guiado não encontrado.', 'adam-bot' ) ), 404 );
			return new WP_REST_Response( $this->serialize_node( $node ), 200 );
		}

		$roots = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => 'publish', 'post_parent' => 0, 'posts_per_page' => 100, 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true ) );
		return new WP_REST_Response( array( 'id' => 0, 'type' => 'menu', 'label' => __( 'ADAM BOT', 'adam-bot' ), 'language' => 'pt', 'intro' => '', 'content' => '', 'blocks' => array(), 'provider' => '', 'children' => array_map( array( $this, 'serialize_choice' ), array_filter( $roots, array( $this, 'is_public_node' ) ) ), 'actions' => array() ), 200 );
	}

	private function serialize_node( $node ): array {
		$type = FlowSchema::nodeType( get_post_meta( $node->ID, FlowSchema::NODE_TYPE_META, true ) );
		$children = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => 'publish', 'post_parent' => (int) $node->ID, 'posts_per_page' => 100, 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true ) );
		$blocks = EntrySchema::sanitizeBlocks( get_post_meta( $node->ID, EntrySchema::RESPONSE_BLOCKS_META, true ) );
		$content = trim( wp_strip_all_tags( strip_shortcodes( (string) $node->post_content ) ) );
		return array(
			'id' => (int) $node->ID,
			'type' => $type,
			'label' => $this->label( $node ),
			'icon' => sanitize_text_field( (string) get_post_meta( $node->ID, FlowSchema::ICON_META, true ) ),
			'language' => FlowSchema::language( get_post_meta( $node->ID, FlowSchema::LANGUAGE_META, true ) ),
			'intro' => sanitize_textarea_field( (string) get_post_meta( $node->ID, FlowSchema::INTRO_META, true ) ),
			'direct_answer' => sanitize_textarea_field( (string) get_post_meta( $node->ID, FlowSchema::DIRECT_ANSWER_META, true ) ),
			'content' => $content,
			'blocks' => $blocks,
			'provider' => $this->provider_key( $node ),
			'children' => array_map( array( $this, 'serialize_choice' ), array_filter( $children, array( $this, 'is_public_node' ) ) ),
			'actions' => $this->serialize_actions( get_post_meta( $node->ID, FlowSchema::ACTIONS_META, true ) ),
		);
	}

	private function serialize_choice( $node ): array {
		return array( 'id' => (int) $node->ID, 'type' => FlowSchema::nodeType( get_post_meta( $node->ID, FlowSchema::NODE_TYPE_META, true ) ), 'label' => $this->label( $node ), 'icon' => sanitize_text_field( (string) get_post_meta( $node->ID, FlowSchema::ICON_META, true ) ), 'language' => FlowSchema::language( get_post_meta( $node->ID, FlowSchema::LANGUAGE_META, true ) ), 'url' => rest_url( 'adam-bot/v1/guided/' . (int) $node->ID ) );
	}

	private function serialize_actions( $value ): array {
		$actions = array();
		foreach ( FlowSchema::sanitizeActions( $value ) as $action ) {
			$type = FlowSchema::actionType( $action['type'] );
			$destination = array( 'type' => $type );
			if ( 'node' === $type ) {
				$target = get_post( (int) $action['node_id'] );
				if ( ! $this->is_public_node( $target ) ) continue;
				$destination['id'] = (int) $target->ID;
				$destination['url'] = rest_url( 'adam-bot/v1/guided/' . (int) $target->ID );
			} elseif ( 'page' === $type ) {
				$page = get_post( (int) $action['page_id'] );
				if ( ! is_object( $page ) || 'page' !== (string) $page->post_type || 'publish' !== (string) $page->post_status ) continue;
				$destination['id'] = (int) $page->ID;
				$destination['url'] = esc_url_raw( (string) get_permalink( $page->ID ) );
			} else {
				$url = esc_url_raw( (string) $action['url'] );
				$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
				if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) continue;
				$destination['url'] = $url;
			}
			$actions[] = array( 'label' => (string) $action['label'], 'primary' => 0 === count( $actions ), 'destination' => $destination );
		}
		return $actions;
	}

	private function provider_key( $node ): string {
		$key = sanitize_key( (string) get_post_meta( $node->ID, FlowSchema::PROVIDER_META, true ) );
		return array_key_exists( $key, $this->providers->labels() ) ? $key : '';
	}

	private function label( $node ): string {
		$label = sanitize_text_field( (string) get_post_meta( $node->ID, FlowSchema::LABEL_META, true ) );
		return '' !== $label ? $label : sanitize_text_field( (string) $node->post_title );
	}

	private function is_public_node( $node ): bool {
		if ( ! is_object( $node ) || FlowSchema::POST_TYPE !== (string) ( $node->post_type ?? '' ) || 'publish' !== (string) ( $node->post_status ?? '' ) ) return false;
		$seen = array();
		while ( $node->post_parent > 0 ) {
			if ( isset( $seen[ $node->ID ] ) ) return false;
			$seen[ $node->ID ] = true;
			$node = get_post( (int) $node->post_parent );
			if ( ! is_object( $node ) || FlowSchema::POST_TYPE !== (string) ( $node->post_type ?? '' ) || 'publish' !== (string) ( $node->post_status ?? '' ) ) return false;
		}
		return true;
	}
}
