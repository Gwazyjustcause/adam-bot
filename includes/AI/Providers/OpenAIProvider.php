<?php
/**
 * OpenAI provider adapter.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Providers;

use AdamBot\AI\DTO\ChatRequest;
use AdamBot\AI\DTO\ChatResponse;
use AdamBot\AI\Exceptions\ConfigurationException;
use AdamBot\AI\Exceptions\ProviderException;

defined( 'ABSPATH' ) || exit;

/**
 * Calls OpenAI without leaking provider details outside the adapter.
 */
final class OpenAIProvider implements AIProviderInterface {
	/** OpenAI Chat Completions endpoint. */
	private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/** @var string */
	private $api_key;

	/** @var string */
	private $model;

	/** @var float */
	private $temperature;

	/** @var int */
	private $max_tokens;

	/** @var int */
	private $timeout;

	/**
	 * Creates the OpenAI adapter.
	 *
	 * @param string $api_key OpenAI secret key.
	 * @param string $model Model identifier.
	 * @param float  $temperature Sampling temperature.
	 * @param int    $max_tokens Maximum completion tokens.
	 * @param int    $timeout HTTP timeout in seconds.
	 */
	public function __construct( string $api_key, string $model, float $temperature, int $max_tokens, int $timeout ) {
		$this->api_key     = $api_key;
		$this->model       = $model;
		$this->temperature = $temperature;
		$this->max_tokens  = $max_tokens;
		$this->timeout     = $timeout;
	}

	/**
	 * Generates a non-streaming response.
	 *
	 * ChatRequest already carries a streaming flag so a future transport can be
	 * added without changing the public REST client or the provider contract.
	 *
	 * @param ChatRequest $request Prepared request.
	 * @return ChatResponse
	 * @throws ConfigurationException When trusted request context is missing.
	 * @throws ProviderException When OpenAI is unavailable or returns invalid data.
	 */
	public function generateResponse( ChatRequest $request ): ChatResponse {
		if ( '' === $request->getSystemPrompt() ) {
			throw new ConfigurationException( 'A system prompt is required.' );
		}

		if ( $request->isStreaming() ) {
			throw new ConfigurationException( 'Streaming transport is not implemented yet.' );
		}

		$messages = array(
			array(
				'role'    => 'developer',
				'content' => $request->getSystemPrompt(),
			),
		);

		foreach ( $request->getHistory() as $turn ) {
			$messages[] = array(
				'role'    => $turn['role'],
				'content' => $turn['content'],
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $request->getUserMessage(),
		);

		$payload = array(
			'model'                 => $this->model,
			'messages'              => $messages,
			'temperature'           => $this->temperature,
			'max_completion_tokens' => $this->max_tokens,
			'stream'                => false,
			'store'                 => false,
		);

		// Sampling controls require non-reasoning mode on GPT-5-class models.
		if ( 0 === strpos( $this->model, 'gpt-5' ) ) {
			$payload['reasoning_effort'] = 'none';
		}

		$encoded = wp_json_encode( $payload );

		if ( false === $encoded ) {
			throw new ProviderException( 'OpenAI request encoding failed.' );
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => $this->timeout,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'        => $encoded,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ProviderException( 'OpenAI transport error: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			throw new ProviderException( $this->buildApiError( $status_code, $data ) );
		}

		if ( ! is_array( $data ) ) {
			throw new ProviderException( 'OpenAI returned invalid JSON.' );
		}

		$content = $data['choices'][0]['message']['content'] ?? null;

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			throw new ProviderException( 'OpenAI returned an empty response.' );
		}

		$usage = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();

		return new ChatResponse(
			true,
			$content,
			'openai',
			$this->usageValue( $usage, 'prompt_tokens' ),
			$this->usageValue( $usage, 'completion_tokens' ),
			$this->usageValue( $usage, 'total_tokens' )
		);
	}

	/**
	 * Builds a bounded diagnostic error message for internal logs.
	 *
	 * @param int   $status_code HTTP status.
	 * @param mixed $data Decoded response.
	 * @return string
	 */
	private function buildApiError( int $status_code, $data ): string {
		$message = '';
		$type    = '';

		if ( is_array( $data ) && isset( $data['error'] ) && is_array( $data['error'] ) ) {
			$message = isset( $data['error']['message'] ) && is_string( $data['error']['message'] )
				? sanitize_text_field( $data['error']['message'] )
				: '';
			$type = isset( $data['error']['type'] ) && is_string( $data['error']['type'] )
				? sanitize_key( $data['error']['type'] )
				: '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			$message = mb_substr( $message, 0, 300 );
		} else {
			$message = substr( $message, 0, 300 );
		}

		return sprintf(
			'OpenAI API error (HTTP %d%s)%s',
			$status_code,
			'' !== $type ? ', ' . $type : '',
			'' !== $message ? ': ' . $message : '.'
		);
	}

	/**
	 * Reads one optional integer usage value.
	 *
	 * @param array<string, mixed> $usage Usage object.
	 * @param string               $key Usage key.
	 * @return int|null
	 */
	private function usageValue( array $usage, string $key ): ?int {
		return isset( $usage[ $key ] ) && is_numeric( $usage[ $key ] )
			? (int) $usage[ $key ]
			: null;
	}
}
