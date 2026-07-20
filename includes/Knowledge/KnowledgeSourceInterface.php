<?php
/**
 * Common knowledge-source contract.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Every knowledge source searches its own data behind this stable boundary.
 */
interface KnowledgeSourceInterface {
	/**
	 * Returns the source key used by the server-side enablement allowlist.
	 *
	 * @return string
	 */
	public function getKey(): string;

	/**
	 * Searches this source and returns scored knowledge results.
	 *
	 * @param string $query Sanitized user question.
	 * @return array<int, \AdamBot\Knowledge\DTO\KnowledgeResult>
	 */
	public function search( string $query ): array;
}
