<?php
/**
 * REST API component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\API;

use AdamBot\AI\DTO\ChatRequest;
use AdamBot\AI\Services\AIService;
use AdamBot\AI\Settings\AISettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the ADAM BOT REST API routes.
 */
final class API {
	/**
	 * Provider-neutral AI service.
	 *
	 * @var AIService
	 */
	private $ai_service;

	/** @var RateLimiter */
	private $rate_limiter;

	/**
	 * Creates the REST API component.
	 *
	 * @param AIService   $ai_service Provider-neutral AI service.
	 * @param RateLimiter $rate_limiter Public request limiter.
	 */
	public function __construct( AIService $ai_service, RateLimiter $rate_limiter ) {
		$this->ai_service   = $ai_service;
		$this->rate_limiter = $rate_limiter;
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
	 * Registers the chat generation endpoint.
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
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( $this, 'sanitizeMessage' ),
						'validate_callback' => array( $this, 'validateMessage' ),
					),
				),
			)
		);
	}

	/**
	 * Generates a chat response through AIService only.
	 *
	 * @param WP_REST_Request $request REST request. Input is sanitized by the route schema.
	 * @return WP_REST_Response
	 */
	public function chat( WP_REST_Request $request ) {
		$message = $this->sanitizeMessage( $request->get_param( 'message' ) );

		if ( ! $this->validateMessage( $message ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Please enter a valid message.', 'adam-bot' ),
				),
				400
			);
		}

		if ( ! $this->rate_limiter->consume() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Too many requests. Please wait a few minutes before trying again.', 'adam-bot' ),
				),
				429
			);
		}

		$response = $this->ai_service->generateResponse( new ChatRequest( $message ) );

		return new WP_REST_Response(
			$response->toPublicArray(),
			$response->isSuccess() ? 200 : 503
		);
	}

	/**
	 * Sanitizes a public message before it enters the AI layer.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitizeMessage( $value ): string {
		return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Enforces a non-empty maximum prompt size.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public function validateMessage( $value ): bool {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$length = function_exists( 'mb_strlen' )
			? mb_strlen( $value )
			: strlen( $value );

		return $length <= AISettings::MAX_PROMPT_CHARACTERS;
	}
}
