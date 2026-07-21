<?php
/**
 * Dynamic knowledge provider contract.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Dynamic;

defined( 'ABSPATH' ) || exit;

/** Contract implemented by live ADAM ecosystem integrations. */
interface DynamicProviderInterface {
	public function getKey(): string;

	public function getLabel(): string;

	public function isAvailable(): bool;

	public function supportsIntent( string $intent ): bool;

	/** @return array<int, \AdamBot\Knowledge\DTO\KnowledgeResult> */
	public function search( string $query, string $intent ): array;

	/** @return array<int, array<string, string>> */
	public function getSuggestions( string $query, string $intent ): array;

	public function getPriority(): int;

	public function getCacheTtl(): int;
}
