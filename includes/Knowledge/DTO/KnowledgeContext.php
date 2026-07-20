<?php
/**
 * Knowledge search context DTO.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\DTO;

defined( 'ABSPATH' ) || exit;

/**
 * Contains the bounded results and aggregate confidence for one question.
 */
final class KnowledgeContext {
	/** @var array<int, KnowledgeResult> */
	private $results;

	/** @var int */
	private $confidence;

	/**
	 * Creates a context DTO.
	 *
	 * @param array<int, KnowledgeResult> $results Ranked results.
	 * @param int                         $confidence Aggregate confidence from 0 to 100.
	 */
	public function __construct( array $results = array(), int $confidence = 0 ) {
		$this->results    = array_values(
			array_filter(
				$results,
				static function ( $result ): bool {
					return $result instanceof KnowledgeResult;
				}
			)
		);
		$this->confidence = max( 0, min( 100, $confidence ) );
	}

	/** @return array<int, KnowledgeResult> */
	public function getResults(): array {
		return $this->results;
	}

	/** @return int */
	public function getConfidence(): int {
		return $this->confidence;
	}

	/** @return bool */
	public function hasResults(): bool {
		return ! empty( $this->results );
	}

	/**
	 * Returns a serializable cache representation.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'confidence' => $this->confidence,
			'results'    => array_map(
				static function ( KnowledgeResult $result ): array {
					return $result->toArray();
				},
				$this->results
			),
		);
	}

	/**
	 * Restores a context from a cache representation.
	 *
	 * @param array<string, mixed> $data Cached data.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$results = array();

		if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
			foreach ( $data['results'] as $result ) {
				if ( is_array( $result ) ) {
					$results[] = KnowledgeResult::fromArray( $result );
				}
			}
		}

		return new self( $results, (int) ( $data['confidence'] ?? 0 ) );
	}
}
