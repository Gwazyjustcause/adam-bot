<?php
/**
 * Public deterministic knowledge response.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\DTO;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable response contract consumed by the existing Phase 6 frontend.
 */
final class KnowledgeResponse {
	/** @var string */
	private $message;

	/** @var array<int, array<string, string>> */
	private $suggestions;

	/** @var array<int, array<string, string>> */
	private $links;

	/** @var array<int, array<string, mixed>> */
	private $cards;

	/** @var array<string, mixed> */
	private $context;

	/** @var string */
	private $confidence_level;

	/** @var int */
	private $response_time_ms;

	/**
	 * @param string                           $message Formatted Markdown response.
	 * @param array<int, array<string,string>> $suggestions Follow-up actions.
	 * @param array<int, array<string,string>> $links Navigation actions.
	 * @param array<int, array<string,mixed>>  $cards Structured knowledge cards.
	 * @param array<string, mixed>             $context Lightweight next-turn context.
	 * @param string                           $confidence_level Internal confidence level.
	 * @param int                              $response_time_ms Search and formatting time.
	 */
	public function __construct(
		string $message,
		array $suggestions = array(),
		array $links = array(),
		array $cards = array(),
		array $context = array(),
		string $confidence_level = 'none',
		int $response_time_ms = 0
	) {
		$this->message          = trim( $message );
		$this->suggestions      = $suggestions;
		$this->links            = $links;
		$this->cards            = $cards;
		$this->context          = $context;
		$this->confidence_level = in_array( $confidence_level, array( 'high', 'medium', 'low', 'none' ), true ) ? $confidence_level : 'none';
		$this->response_time_ms = max( 0, $response_time_ms );
	}

	/** @return bool */
	public function isSuccess(): bool {
		return true;
	}

	/** @return string */
	public function getConfidenceLevel(): string {
		return $this->confidence_level;
	}

	/** @return int */
	public function getResponseTimeMs(): int {
		return $this->response_time_ms;
	}

	/** @return bool */
	public function hasKnowledgeHit(): bool {
		return in_array( $this->confidence_level, array( 'high', 'medium', 'low' ), true );
	}

	/** @return array<string, mixed> */
	public function toPublicArray(): array {
		return array(
			'success'     => true,
			'message'     => $this->message,
			'suggestions' => $this->suggestions,
			'links'       => $this->links,
			'cards'       => $this->cards,
			'context'     => $this->context,
		);
	}
}
