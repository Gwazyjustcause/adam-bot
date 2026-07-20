<?php
/**
 * Manual-entry knowledge source.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Sources;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\KnowledgeSourceInterface;
use AdamBot\Knowledge\Search\KeywordMatcher;

defined( 'ABSPATH' ) || exit;

/** Searches administrator-authored knowledge entries. */
final class ManualSource implements KnowledgeSourceInterface {
	/** Manual knowledge custom post type. */
	public const POST_TYPE = 'adam_bot_knowledge';

	/** @var KeywordMatcher */
	private $matcher;

	/** @param KeywordMatcher $matcher Keyword matcher. */
	public function __construct( KeywordMatcher $matcher ) {
		$this->matcher = $matcher;
	}

	/** @return string */
	public function getKey(): string {
		return 'manual';
	}

	/**
	 * @param string $query User question.
	 * @return array<int, KnowledgeResult>
	 */
	public function search( string $query ): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$results = array();

		foreach ( $posts as $post ) {
			if ( '0' === (string) get_post_meta( $post->ID, FAQSource::ENABLED_META, true ) ) {
				continue;
			}

			$title    = $this->clean( (string) $post->post_title );
			$content  = $this->clean( (string) $post->post_content );
			$category = $this->clean( (string) get_post_meta( $post->ID, FAQSource::CATEGORY_META, true ) );
			$score    = $this->matcher->score( $query, $title, $content, $category, 14 );

			if ( $score > 0 && '' !== $content ) {
				$results[] = new KnowledgeResult(
					$this->getKey(),
					__( 'ADAM knowledge entry', 'adam-bot' ),
					$title,
					$content,
					$category,
					'',
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
