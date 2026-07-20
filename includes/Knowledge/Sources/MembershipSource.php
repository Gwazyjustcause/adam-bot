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
use AdamBot\Knowledge\Search\KeywordMatcher;

defined( 'ABSPATH' ) || exit;

/**
 * Consumes authoritative membership data supplied by the ADAM ecosystem.
 */
final class MembershipSource implements KnowledgeSourceInterface {
	/** @var KeywordMatcher */
	private $matcher;

	/** @param KeywordMatcher $matcher Keyword matcher. */
	public function __construct( KeywordMatcher $matcher ) {
		$this->matcher = $matcher;
	}

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

		$has_intent = $this->matcher->hasIntent(
			$query,
			array( 'membership', 'member', 'socio', 'sócio', 'quota', 'aderente', 'efetivo', 'join', 'renew', 'renovar', 'benefit', 'benefício' )
		);
		$results = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ( isset( $item['enabled'] ) && ! $item['enabled'] ) ) {
				continue;
			}

			$title    = $this->clean( (string) ( $item['title'] ?? '' ) );
			$content  = $this->clean( (string) ( $item['content'] ?? '' ) );
			$category = $this->clean( (string) ( $item['category'] ?? __( 'Membership', 'adam-bot' ) ) );
			$priority = max( 0, min( 15, (int) ( $item['priority'] ?? 0 ) ) );
			$score    = $this->matcher->score( $query, $title, $content, $category, 17, $priority );

			if ( $has_intent && '' !== $content ) {
				$score = max( $score, 36 + $priority );
			}

			if ( $score > 0 && '' !== $content ) {
				$results[] = new KnowledgeResult(
					$this->getKey(),
					__( 'ADAM membership information', 'adam-bot' ),
					$title,
					$content,
					$category,
					(string) ( $item['url'] ?? '' ),
					$score
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
