<?php
/**
 * Shared schema for administrator-authored knowledge.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/** Keeps the two built-in content providers on one versioned data contract. */
final class EntrySchema {
	/** Shared hierarchical category taxonomy. */
	public const TAXONOMY = 'adam_bot_category';

	public const QUESTION_META = '_adam_bot_question';
	public const KEYWORDS_META = '_adam_bot_keywords';
	public const SYNONYMS_META = '_adam_bot_synonyms';
	public const PRIORITY_META = '_adam_bot_priority';
	public const SEARCH_WEIGHT_META = '_adam_bot_search_weight';
	public const VISIBILITY_META = '_adam_bot_visibility';
	public const ENABLED_META = '_adam_bot_enabled';
	public const RELATED_PAGE_META = '_adam_bot_related_page';
	public const BUTTON_TEXT_META = '_adam_bot_button_text';
	public const BUTTON_URL_META = '_adam_bot_button_url';
	public const RELATED_ENTRIES_META = '_adam_bot_related_entries';
	public const RESPONSE_BLOCKS_META = '_adam_bot_response_blocks';
	public const LEGACY_CATEGORY_META = '_adam_bot_category';

	public const TERM_COLOR_META = '_adam_bot_category_color';
	public const TERM_ICON_META = '_adam_bot_category_icon';

	/** @return array<int, string> */
	public static function revisionMetaKeys(): array {
		return array(
			self::QUESTION_META,
			self::KEYWORDS_META,
			self::SYNONYMS_META,
			self::PRIORITY_META,
			self::SEARCH_WEIGHT_META,
			self::VISIBILITY_META,
			self::RELATED_PAGE_META,
			self::BUTTON_TEXT_META,
			self::BUTTON_URL_META,
			self::RELATED_ENTRIES_META,
			self::RESPONSE_BLOCKS_META,
		);
	}

	/** @param mixed $value Comma- or line-separated terms. @return array<int, string> */
	public static function sanitizeTerms( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,\r\n]+/u', wp_unslash( $value ), -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$terms = array_map(
			static function ( $term ): string {
				return sanitize_text_field( is_scalar( $term ) ? (string) $term : '' );
			},
			$value
		);

		return array_slice( array_values( array_unique( array_filter( $terms ) ) ), 0, 100 );
	}

	/** @param mixed $value Candidate related IDs. @return array<int, int> */
	public static function sanitizeRelatedIds( $value, int $current_id = 0 ): array {
		if ( ! is_array( $value ) ) {
			$value = is_string( $value ) ? preg_split( '/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY ) : array();
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
		$ids = array_values( array_diff( $ids, array( $current_id ) ) );

		return array_slice( $ids, 0, 20 );
	}

	/**
	 * Sanitizes the no-code response builder payload.
	 *
	 * @param mixed $value Candidate blocks.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitizeBlocks( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = array( 'paragraph', 'heading', 'bullet_list', 'numbered_list', 'button', 'link', 'warning', 'information', 'success' );
		$blocks  = array();

		foreach ( $value as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$type = sanitize_key( (string) ( $block['type'] ?? '' ) );
			if ( ! in_array( $type, $allowed, true ) ) {
				continue;
			}

			$text = sanitize_textarea_field( wp_unslash( (string) ( $block['text'] ?? '' ) ) );
			$url  = esc_url_raw( wp_unslash( (string) ( $block['url'] ?? '' ) ) );
			if ( in_array( $type, array( 'button', 'link' ), true ) ) {
				if ( '' === $text || '' === $url ) {
					continue;
				}
			} elseif ( '' === $text ) {
				continue;
			}

			$blocks[] = array(
				'type' => $type,
				'text' => self::truncate( $text, 3000 ),
				'url'  => self::truncate( $url, 1000 ),
			);

			if ( 50 === count( $blocks ) ) {
				break;
			}
		}

		return $blocks;
	}

	/** Converts response blocks into searchable plain text. */
	public static function blocksToText( array $blocks ): string {
		$lines = array();
		foreach ( self::sanitizeBlocks( $blocks ) as $block ) {
			$lines[] = (string) $block['text'];
		}

		return implode( "\n", $lines );
	}

	private static function truncate( string $value, int $maximum ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum ) : substr( $value, 0, $maximum );
	}
}
