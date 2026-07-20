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

	/** @var array<int, array<string, string>> */
	private $history;

	/** @var bool */
	private $allow_general_knowledge;

	/**
	 * Creates a request. The system prompt is attached by PromptBuilder.
	 *
	 * @param string $user_message Sanitized user message.
	 * @param string $system_prompt Server-controlled system prompt.
	 * @param bool                            $streaming Reserved for a future streaming transport.
	 * @param array<int, array<string,string>> $history Current-session conversation context.
	 * @param bool                            $allow_general_knowledge Whether the user explicitly opted into a non-official answer.
	 */
	public function __construct(
		string $user_message,
		string $system_prompt = '',
		bool $streaming = false,
		array $history = array(),
		bool $allow_general_knowledge = false
	) {
		$this->user_message           = trim( $user_message );
		$this->system_prompt           = trim( $system_prompt );
		$this->streaming               = $streaming;
		$this->history                 = $this->normalizeHistory( $history );
		$this->allow_general_knowledge = $allow_general_knowledge;
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

	/** @return array<int, array<string, string>> */
	public function getHistory(): array {
		return $this->history;
	}

	/** @return bool */
	public function allowsGeneralKnowledge(): bool {
		return $this->allow_general_knowledge;
	}

	/**
	 * Returns a copy containing the trusted system prompt.
	 *
	 * @param string $system_prompt System prompt.
	 * @return self
	 */
	public function withSystemPrompt( string $system_prompt ): self {
		return new self(
			$this->user_message,
			$system_prompt,
			$this->streaming,
			$this->history,
			$this->allow_general_knowledge
		);
	}

	/**
	 * Keeps only bounded user/assistant turns.
	 *
	 * @param array<int, mixed> $history Candidate history.
	 * @return array<int, array<string, string>>
	 */
	private function normalizeHistory( array $history ): array {
		$clean = array();

		foreach ( array_slice( $history, -10 ) as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}

			$role    = (string) ( $turn['role'] ?? '' );
			$content = trim( (string) ( $turn['content'] ?? '' ) );

			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === $content ) {
				continue;
			}

			$clean[] = compact( 'role', 'content' );
		}

		return $clean;
	}
}
