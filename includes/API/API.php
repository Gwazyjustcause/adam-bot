<?php
/**
 * REST API component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\API;

use AdamBot\Services\ChatService;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the ADAM BOT REST API routes.
 */
final class API {
	/**
	 * Chat service reserved for future request handling.
	 *
	 * @var ChatService
	 */
	private $chat_service;

	/**
	 * Creates the REST API component.
	 *
	 * @param ChatService $chat_service Chat service.
	 */
	public function __construct( ChatService $chat_service ) {
		$this->chat_service = $chat_service;
	}

	/**
	 * Registers REST API hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the chat readiness endpoint.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'adam-bot/v1',
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'message' => array(
						'description'       => __( 'Chat message.', 'adam-bot' ),
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Returns the Phase 2 API readiness response.
	 *
	 * @param WP_REST_Request $request REST request. Input is sanitized by the route schema.
	 * @return \WP_REST_Response
	 */
	public function chat( WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'API ready', 'adam-bot' ),
			)
		);
	}
}
