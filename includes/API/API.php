<?php
/**
 * REST API component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\API;

use AdamBot\Analytics\Analytics;
use AdamBot\Knowledge\Response\ResponseFormatter;
use AdamBot\Knowledge\Search\SearchService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the deterministic ADAM Knowledge Response endpoint.
 */
final class API {
	/** Maximum public question length. */
	private const MAX_MESSAGE_CHARACTERS = 4000;

	/** @var SearchService */
	private $search_service;

	/** @var ResponseFormatter */
	private $response_formatter;

	/** @var RateLimiter */
	private $rate_limiter;

	/** @var Analytics */
	private $analytics;

	/**
	 * @param SearchService     $search_service Deterministic provider search.
	 * @param ResponseFormatter $response_formatter Conversational formatter.
	 * @param RateLimiter       $rate_limiter Public request limiter.
	 * @param Analytics         $analytics Privacy-friendly aggregate analytics.
	 */
	public function __construct(
		SearchService $search_service,
		ResponseFormatter $response_formatter,
		RateLimiter $rate_limiter,
		Analytics $analytics
	) {
		$this->search_service     = $search_service;
		$this->response_formatter = $response_formatter;
		$this->rate_limiter       = $rate_limiter;
		$this->analytics          = $analytics;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** @return void */
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
					'context' => array(
						'description' => __( 'Temporary topic and recently shown knowledge results.', 'adam-bot' ),
						'required'    => false,
						'type'        => 'object',
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
	 * Runs User -> Search -> Rank -> Format -> Display without an external API.
	 *
	 * @param WP_REST_Request $request REST request.
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

		$context          = $this->sanitizeContext( $request->get_param( 'context' ) );
		$new_conversation = $this->toBoolean( $request->get_param( 'new_conversation' ) );
		$search           = $this->search_service->search( $message, $context );
		$response         = $this->response_formatter->format( $search, $message );
		$top              = $search->getTopResult();

		$this->analytics->record(
			$message,
			$new_conversation,
			$response->getResponseTimeMs(),
			$response->getConfidenceLevel(),
			$response->hasKnowledgeHit(),
			true,
			$search->getConfidence(),
			$top ? $top->getObjectId() : 0,
			$top ? $top->getTitle() : '',
			$search->getMatchedProvider(),
			$search->getIntent(),
			$search->getFallbackProvider(),
			$search->getProviderResultCount(),
			$search->getProviderDurationMs(),
			$search->isDynamic(),
			$top ? $top->getCategory() : '',
			$search->getMatchedKeywords()
		);

		return new WP_REST_Response( $response->toPublicArray(), 200 );
	}

	/** @param mixed $value Submitted value. */
	public function sanitizeMessage( $value ): string {
		return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/** @param mixed $value Submitted value. */
	public function validateMessage( $value ): bool {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		return $length <= self::MAX_MESSAGE_CHARACTERS;
	}

	/**
	 * Keeps only a bounded topic and opaque result IDs for the browser session.
	 *
	 * @param mixed $value Candidate context.
	 * @return array<string, mixed>
	 */
	private function sanitizeContext( $value ): array {
		if ( ! is_array( $value ) ) {
			return array( 'topic' => '', 'recent_result_ids' => array() );
		}

		$topic          = sanitize_key( (string) ( $value['topic'] ?? '' ) );
		$topic          = substr( $topic, 0, 64 );
		$ids            = $value['recentResultIds'] ?? $value['recent_result_ids'] ?? array();
		$ids            = is_array( $ids ) ? $ids : array();
		$ids            = array_values(
			array_filter(
				array_map(
					static function ( $id ): string {
						$id = strtolower( is_scalar( $id ) ? (string) $id : '' );
						return 1 === preg_match( '/^[a-f0-9]{32}$/', $id ) ? $id : '';
					},
					array_slice( $ids, -5 )
				)
			)
		);

		return array( 'topic' => $topic, 'recent_result_ids' => $ids );
	}

	/** @return bool */
	private function toBoolean( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}
}
