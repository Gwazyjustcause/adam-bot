<?php
/**
 * Similar-entry detection.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

use AdamBot\Knowledge\Search\KeywordMatcher;

defined( 'ABSPATH' ) || exit;

/** Provides explainable, deterministic duplicate warnings without external services. */
final class DuplicateDetector {
	/** @var KeywordMatcher */
	private $matcher;

	public function __construct( KeywordMatcher $matcher ) {
		$this->matcher = $matcher;
	}

	/** @return array<int, array<string, int|string>> */
	public function find( string $question, int $exclude_id = 0, int $limit = 3 ): array {
		$question = $this->matcher->normalize( $question );
		if ( '' === $question ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'adam_bot_knowledge',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		$matches = array();
		foreach ( $posts as $post ) {
			if ( (int) $post->ID === $exclude_id ) {
				continue;
			}
			$candidate = (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true );
			$candidate = '' !== trim( $candidate ) ? $candidate : (string) $post->post_title;
			$score     = $this->similarity( $question, $this->matcher->normalize( $candidate ) );
			if ( $score >= 72 ) {
				$matches[] = array(
					'id'         => (int) $post->ID,
					'question'   => sanitize_text_field( $candidate ),
					'similarity' => $score,
				);
			}
		}

		usort( $matches, static function ( array $left, array $right ): int { return (int) $right['similarity'] <=> (int) $left['similarity']; } );

		return array_slice( $matches, 0, max( 1, $limit ) );
	}

	public function similarity( string $left, string $right ): int {
		if ( '' === $left || '' === $right ) {
			return 0;
		}
		if ( $left === $right ) {
			return 100;
		}

		$left_terms  = array_values( array_unique( preg_split( '/\s+/u', $left, -1, PREG_SPLIT_NO_EMPTY ) ?: array() ) );
		$right_terms = array_values( array_unique( preg_split( '/\s+/u', $right, -1, PREG_SPLIT_NO_EMPTY ) ?: array() ) );
		$union       = array_unique( array_merge( $left_terms, $right_terms ) );
		$intersection = array_intersect( $left_terms, $right_terms );
		$jaccard     = empty( $union ) ? 0 : count( $intersection ) / count( $union );
		similar_text( $left, $right, $character_similarity );

		return (int) round( min( 100, ( $jaccard * 65 ) + ( $character_similarity * 0.35 ) ) );
	}
}
