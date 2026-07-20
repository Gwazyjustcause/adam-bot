<?php
/**
 * Provider-neutral chat request DTO.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\DTO;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable input passed through the AI service boundary.
 */
final class ChatRequest {
	/** @var string */
	private $user_message;

	/** @var string */
	private $system_prompt;

	/** @var bool */
	private $streaming;

	/**
	 * Creates a request. The system prompt is attached by PromptBuilder.
	 *
	 * @param string $user_message Sanitized user message.
	 * @param string $system_prompt Server-controlled system prompt.
	 * @param bool   $streaming Reserved for a future streaming transport.
	 */
	public function __construct( string $user_message, string $system_prompt = '', bool $streaming = false ) {
		$this->user_message = trim( $user_message );
		$this->system_prompt = trim( $system_prompt );
		$this->streaming     = $streaming;
	}

	/** @return string */
	public function getUserMessage(): string {
		return $this->user_message;
	}

	/** @return string */
	public function getSystemPrompt(): string {
		return $this->system_prompt;
	}

	/** @return bool */
	public function isStreaming(): bool {
		return $this->streaming;
	}

	/**
	 * Returns a copy containing the trusted system prompt.
	 *
	 * @param string $system_prompt System prompt.
	 * @return self
	 */
	public function withSystemPrompt( string $system_prompt ): self {
		return new self( $this->user_message, $system_prompt, $this->streaming );
	}
}
