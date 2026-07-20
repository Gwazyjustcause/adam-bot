<?php
/**
 * Selected WordPress-page knowledge source.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Sources;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\KnowledgeSettings;
use AdamBot\Knowledge\KnowledgeSourceInterface;
use AdamBot\Knowledge\Search\KeywordMatcher;

defined( 'ABSPATH' ) || exit;

/** Searches only pages explicitly selected by an administrator. */
final class PageSource implements KnowledgeSourceInterface {
	/** @var KeywordMatcher */
	private $matcher;

	/** @var KnowledgeSettings */
	private $settings;

	/**
	 * @param KeywordMatcher    $matcher Keyword matcher.
	 * @param KnowledgeSettings $settings Knowledge settings.
	 */
	public function __construct( KeywordMatcher $matcher, KnowledgeSettings $settings ) {
		$this->matcher  = $matcher;
		$this->settings = $settings;
	}

	/** @return string */
	public function getKey(): string {
		return 'page';
	}

	/**
	 * @param string $query User question.
	 * @return array<int, KnowledgeResult>
	 */
	public function search( string $query ): array {
		$page_ids = $this->settings->getPageIds();

		if ( empty( $page_ids ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post__in'       => $page_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $page_ids ),
				'no_found_rows'  => true,
			)
		);
		$results = array();

		foreach ( $posts as $post ) {
			$title   = $this->clean( (string) $post->post_title );
			$content = $this->clean( (string) $post->post_content );
			$score   = $this->matcher->score( $query, $title, $content, __( 'Website page', 'adam-bot' ), 10 );

			if ( $score > 0 && '' !== $content ) {
				$results[] = new KnowledgeResult(
					$this->getKey(),
					sprintf( __( 'ADAM page: %s', 'adam-bot' ), $title ),
					$title,
					$content,
					__( 'Website page', 'adam-bot' ),
					(string) get_permalink( $post ),
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
