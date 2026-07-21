<?php
/**
 * Event knowledge source adapter.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Sources;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\KnowledgeSourceInterface;
use AdamBot\Knowledge\Response\Component\EventCard;

defined( 'ABSPATH' ) || exit;

/**
 * Searches existing event post types and accepts repository-backed event data.
 */
final class EventSource implements KnowledgeSourceInterface {
	/** @return string */
	public function getKey(): string {
		return 'event';
	}

	/**
	 * Searches structured integrations and published event posts.
	 *
	 * @param string $query User question.
	 * @return array<int, KnowledgeResult>
	 */
	public function search( string $query ): array {
		$results = $this->searchIntegratedItems( $query );

		foreach ( $this->eventPostTypes() as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);

			foreach ( $posts as $post ) {
				$item = apply_filters(
					'adam_bot_knowledge_event_post_item',
					array(
						'title'     => (string) $post->post_title,
						'content'   => (string) ( $post->post_excerpt ?? '' ) . "\n" . (string) $post->post_content,
						'url'       => (string) get_permalink( $post ),
						'object_id' => (int) $post->ID,
					),
					$post,
					$query
				);
				if ( ! is_array( $item ) || ( isset( $item['public'] ) && ! $item['public'] ) || ( isset( $item['enabled'] ) && ! $item['enabled'] ) ) {
					continue;
				}
				$title    = $this->clean( (string) ( $item['title'] ?? '' ) );
				$date     = $this->clean( (string) ( $item['date'] ?? $item['start_date'] ?? '' ) );
				$location = $this->clean( (string) ( $item['location'] ?? '' ) );
				$price    = $this->clean( (string) ( $item['price'] ?? '' ) );
				$content  = $this->eventContent(
					$this->clean( (string) ( $item['content'] ?? '' ) ),
					$date,
					$location,
					$price
				);
				$priority = max( $this->datePriority( $date ), max( 0, min( 100, (int) ( $item['priority'] ?? 50 ) ) ) );

				if ( '' !== $title && '' !== $content ) {
					$results[] = new KnowledgeResult(
						$this->getKey(),
						__( 'ADAM event information', 'adam-bot' ),
						$title,
						$content,
						__( 'Events', 'adam-bot' ),
						esc_url_raw( (string) ( $item['url'] ?? $item['registration_url'] ?? '' ) ),
						0,
						array(),
						$priority,
						array(
							'object_id'     => (int) ( $item['object_id'] ?? $post->ID ),
							'keywords'      => array_merge( array( 'event', 'events' ), isset( $item['keywords'] ) && is_array( $item['keywords'] ) ? $item['keywords'] : array() ),
							'synonyms'      => $item['synonyms'] ?? array(),
							'search_weight' => $item['search_weight'] ?? 100,
							'button_label'  => $item['button_text'] ?? '',
							'components'    => array( ( new EventCard( $item ) )->toArray() ),
						)
					);
				}
			}
		}

		return $results;
	}

	/**
	 * Searches authoritative event items supplied by another ADAM component.
	 *
	 * @param string $query User question.
	 * @return array<int, KnowledgeResult>
	 */
	private function searchIntegratedItems( string $query ): array {
		/**
		 * Filters authoritative event knowledge items.
		 *
		 * Items may contain title, content, date, location, price, category, url,
		 * priority, and enabled. Existing event services can adapt their repository
		 * records here without copying them into ADAM BOT.
		 *
		 * @param array<int, array<string, mixed>> $items Event items.
		 * @param string                           $query Current user question.
		 */
		$items = apply_filters( 'adam_bot_knowledge_event_items', array(), $query );
		$items = is_array( $items ) ? $items : array();
		$results = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ( isset( $item['public'] ) && ! $item['public'] ) || ( isset( $item['enabled'] ) && ! $item['enabled'] ) ) {
				continue;
			}

			$title    = $this->clean( (string) ( $item['title'] ?? '' ) );
			$date     = $this->clean( (string) ( $item['date'] ?? '' ) );
			$category = $this->clean( (string) ( $item['category'] ?? __( 'Events', 'adam-bot' ) ) );
			$content  = $this->eventContent(
				$this->clean( (string) ( $item['content'] ?? '' ) ),
				$date,
				$this->clean( (string) ( $item['location'] ?? '' ) ),
				$this->clean( (string) ( $item['price'] ?? '' ) )
			);
			$priority = max( $this->datePriority( $date ), max( 0, min( 100, (int) ( $item['priority'] ?? 50 ) ) ) );

			if ( '' !== $content ) {
				$results[] = new KnowledgeResult(
					$this->getKey(),
					__( 'ADAM event information', 'adam-bot' ),
					$title,
					$content,
					$category,
					esc_url_raw( (string) ( $item['url'] ?? $item['registration_url'] ?? '' ) ),
					0,
					array(),
					$priority,
					array(
						'keywords'        => array_merge( array( 'event', 'events' ), isset( $item['keywords'] ) && is_array( $item['keywords'] ) ? $item['keywords'] : array() ),
						'synonyms'        => $item['synonyms'] ?? array(),
						'search_weight'   => $item['search_weight'] ?? 100,
						'button_label'    => $item['button_text'] ?? '',
						'response_blocks' => $item['response_blocks'] ?? array(),
						'related'         => $item['related'] ?? array(),
						'object_id'       => $item['object_id'] ?? 0,
						'components'      => array( ( new EventCard( $item ) )->toArray() ),
					)
				);
			}
		}

		return $results;
	}

	/** @return array<int, string> */
	private function eventPostTypes(): array {
		/**
		 * Filters event post types already registered by the ADAM ecosystem.
		 *
		 * @param array<int, string> $post_types Candidate event post types.
		 */
		$post_types = apply_filters( 'adam_bot_knowledge_event_post_types', array() );
		$post_types = is_array( $post_types ) ? $post_types : array();

		return array_values(
			array_filter(
				array_unique( array_map( 'sanitize_key', $post_types ) ),
				static function ( string $post_type ): bool {
					return post_type_exists( $post_type );
				}
			)
		);
	}

	/**
	 * Adds structured event fields to searchable and promptable content.
	 *
	 * @return string
	 */
	private function eventContent( string $content, string $date, string $location, string $price ): string {
		$parts = array_filter(
			array(
				$content,
				'' !== $date ? sprintf( __( 'Date: %s', 'adam-bot' ), $date ) : '',
				'' !== $location ? sprintf( __( 'Location: %s', 'adam-bot' ), $location ) : '',
				'' !== $price ? sprintf( __( 'Price: %s', 'adam-bot' ), $price ) : '',
			)
		);

		return implode( "\n", $parts );
	}

	/**
	 * Gives upcoming events a small deterministic recency boost.
	 *
	 * @param string $date Event date.
	 * @return int
	 */
	private function datePriority( string $date ): int {
		$timestamp = strtotime( $date );

		if ( false === $timestamp || $timestamp < time() ) {
			return 0;
		}

		$days = (int) floor( ( $timestamp - time() ) / DAY_IN_SECONDS );

		return max( 2, 15 - min( 13, (int) floor( $days / 14 ) ) );
	}

	/** @return string */
	private function clean( string $value ): string {
		return trim( wp_strip_all_tags( strip_shortcodes( $value ) ) );
	}
}
