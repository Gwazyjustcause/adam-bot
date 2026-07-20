<?php
/**
 * FAQ knowledge source.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Sources;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\KnowledgeSourceInterface;

defined( 'ABSPATH' ) || exit;

/** Searches enabled, published FAQ entries. */
final class FAQSource implements KnowledgeSourceInterface {
	/** FAQ custom post type. */
	public const POST_TYPE = 'adam_bot_faq';

	/** Entry category meta key. */
	public const CATEGORY_META = '_adam_bot_category';

	/** Entry priority meta key. */
	public const PRIORITY_META = '_adam_bot_priority';

	/** Entry enabled meta key. */
	public const ENABLED_META = '_adam_bot_enabled';

	/** @return string */
	public function getKey(): string {
		return 'faq';
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
				'posts_per_page' => 100,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'no_found_rows'  => true,
			)
		);
		$results = array();

		foreach ( $posts as $post ) {
			if ( '0' === (string) get_post_meta( $post->ID, self::ENABLED_META, true ) ) {
				continue;
			}

			$title       = $this->clean( (string) $post->post_title );
			$content     = $this->clean( (string) $post->post_content );
			$category    = $this->clean( (string) get_post_meta( $post->ID, self::CATEGORY_META, true ) );
			$priority    = max( 0, min( 100, (int) get_post_meta( $post->ID, self::PRIORITY_META, true ) ) );
			$entry_priority = (int) round( $priority * 0.15 );

			if ( '' !== $content ) {
				$results[] = new KnowledgeResult(
					$this->getKey(),
					__( 'ADAM FAQ', 'adam-bot' ),
					$title,
					$content,
					$category,
					'',
					0,
					array(),
					$entry_priority
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
