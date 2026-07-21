<?php
/**
 * Knowledge metadata revision integration.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/** Adds entry metadata to native WordPress revisions and restores. */
final class RevisionManager {
	/** @return void */
	public function register_hooks(): void {
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'revision_meta_keys' ), 10, 2 );
		add_action( '_wp_put_post_revision', array( $this, 'copy_meta_to_revision' ) );
		add_action( 'wp_restore_post_revision', array( $this, 'restore_meta' ), 10, 2 );
		add_filter( 'wp_get_revision_ui_diff', array( $this, 'revision_diff' ), 10, 3 );
	}

	/** @param array<int,string> $keys Existing keys. @param string $post_type Post type. @return array<int,string> */
	public function revision_meta_keys( array $keys, string $post_type ): array {
		if ( in_array( $post_type, array( 'adam_bot_knowledge', 'adam_bot_faq' ), true ) ) {
			$keys = array_values( array_unique( array_merge( $keys, EntrySchema::revisionMetaKeys() ) ) );
		}
		return $keys;
	}

	/** WordPress 6.3 compatibility for metadata revisions. */
	public function copy_meta_to_revision( int $revision_id ): void {
		$parent_id = function_exists( 'wp_is_post_revision' ) ? (int) wp_is_post_revision( $revision_id ) : 0;
		if ( $parent_id <= 0 ) {
			return;
		}
		foreach ( EntrySchema::revisionMetaKeys() as $key ) {
			$value = get_post_meta( $parent_id, $key, true );
			if ( '' !== $value && array() !== $value ) {
				update_metadata( 'post', $revision_id, $key, $value );
			}
		}
	}

	public function restore_meta( int $post_id, int $revision_id ): void {
		foreach ( EntrySchema::revisionMetaKeys() as $key ) {
			$value = get_metadata( 'post', $revision_id, $key, true );
			if ( '' === $value || array() === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
		do_action( 'adam_bot_knowledge_invalidate_cache' );
	}

	/** @param array<int,array<string,string>> $fields Diff fields. @param object $compare_from Previous revision. @param object $compare_to New revision. @return array<int,array<string,string>> */
	public function revision_diff( array $fields, $compare_from, $compare_to ): array {
		$labels = array(
			EntrySchema::QUESTION_META      => __( 'Question', 'adam-bot' ),
			EntrySchema::KEYWORDS_META      => __( 'Keywords', 'adam-bot' ),
			EntrySchema::SYNONYMS_META      => __( 'Synonyms', 'adam-bot' ),
			EntrySchema::PRIORITY_META      => __( 'Priority', 'adam-bot' ),
			EntrySchema::SEARCH_WEIGHT_META => __( 'Search Weight', 'adam-bot' ),
			EntrySchema::VISIBILITY_META    => __( 'Visibility', 'adam-bot' ),
			EntrySchema::BUTTON_TEXT_META   => __( 'Button text', 'adam-bot' ),
			EntrySchema::BUTTON_URL_META    => __( 'Button URL', 'adam-bot' ),
			EntrySchema::RESPONSE_BLOCKS_META => __( 'Response blocks', 'adam-bot' ),
		);

		foreach ( $labels as $key => $label ) {
			$from = $this->displayValue( get_post_meta( (int) $compare_from->ID, $key, true ) );
			$to   = $this->displayValue( get_post_meta( (int) $compare_to->ID, $key, true ) );
			if ( $from === $to ) {
				continue;
			}
			$fields[] = array(
				'id'   => 'adam-bot-' . sanitize_key( $key ),
				'name' => $label,
				'diff' => function_exists( 'wp_text_diff' ) ? wp_text_diff( $from, $to ) : esc_html( $from . ' → ' . $to ),
			);
		}
		return $fields;
	}

	/** @param mixed $value Stored value. */
	private function displayValue( $value ): string {
		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		return (string) $value;
	}
}
