<?php
/**
 * REST API component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\API;

use AdamBot\Analytics\Analytics;
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

	/** @var Analytics */
	private $analytics;

	/**
	 * Creates the REST API component.
	 *
	 * @param AIService   $ai_service Provider-neutral AI service.
	 * @param RateLimiter $rate_limiter Public request limiter.
	 * @param Analytics   $analytics Privacy-friendly aggregate analytics.
	 */
	public function __construct( AIService $ai_service, RateLimiter $rate_limiter, Analytics $analytics ) {
		$this->ai_service   = $ai_service;
		$this->rate_limiter = $rate_limiter;
		$this->analytics    = $analytics;
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
					'history' => array(
						'description' => __( 'Temporary current-session conversation context.', 'adam-bot' ),
						'required'    => false,
						'type'        => 'array',
						'maxItems'    => 10,
					),
					'allow_general' => array(
						'description' => __( 'Explicit opt-in to a clearly labelled general-knowledge answer.', 'adam-bot' ),
						'required'    => false,
						'type'        => 'boolean',
					),
					'new_conversation' => array(
						'description' => __( 'Whether this is the first request in the browser session.', 'adam-bot' ),
						'required'    => false,
						'type'        => 'boolean',
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

		$allow_general    = $this->toBoolean( $request->get_param( 'allow_general' ) );
		$new_conversation = $this->toBoolean( $request->get_param( 'new_conversation' ) );
		$history          = $this->sanitizeHistory( $request->get_param( 'history' ) );
		$response         = $this->ai_service->generateResponse(
			new ChatRequest( $message, '', false, $history, $allow_general )
		);

		$this->analytics->record(
			$message,
			$new_conversation,
			$response->getResponseTimeMs(),
			$response->isSuccess() && ! $response->needsGeneralConsent() ? $response->getClassification() : '',
			$response->isSuccess() && $response->hasKnowledgeHit(),
			! $allow_general
		);

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

	/**
	 * Sanitizes bounded context supplied from sessionStorage.
	 *
	 * @param mixed $value Candidate history.
	 * @return array<int, array<string, string>>
	 */
	private function sanitizeHistory( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$history = array();

		foreach ( array_slice( $value, -10 ) as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}

			$role    = sanitize_key( (string) ( $turn['role'] ?? '' ) );
			$content = sanitize_textarea_field( is_scalar( $turn['content'] ?? null ) ? (string) $turn['content'] : '' );

			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === trim( $content ) ) {
				continue;
			}

			if ( function_exists( 'mb_substr' ) ) {
				$content = mb_substr( $content, 0, 2000 );
			} else {
				$content = substr( $content, 0, 2000 );
			}

			$history[] = compact( 'role', 'content' );
		}

		return $history;
	}

	/** @return bool */
	private function toBoolean( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}
}
