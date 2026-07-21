<?php
/**
 * JSON and CSV knowledge transfer.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/** Streams safe exports and imports bounded administrator uploads. */
final class ImportExport {
	private const NONCE_ACTION = 'adam_bot_knowledge_transfer';

	/** @return void */
	public function register_hooks(): void {
		add_action( 'admin_post_adam_bot_export_knowledge', array( $this, 'export' ) );
		add_action( 'admin_post_adam_bot_import_knowledge', array( $this, 'import' ) );
	}

	public function export(): void {
		$this->authorize();
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( (string) $_GET['format'] ) ) : 'json';
		$format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';
		$rows   = $this->rows();
		$stamp  = gmdate( 'Y-m-d-His' );

		nocache_headers();
		header( 'Content-Disposition: attachment; filename="adam-bot-knowledge-' . $stamp . '.' . $format . '"' );
		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			$output = fopen( 'php://output', 'wb' );
			if ( false !== $output ) {
				fputcsv( $output, array_keys( $this->emptyRow() ) );
				foreach ( $rows as $row ) {
					fputcsv( $output, array_values( $row ) );
				}
				fclose( $output );
			}
		} else {
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode(
				array( 'schema' => 1, 'exported_at' => gmdate( 'c' ), 'entries' => $rows ),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Downloaded JSON.
		}
		exit;
	}

	public function import(): void {
		$this->authorize();
		$file = $_FILES['adam_bot_import_file'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || (int) ( $file['size'] ?? 0 ) > 5 * MB_IN_BYTES ) {
			$this->redirect( 'invalid_file' );
		}

		$path      = (string) ( $file['tmp_name'] ?? '' );
		$extension = strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'json', 'csv' ), true ) || ! is_uploaded_file( $path ) ) {
			$this->redirect( 'invalid_file' );
		}

		$rows = 'json' === $extension ? $this->readJson( $path ) : $this->readCsv( $path );
		if ( empty( $rows ) ) {
			$this->redirect( 'empty_file' );
		}

		$count   = 0;
		$id_map  = array();
		$pending = array();
		foreach ( array_slice( $rows, 0, 5000 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$new_id = $this->importRow( $row );
			if ( $new_id <= 0 ) {
				continue;
			}
			$external_id = absint( $row['external_id'] ?? 0 );
			if ( $external_id > 0 ) {
				$id_map[ $external_id ] = $new_id;
			}
			$pending[] = array( 'id' => $new_id, 'row' => $row );
			$count++;
		}
		foreach ( $pending as $item ) {
			$old_related = EntrySchema::sanitizeRelatedIds( explode( '|', (string) ( $item['row']['related_entries'] ?? '' ) ) );
			$new_related = array();
			foreach ( $old_related as $old_id ) {
				if ( isset( $id_map[ $old_id ] ) ) {
					$new_related[] = (int) $id_map[ $old_id ];
				}
			}
			update_post_meta( (int) $item['id'], EntrySchema::RELATED_ENTRIES_META, $new_related );
		}
		do_action( 'adam_bot_knowledge_invalidate_cache' );
		$this->redirect( 'imported', $count );
	}

	/** @return array<int,array<string,string>> */
	private function rows(): array {
		$posts = get_posts(
			array(
				'post_type'      => array( 'adam_bot_knowledge', 'adam_bot_faq' ),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$rows = array();
		foreach ( $posts as $post ) {
			$term_objects = function_exists( 'wp_get_object_terms' ) ? wp_get_object_terms( $post->ID, EntrySchema::TAXONOMY ) : array();
			$term_objects = is_array( $term_objects ) ? $term_objects : array();
			$terms         = array();
			$category_meta = array();
			foreach ( $term_objects as $term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}
				$name = sanitize_text_field( (string) $term->name );
				$terms[] = $name;
				$category_meta[ $name ] = array(
					'color' => (string) get_term_meta( (int) $term->term_id, EntrySchema::TERM_COLOR_META, true ),
					'icon'  => (string) get_term_meta( (int) $term->term_id, EntrySchema::TERM_ICON_META, true ),
				);
			}
			$question   = (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true );
			$rows[] = array(
				'type'            => 'adam_bot_faq' === $post->post_type ? 'faq' : 'knowledge',
				'external_id'     => (string) $post->ID,
				'title'           => (string) $post->post_title,
				'question'        => '' !== $question ? $question : (string) $post->post_title,
				'answer'          => (string) $post->post_content,
				'status'          => (string) $post->post_status,
				'visibility'      => (string) ( get_post_meta( $post->ID, EntrySchema::VISIBILITY_META, true ) ?: 'published' ),
				'categories'      => implode( '|', array_map( 'strval', $terms ) ),
				'category_metadata'=> (string) wp_json_encode( $category_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'keywords'        => implode( '|', EntrySchema::sanitizeTerms( get_post_meta( $post->ID, EntrySchema::KEYWORDS_META, true ) ) ),
				'synonyms'        => implode( '|', EntrySchema::sanitizeTerms( get_post_meta( $post->ID, EntrySchema::SYNONYMS_META, true ) ) ),
				'priority'        => (string) ( get_post_meta( $post->ID, EntrySchema::PRIORITY_META, true ) ?: 50 ),
				'search_weight'   => (string) ( get_post_meta( $post->ID, EntrySchema::SEARCH_WEIGHT_META, true ) ?: 100 ),
				'related_page'    => (string) get_post_meta( $post->ID, EntrySchema::RELATED_PAGE_META, true ),
				'button_text'     => (string) get_post_meta( $post->ID, EntrySchema::BUTTON_TEXT_META, true ),
				'button_url'      => (string) get_post_meta( $post->ID, EntrySchema::BUTTON_URL_META, true ),
				'related_entries' => implode( '|', EntrySchema::sanitizeRelatedIds( get_post_meta( $post->ID, EntrySchema::RELATED_ENTRIES_META, true ) ) ),
				'response_blocks' => (string) wp_json_encode( EntrySchema::sanitizeBlocks( get_post_meta( $post->ID, EntrySchema::RESPONSE_BLOCKS_META, true ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'order'           => (string) ( $post->menu_order ?? 0 ),
			);
		}
		return $rows;
	}

	/** @return array<string,string> */
	private function emptyRow(): array {
		return array_fill_keys( array( 'type', 'external_id', 'title', 'question', 'answer', 'status', 'visibility', 'categories', 'category_metadata', 'keywords', 'synonyms', 'priority', 'search_weight', 'related_page', 'button_text', 'button_url', 'related_entries', 'response_blocks', 'order' ), '' );
	}

	/** @return array<int,array<string,mixed>> */
	private function readJson( string $path ): array {
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$rows = $data['entries'] ?? $data;
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/** @return array<int,array<string,string>> */
	private function readCsv( string $path ): array {
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return array();
		}
		$headers = fgetcsv( $handle );
		$rows    = array();
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			return array();
		}
		while ( false !== ( $values = fgetcsv( $handle ) ) ) {
			$values = array_pad( $values, count( $headers ), '' );
			$row    = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}
		fclose( $handle );
		return $rows;
	}

	/** @param array<string,mixed> $row Imported row. @return int Inserted post ID or zero. */
	private function importRow( array $row ): int {
		$type     = 'faq' === sanitize_key( (string) ( $row['type'] ?? '' ) ) ? 'adam_bot_faq' : 'adam_bot_knowledge';
		$title    = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$question = sanitize_text_field( (string) ( $row['question'] ?? $title ) );
		if ( '' === $title || '' === $question ) {
			return 0;
		}
		$status = sanitize_key( (string) ( $row['status'] ?? 'draft' ) );
		$status = in_array( $status, array( 'publish', 'draft', 'private' ), true ) ? $status : 'draft';
		$post_id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_title'   => $title,
				'post_content' => wp_kses_post( (string) ( $row['answer'] ?? '' ) ),
				'post_status'  => $status,
				'menu_order'   => (int) ( $row['order'] ?? 0 ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		$blocks = $row['response_blocks'] ?? array();
		if ( is_string( $blocks ) ) {
			$decoded = json_decode( $blocks, true );
			$blocks  = is_array( $decoded ) ? $decoded : array();
		}
		$meta = array(
			EntrySchema::QUESTION_META        => $question,
			EntrySchema::KEYWORDS_META        => EntrySchema::sanitizeTerms( str_replace( '|', ',', (string) ( $row['keywords'] ?? '' ) ) ),
			EntrySchema::SYNONYMS_META        => EntrySchema::sanitizeTerms( str_replace( '|', ',', (string) ( $row['synonyms'] ?? '' ) ) ),
			EntrySchema::PRIORITY_META        => max( 0, min( 100, (int) ( $row['priority'] ?? 50 ) ) ),
			EntrySchema::SEARCH_WEIGHT_META   => max( 0, min( 200, (int) ( $row['search_weight'] ?? 100 ) ) ),
			EntrySchema::VISIBILITY_META      => 'hidden' === sanitize_key( (string) ( $row['visibility'] ?? '' ) ) ? 'hidden' : 'published',
			EntrySchema::RELATED_PAGE_META    => absint( $row['related_page'] ?? 0 ),
			EntrySchema::BUTTON_TEXT_META     => sanitize_text_field( (string) ( $row['button_text'] ?? '' ) ),
			EntrySchema::BUTTON_URL_META      => esc_url_raw( (string) ( $row['button_url'] ?? '' ) ),
			EntrySchema::RELATED_ENTRIES_META => EntrySchema::sanitizeRelatedIds( explode( '|', (string) ( $row['related_entries'] ?? '' ) ) ),
			EntrySchema::RESPONSE_BLOCKS_META => EntrySchema::sanitizeBlocks( $blocks ),
			EntrySchema::ENABLED_META         => '1',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $post_id, $key, $value );
		}
		$categories = array_filter( array_map( 'sanitize_text_field', explode( '|', (string) ( $row['categories'] ?? '' ) ) ) );
		if ( ! empty( $categories ) ) {
			wp_set_object_terms( (int) $post_id, $categories, EntrySchema::TAXONOMY );
			$category_meta = json_decode( (string) ( $row['category_metadata'] ?? '' ), true );
			$category_meta = is_array( $category_meta ) ? $category_meta : array();
			foreach ( $categories as $category ) {
				$term = get_term_by( 'name', $category, EntrySchema::TAXONOMY );
				if ( ! is_object( $term ) || ! isset( $category_meta[ $category ] ) || ! is_array( $category_meta[ $category ] ) ) {
					continue;
				}
				$color = sanitize_hex_color( (string) ( $category_meta[ $category ]['color'] ?? '' ) );
				$icon  = sanitize_text_field( (string) ( $category_meta[ $category ]['icon'] ?? '' ) );
				update_term_meta( (int) $term->term_id, EntrySchema::TERM_COLOR_META, $color ?: '#2271b1' );
				update_term_meta( (int) $term->term_id, EntrySchema::TERM_ICON_META, $icon );
			}
		}
		return (int) $post_id;
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to transfer knowledge.', 'adam-bot' ) );
		}
		check_admin_referer( self::NONCE_ACTION );
	}

	private function redirect( string $status, int $count = 0 ): void {
		$url = add_query_arg(
			array( 'page' => 'adam-bot-settings', 'adam_bot_transfer' => sanitize_key( $status ), 'count' => max( 0, $count ) ),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
