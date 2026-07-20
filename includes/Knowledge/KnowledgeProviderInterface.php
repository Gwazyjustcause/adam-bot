<?php
/**
 * Knowledge provider contract.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Every provider supplies normalized candidates to the deterministic search pipeline.
 */
interface KnowledgeProviderInterface {
	/** @return string */
	public function getKey(): string;

	/**
	 * @param string $query Sanitized search query.
	 * @return array<int, \AdamBot\Knowledge\DTO\KnowledgeResult>
	 */
	public function search( string $query ): array;
}
