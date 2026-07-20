<?php
/**
 * Provider-neutral chat response DTO.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\DTO;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable response returned by AIService.
 */
final class ChatResponse {
	/** @var bool */
	private $success;

	/** @var string */
	private $message;

	/** @var string */
	private $provider;

	/** @var int|null */
	private $prompt_tokens;

	/** @var int|null */
	private $completion_tokens;

	/** @var int|null */
	private $total_tokens;

	/**
	 * Creates a response DTO.
	 *
	 * @param bool     $success Whether generation succeeded.
	 * @param string   $message Public response message.
	 * @param string   $provider Provider identifier.
	 * @param int|null $prompt_tokens Input-token usage.
	 * @param int|null $completion_tokens Output-token usage.
	 * @param int|null $total_tokens Total-token usage.
	 */
	public function __construct(
		bool $success,
		string $message,
		string $provider = '',
		?int $prompt_tokens = null,
		?int $completion_tokens = null,
		?int $total_tokens = null
	) {
		$this->success           = $success;
		$this->message           = trim( $message );
		$this->provider          = $provider;
		$this->prompt_tokens     = $prompt_tokens;
		$this->completion_tokens = $completion_tokens;
		$this->total_tokens      = $total_tokens;
	}

	/** @return bool */
	public function isSuccess(): bool {
		return $this->success;
	}

	/** @return string */
	public function getMessage(): string {
		return $this->message;
	}

	/** @return string */
	public function getProvider(): string {
		return $this->provider;
	}

	/**
	 * Returns token usage without any conversation content.
	 *
	 * @return array<string, int|null>
	 */
	public function getTokenUsage(): array {
		return array(
			'prompt_tokens'     => $this->prompt_tokens,
			'completion_tokens' => $this->completion_tokens,
			'total_tokens'      => $this->total_tokens,
		);
	}

	/**
	 * Returns the intentionally small public REST payload.
	 *
	 * @return array<string, bool|string>
	 */
	public function toPublicArray(): array {
		return array(
			'success' => $this->success,
			'message' => $this->message,
		);
	}
}
