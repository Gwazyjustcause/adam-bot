<?php
/**
 * Manual-entry knowledge source.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Sources;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\EntrySchema;
use AdamBot\Knowledge\KnowledgeSourceInterface;

defined( 'ABSPATH' ) || exit;

/** Searches administrator-authored knowledge entries. */
final class ManualSource implements KnowledgeSourceInterface {
	/** Manual knowledge custom post type. */
	public const POST_TYPE = 'adam_bot_knowledge';

	/** @return string */
	public function getKey(): string {
		return 'manual';
	}

	/**
	 * @param string $query User question.
	 * @return array<int, KnowledgeResult>
	 */
	public function search( string $query ): array {
		unset( $query );
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$results = array();

		foreach ( $posts as $post ) {
			if ( '0' === (string) get_post_meta( $post->ID, FAQSource::ENABLED_META, true ) || 'hidden' === (string) get_post_meta( $post->ID, EntrySchema::VISIBILITY_META, true ) ) {
				continue;
			}

			$title    = $this->clean( (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true ) );
			$title    = '' !== $title ? $title : $this->clean( (string) $post->post_title );
			$blocks   = EntrySchema::sanitizeBlocks( get_post_meta( $post->ID, EntrySchema::RESPONSE_BLOCKS_META, true ) );
			$content  = ! empty( $blocks ) ? EntrySchema::blocksToText( $blocks ) : $this->clean( (string) $post->post_content );
			$category = $this->category( $post );
			$priority = '' === (string) get_post_meta( $post->ID, EntrySchema::PRIORITY_META, true ) ? 50 : (int) get_post_meta( $post->ID, EntrySchema::PRIORITY_META, true );
			$url      = esc_url_raw( (string) get_post_meta( $post->ID, EntrySchema::BUTTON_URL_META, true ) );
			$page_id  = absint( get_post_meta( $post->ID, EntrySchema::RELATED_PAGE_META, true ) );
			if ( '' === $url && $page_id > 0 ) {
				$url = (string) get_permalink( $page_id );
			}
			if ( '' !== $content ) {
				$results[] = new KnowledgeResult(
					$this->getKey(),
					__( 'Entrada de conhecimento ADAM', 'adam-bot' ),
					$title,
					$content,
					$category,
					$url,
					0,
					array(),
					$priority,
					array(
						'keywords'        => get_post_meta( $post->ID, EntrySchema::KEYWORDS_META, true ),
						'synonyms'        => get_post_meta( $post->ID, EntrySchema::SYNONYMS_META, true ),
						'search_weight'   => '' === (string) get_post_meta( $post->ID, EntrySchema::SEARCH_WEIGHT_META, true ) ? 100 : (int) get_post_meta( $post->ID, EntrySchema::SEARCH_WEIGHT_META, true ),
						'button_label'    => get_post_meta( $post->ID, EntrySchema::BUTTON_TEXT_META, true ),
						'response_blocks' => $blocks,
						'related'         => $this->related( $post ),
						'object_id'       => (int) $post->ID,
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

	/** @param object $post Knowledge post. */
	private function category( $post ): string {
		if ( function_exists( 'get_the_terms' ) ) {
			$terms = get_the_terms( $post->ID, EntrySchema::TAXONOMY );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				return implode( ', ', array_map( static function ( $term ): string { return sanitize_text_field( (string) $term->name ); }, $terms ) );
			}
		}
		return $this->clean( (string) get_post_meta( $post->ID, FAQSource::CATEGORY_META, true ) );
	}

	/** @param object $post Knowledge post. @return array<int, array<string, string>> */
	private function related( $post ): array {
		$related = array();
		foreach ( EntrySchema::sanitizeRelatedIds( get_post_meta( $post->ID, EntrySchema::RELATED_ENTRIES_META, true ), (int) $post->ID ) as $related_id ) {
			$related_post = function_exists( 'get_post' ) ? get_post( $related_id ) : null;
			if ( ! is_object( $related_post ) || 'publish' !== (string) ( $related_post->post_status ?? '' ) ) {
				continue;
			}
			$question  = $this->clean( (string) get_post_meta( $related_id, EntrySchema::QUESTION_META, true ) );
			$question  = '' !== $question ? $question : $this->clean( (string) $related_post->post_title );
			$related[] = array( 'title' => $question, 'question' => $question );
		}
		return $related;
	}
}
