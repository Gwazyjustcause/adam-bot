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
	/** Trusted ADAM content only. */
	public const CLASSIFICATION_OFFICIAL = 'official_adam';

	/** User-approved general model knowledge. */
	public const CLASSIFICATION_GENERAL = 'general_ai';

	/** Trusted ADAM context plus general model knowledge. */
	public const CLASSIFICATION_MIXED = 'mixed';

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

	/** @var string */
	private $classification;

	/** @var array<int, array<string, string>> */
	private $suggestions;

	/** @var array<int, array<string, string>> */
	private $links;

	/** @var bool */
	private $knowledge_hit;

	/** @var int */
	private $response_time_ms;

	/** @var bool */
	private $needs_general_consent;

	/**
	 * Creates a response DTO.
	 *
	 * @param bool     $success Whether generation succeeded.
	 * @param string   $message Public response message.
	 * @param string   $provider Provider identifier.
	 * @param int|null $prompt_tokens Input-token usage.
	 * @param int|null $completion_tokens Output-token usage.
	 * @param int|null                         $total_tokens Total-token usage.
	 * @param string                           $classification Internal trust classification.
	 * @param array<int, array<string,string>> $suggestions Contextual follow-up actions.
	 * @param array<int, array<string,string>> $links Safe ADAM navigation links.
	 * @param bool                             $knowledge_hit Whether trusted context was found.
	 * @param int                              $response_time_ms End-to-end response time.
	 * @param bool                             $needs_general_consent Whether general knowledge requires user opt-in.
	 */
	public function __construct(
		bool $success,
		string $message,
		string $provider = '',
		?int $prompt_tokens = null,
		?int $completion_tokens = null,
		?int $total_tokens = null,
		string $classification = self::CLASSIFICATION_GENERAL,
		array $suggestions = array(),
		array $links = array(),
		bool $knowledge_hit = false,
		int $response_time_ms = 0,
		bool $needs_general_consent = false
	) {
		$this->success           = $success;
		$this->message           = trim( $message );
		$this->provider          = $provider;
		$this->prompt_tokens     = $prompt_tokens;
		$this->completion_tokens = $completion_tokens;
		$this->total_tokens      = $total_tokens;
		$this->classification    = in_array( $classification, array( self::CLASSIFICATION_OFFICIAL, self::CLASSIFICATION_GENERAL, self::CLASSIFICATION_MIXED ), true )
			? $classification
			: self::CLASSIFICATION_GENERAL;
		$this->suggestions       = $suggestions;
		$this->links             = $links;
		$this->knowledge_hit     = $knowledge_hit;
		$this->response_time_ms  = max( 0, $response_time_ms );
		$this->needs_general_consent = $needs_general_consent;
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

	/** @return string */
	public function getClassification(): string {
		return $this->classification;
	}

	/** @return bool */
	public function hasKnowledgeHit(): bool {
		return $this->knowledge_hit;
	}

	/** @return int */
	public function getResponseTimeMs(): int {
		return $this->response_time_ms;
	}

	/** @return bool */
	public function needsGeneralConsent(): bool {
		return $this->needs_general_consent;
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
	 * Returns a copy enriched with non-provider experience metadata.
	 *
	 * @param string                           $classification Internal trust class.
	 * @param array<int, array<string,string>> $suggestions Follow-up actions.
	 * @param array<int, array<string,string>> $links Navigation links.
	 * @param bool                             $knowledge_hit Whether trusted context was present.
	 * @param int                              $response_time_ms End-to-end response time.
	 * @param bool                             $needs_general_consent Whether general knowledge needs opt-in.
	 * @return self
	 */
	public function withExperience(
		string $classification,
		array $suggestions,
		array $links,
		bool $knowledge_hit,
		int $response_time_ms,
		bool $needs_general_consent = false
	): self {
		return new self(
			$this->success,
			$this->message,
			$this->provider,
			$this->prompt_tokens,
			$this->completion_tokens,
			$this->total_tokens,
			$classification,
			$suggestions,
			$links,
			$knowledge_hit,
			$response_time_ms,
			$needs_general_consent
		);
	}

	/**
	 * Returns the intentionally small public REST payload.
	 *
	 * The internal confidence classification is deliberately omitted.
	 *
	 * @return array<string, mixed>
	 */
	public function toPublicArray(): array {
		return array(
			'success'               => $this->success,
			'message'               => $this->message,
			'suggestions'           => $this->suggestions,
			'links'                 => $this->links,
			'needsGeneralKnowledge' => $this->needs_general_consent,
		);
	}
}
