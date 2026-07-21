<?php
/**
 * Migrates the legacy FAQ post type into the canonical Knowledge store.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;

defined( 'ABSPATH' ) || exit;

/** Runs a bounded, resumable migration without locking administrator content. */
final class KnowledgeMigration {
	public const CRON_HOOK = 'adam_bot_migrate_legacy_knowledge';
	private const OPTION_KEY = 'adam_bot_knowledge_schema_version';
	private const SCHEMA_VERSION = 2;

	public function register_hooks(): void {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( $this, 'run' ) );
	}

	public function maybeSchedule(): void {
		if ( (int) get_option( self::OPTION_KEY, 0 ) >= self::SCHEMA_VERSION ) {
			return;
		}
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	/** Moves at most 100 FAQ records per request and initializes canonical metadata. */
	public function run(): void {
		if ( (int) get_option( self::OPTION_KEY, 0 ) >= self::SCHEMA_VERSION ) {
			return;
		}
		$legacy = get_posts(
			array(
				'post_type'      => FAQSource::LEGACY_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 100,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		foreach ( is_array( $legacy ) ? $legacy : array() as $post ) {
			if ( $this->removeExactGeneratedDuplicate( $post ) ) {
				continue;
			}
			$result = wp_update_post( array( 'ID' => (int) $post->ID, 'post_type' => ManualSource::POST_TYPE ), true );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			update_post_meta( (int) $post->ID, EntrySchema::ENTRY_TYPE_META, 'faq' );
			if ( '' === (string) get_post_meta( (int) $post->ID, EntrySchema::SOURCE_META, true ) ) {
				update_post_meta( (int) $post->ID, EntrySchema::SOURCE_META, 'faq_import' );
			}
			if ( '' === (string) get_post_meta( (int) $post->ID, EntrySchema::LANGUAGE_META, true ) ) {
				update_post_meta( (int) $post->ID, EntrySchema::LANGUAGE_META, 'pt' );
			}
		}

		if ( count( $legacy ) >= 100 ) {
			$this->maybeSchedule();
			return;
		}

		if ( ! $this->initializeCanonicalEntries() ) {
			$this->scheduleNextBatch();
			return;
		}
		update_option( self::OPTION_KEY, self::SCHEMA_VERSION, false );
		do_action( 'adam_bot_knowledge_invalidate_cache' );
	}

	private function scheduleNextBatch(): void {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	/** Removes only byte-equivalent records created twice by the previous website indexer. */
	private function removeExactGeneratedDuplicate( $legacy_post ): bool {
		if ( '1' !== (string) get_post_meta( (int) $legacy_post->ID, EntrySchema::GENERATED_META, true ) ) { return false; }
		$source_key = (string) get_post_meta( (int) $legacy_post->ID, EntrySchema::SOURCE_KEY_META, true );
		if ( '' === $source_key ) { return false; }
		$matches = get_posts( array( 'post_type' => ManualSource::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 1, 'meta_key' => EntrySchema::SOURCE_KEY_META, 'meta_value' => $source_key, 'no_found_rows' => true ) );
		$canonical = is_array( $matches ) && isset( $matches[0] ) ? $matches[0] : null;
		if ( ! is_object( $canonical ) || '1' !== (string) get_post_meta( (int) $canonical->ID, EntrySchema::GENERATED_META, true ) ) { return false; }
		$same = (string) $canonical->post_title === (string) $legacy_post->post_title
			&& (string) $canonical->post_content === (string) $legacy_post->post_content
			&& (string) get_post_meta( (int) $canonical->ID, EntrySchema::QUESTION_META, true ) === (string) get_post_meta( (int) $legacy_post->ID, EntrySchema::QUESTION_META, true )
			&& (string) get_post_meta( (int) $canonical->ID, EntrySchema::SOURCE_HASH_META, true ) === (string) get_post_meta( (int) $legacy_post->ID, EntrySchema::SOURCE_HASH_META, true );
		if ( ! $same ) { return false; }
		$source_post = get_post( absint( get_post_meta( (int) $canonical->ID, EntrySchema::SOURCE_POST_META, true ) ) );
		$type = is_object( $source_post ) && 1 === preg_match( '/faq|perguntas-frequentes/i', (string) ( $source_post->post_name ?? '' ) ) ? 'faq' : 'knowledge';
		update_post_meta( (int) $canonical->ID, EntrySchema::ENTRY_TYPE_META, $type );
		wp_delete_post( (int) $legacy_post->ID, true );
		return true;
	}

	/** Backfills type/source values for records created before the unified schema. */
	private function initializeCanonicalEntries(): bool {
		$posts = get_posts(
			array(
				'post_type'      => ManualSource::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 200,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => EntrySchema::ENTRY_TYPE_META, 'compare' => 'NOT EXISTS' ),
					array( 'key' => EntrySchema::SOURCE_META, 'compare' => 'NOT EXISTS' ),
					array( 'key' => EntrySchema::LANGUAGE_META, 'compare' => 'NOT EXISTS' ),
					array( 'key' => EntrySchema::SYNC_STATUS_META, 'compare' => 'NOT EXISTS' ),
				),
				'no_found_rows'  => true,
			)
		);
		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			if ( '' === (string) get_post_meta( (int) $post->ID, EntrySchema::ENTRY_TYPE_META, true ) ) {
				update_post_meta( (int) $post->ID, EntrySchema::ENTRY_TYPE_META, 'knowledge' );
			}
			if ( '' === (string) get_post_meta( (int) $post->ID, EntrySchema::SOURCE_META, true ) ) {
				$source = '1' === (string) get_post_meta( (int) $post->ID, EntrySchema::GENERATED_META, true ) ? 'website' : 'manual';
				update_post_meta( (int) $post->ID, EntrySchema::SOURCE_META, $source );
			}
			if ( '' === (string) get_post_meta( (int) $post->ID, EntrySchema::LANGUAGE_META, true ) ) {
				update_post_meta( (int) $post->ID, EntrySchema::LANGUAGE_META, 'pt' );
			}
			$source = EntrySchema::sanitizeSource( get_post_meta( (int) $post->ID, EntrySchema::SOURCE_META, true ) );
			if ( '' === (string) get_post_meta( (int) $post->ID, EntrySchema::SYNC_STATUS_META, true ) ) {
				// Existing generated records are treated conservatively as edited until a new comparison is accepted.
				update_post_meta( (int) $post->ID, EntrySchema::SYNC_STATUS_META, 'modified' );
			}
			if ( 'website' === $source && '' === (string) get_post_meta( (int) $post->ID, EntrySchema::LAST_INDEXED_META, true ) ) {
				$modified = isset( $post->post_modified_gmt ) ? strtotime( (string) $post->post_modified_gmt . ' UTC' ) : false;
				update_post_meta( (int) $post->ID, EntrySchema::LAST_INDEXED_META, gmdate( 'c', false !== $modified ? $modified : time() ) );
			}
		}

		return count( $posts ) < 200;
	}
}
