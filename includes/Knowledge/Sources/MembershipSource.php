<?php
/**
 * Membership knowledge source adapter.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Sources;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\KnowledgeSourceInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Consumes authoritative membership data supplied by the ADAM ecosystem.
 */
final class MembershipSource implements KnowledgeSourceInterface {
	/** @return string */
	public function getKey(): string {
		return 'membership';
	}

	/**
	 * Searches structured membership information from an integration filter.
	 *
	 * Integrations should return arrays containing title, content, category, url,
	 * priority, and enabled fields. This keeps prices and benefits owned by the
	 * existing membership service instead of duplicating them in ADAM BOT.
	 *
	 * @param string $query User question.
	 * @return array<int, KnowledgeResult>
	 */
	public function search( string $query ): array {
		/**
		 * Filters authoritative membership knowledge items.
		 *
		 * @param array<int, array<string, mixed>> $items Membership items.
		 * @param string                           $query Current user question.
		 */
		$items = apply_filters( 'adam_bot_knowledge_membership_items', array(), $query );

		if ( ! is_array( $items ) ) {
			return array();
		}

		$results = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ( isset( $item['enabled'] ) && ! $item['enabled'] ) ) {
				continue;
			}

			$title    = $this->clean( (string) ( $item['title'] ?? '' ) );
			$content  = $this->clean( (string) ( $item['content'] ?? '' ) );
			$category = $this->clean( (string) ( $item['category'] ?? __( 'Sócios', 'adam-bot' ) ) );
			$priority = max( 0, min( 100, (int) ( $item['priority'] ?? 50 ) ) );

			if ( '' !== $content ) {
				$results[] = new KnowledgeResult(
					$this->getKey(),
					__( 'Informação de sócios ADAM', 'adam-bot' ),
					$title,
					$content,
					$category,
					(string) ( $item['url'] ?? '' ),
					0,
					array(),
					$priority,
					array(
						'keywords'        => $item['keywords'] ?? array(),
						'synonyms'        => $item['synonyms'] ?? array(),
						'search_weight'   => $item['search_weight'] ?? 100,
						'button_label'    => $item['button_text'] ?? '',
						'response_blocks' => $item['response_blocks'] ?? array(),
						'related'         => $item['related'] ?? array(),
						'object_id'       => $item['object_id'] ?? 0,
					)
				);
			}
		}

		return $results;
	}

	/** @return string */
	private function clean( string $value ): string {
		return trim( wp_strip_all_tags( strip_shortcodes( $value ) ) );
	}
}
