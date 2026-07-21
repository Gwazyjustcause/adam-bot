<?php
/**
 * Knowledge administration component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

use AdamBot\Knowledge\Response\ResponseFormatter;
use AdamBot\Knowledge\Search\SearchService;
use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;

defined( 'ABSPATH' ) || exit;

/** Registers the complete no-code knowledge and FAQ management experience. */
final class KnowledgeAdmin {
	public const SETTINGS_GROUP = 'adam_bot_knowledge';
	private const NONCE_ACTION = 'adam_bot_save_knowledge_entry';
	private const NONCE_FIELD = 'adam_bot_knowledge_nonce';
	private const AJAX_NONCE_ACTION = 'adam_bot_knowledge_preview';

	/** @var KnowledgeSettings */
	private $settings;

	/** @var SearchService */
	private $search_service;

	/** @var ResponseFormatter */
	private $response_formatter;

	/** @var DuplicateDetector */
	private $duplicate_detector;

	public function __construct(
		KnowledgeSettings $settings,
		SearchService $search_service,
		ResponseFormatter $response_formatter,
		DuplicateDetector $duplicate_detector
	) {
		$this->settings           = $settings;
		$this->search_service     = $search_service;
		$this->response_formatter = $response_formatter;
		$this->duplicate_detector = $duplicate_detector;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_content_types' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_entry_meta' ), 20, 3 );
		add_action( 'save_post', array( $this, 'maybe_invalidate_content_cache' ), 30, 3 );
		add_action( 'adam_bot_knowledge_invalidate_cache', array( $this->settings, 'bumpCacheVersion' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_adam_bot_search_preview', array( $this, 'search_preview' ) );
		add_action( 'wp_ajax_adam_bot_duplicate_check', array( $this, 'duplicate_check' ) );
		add_action( EntrySchema::TAXONOMY . '_add_form_fields', array( $this, 'render_category_add_fields' ) );
		add_action( EntrySchema::TAXONOMY . '_edit_form_fields', array( $this, 'render_category_edit_fields' ) );
		add_action( 'created_' . EntrySchema::TAXONOMY, array( $this, 'save_category_fields' ) );
		add_action( 'edited_' . EntrySchema::TAXONOMY, array( $this, 'save_category_fields' ) );
		add_action( 'created_' . EntrySchema::TAXONOMY, array( $this->settings, 'bumpCacheVersion' ) );
		add_action( 'edited_' . EntrySchema::TAXONOMY, array( $this->settings, 'bumpCacheVersion' ) );
		add_action( 'delete_' . EntrySchema::TAXONOMY, array( $this->settings, 'bumpCacheVersion' ) );
		add_filter( 'manage_' . ManualSource::POST_TYPE . '_posts_columns', array( $this, 'entry_columns' ) );
		add_filter( 'manage_' . FAQSource::POST_TYPE . '_posts_columns', array( $this, 'entry_columns' ) );
		add_filter( 'manage_edit-' . ManualSource::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_filter( 'manage_edit-' . FAQSource::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'manage_' . ManualSource::POST_TYPE . '_posts_custom_column', array( $this, 'render_entry_column' ), 10, 2 );
		add_action( 'manage_' . FAQSource::POST_TYPE . '_posts_custom_column', array( $this, 'render_entry_column' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . ManualSource::POST_TYPE, array( $this, 'bulk_actions' ) );
		add_filter( 'bulk_actions-edit-' . FAQSource::POST_TYPE, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . ManualSource::POST_TYPE, array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-' . FAQSource::POST_TYPE, array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_list_tools' ) );
		add_filter( 'posts_search', array( $this, 'extend_admin_search' ), 10, 2 );
	}

	/** @return void */
	public function register_content_types(): void {
		$common = array(
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title', 'editor', 'revisions', 'page-attributes' ),
			'taxonomies'          => array( EntrySchema::TAXONOMY ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type(
			ManualSource::POST_TYPE,
			array_merge(
				$common,
				array(
					'labels' => array(
						'name'               => __( 'Knowledge Base', 'adam-bot' ),
						'singular_name'      => __( 'Knowledge Entry', 'adam-bot' ),
						'add_new'            => __( 'Add Entry', 'adam-bot' ),
						'add_new_item'       => __( 'Add Knowledge Entry', 'adam-bot' ),
						'edit_item'          => __( 'Edit Knowledge Entry', 'adam-bot' ),
						'new_item'           => __( 'New Knowledge Entry', 'adam-bot' ),
						'search_items'       => __( 'Search Knowledge Base', 'adam-bot' ),
						'not_found'          => __( 'No knowledge entries found.', 'adam-bot' ),
						'item_updated'       => __( 'Knowledge entry updated.', 'adam-bot' ),
					),
					'menu_icon' => 'dashicons-welcome-learn-more',
				)
			)
		);

		register_post_type(
			FAQSource::POST_TYPE,
			array_merge(
				$common,
				array(
					'labels' => array(
						'name'          => __( 'FAQ', 'adam-bot' ),
						'singular_name' => __( 'FAQ', 'adam-bot' ),
						'add_new'       => __( 'Add FAQ', 'adam-bot' ),
						'add_new_item'  => __( 'Add FAQ', 'adam-bot' ),
						'edit_item'     => __( 'Edit FAQ', 'adam-bot' ),
						'search_items'  => __( 'Search FAQ', 'adam-bot' ),
						'not_found'     => __( 'No FAQ entries found.', 'adam-bot' ),
					),
					'menu_icon' => 'dashicons-editor-help',
				)
			)
		);

		register_taxonomy(
			EntrySchema::TAXONOMY,
			array( ManualSource::POST_TYPE, FAQSource::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Knowledge Categories', 'adam-bot' ),
					'singular_name' => __( 'Knowledge Category', 'adam-bot' ),
					'add_new_item'  => __( 'Add Knowledge Category', 'adam-bot' ),
					'edit_item'     => __( 'Edit Knowledge Category', 'adam-bot' ),
					'search_items'  => __( 'Search Knowledge Categories', 'adam-bot' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'rewrite'           => false,
			)
		);
	}

	/** @return void */
	public function register_menu(): void {
		add_submenu_page(
			'adam-bot',
			__( 'Knowledge Base', 'adam-bot' ),
			__( 'Knowledge Base', 'adam-bot' ),
			'manage_options',
			'edit.php?post_type=' . ManualSource::POST_TYPE
		);
		add_submenu_page(
			'adam-bot',
			__( 'FAQ', 'adam-bot' ),
			__( 'FAQ', 'adam-bot' ),
			'manage_options',
			'edit.php?post_type=' . FAQSource::POST_TYPE
		);
	}

	/** @return void */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			KnowledgeSettings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => $this->settings->defaults(),
			)
		);
	}

	/** @return void */
	public function register_meta_boxes(): void {
		foreach ( array( ManualSource::POST_TYPE, FAQSource::POST_TYPE ) as $post_type ) {
			add_meta_box( 'adam-bot-basic-information', __( 'Basic Information', 'adam-bot' ), array( $this, 'render_basic_box' ), $post_type, 'normal', 'high' );
			add_meta_box( 'adam-bot-response-builder', __( 'Rich Response Builder', 'adam-bot' ), array( $this, 'render_response_builder' ), $post_type, 'normal', 'high' );
			add_meta_box( 'adam-bot-search-fields', __( 'Search', 'adam-bot' ), array( $this, 'render_search_box' ), $post_type, 'normal', 'default' );
			add_meta_box( 'adam-bot-navigation', __( 'Navigation', 'adam-bot' ), array( $this, 'render_navigation_box' ), $post_type, 'normal', 'default' );
			add_meta_box( 'adam-bot-related', __( 'Related Knowledge', 'adam-bot' ), array( $this, 'render_related_box' ), $post_type, 'normal', 'default' );
			add_meta_box( 'adam-bot-search-preview', __( 'Search Preview', 'adam-bot' ), array( $this, 'render_preview_box' ), $post_type, 'side', 'high' );
			add_meta_box( 'adam-bot-duplicates', __( 'Duplicate Detection', 'adam-bot' ), array( $this, 'render_duplicate_box' ), $post_type, 'side', 'default' );
		}
	}

	/** @param object $post Current post. */
	public function render_basic_box( $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$question   = (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true );
		$visibility = (string) get_post_meta( $post->ID, EntrySchema::VISIBILITY_META, true );
		$visibility = in_array( $visibility, array( 'published', 'hidden' ), true ) ? $visibility : 'published';
		?>
		<p><label for="adam-bot-question"><strong><?php esc_html_e( 'Question', 'adam-bot' ); ?></strong></label></p>
		<input class="widefat" type="text" id="adam-bot-question" name="adam_bot_question" maxlength="500" value="<?php echo esc_attr( $question ); ?>" placeholder="<?php esc_attr_e( 'How do I become a member?', 'adam-bot' ); ?>" />
		<p class="description"><?php esc_html_e( 'Use the WordPress editor below for a simple answer, or compose a structured answer in the Rich Response Builder.', 'adam-bot' ); ?></p>
		<p><label for="adam-bot-visibility"><strong><?php esc_html_e( 'Visibility', 'adam-bot' ); ?></strong></label></p>
		<select id="adam-bot-visibility" name="adam_bot_visibility">
			<option value="published" <?php selected( $visibility, 'published' ); ?>><?php esc_html_e( 'Published', 'adam-bot' ); ?></option>
			<option value="hidden" <?php selected( $visibility, 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'adam-bot' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Use the Publish panel to save as Draft. Hidden entries remain editable but are never searched.', 'adam-bot' ); ?></p>
		<?php
	}

	/** @param object $post Current post. */
	public function render_search_box( $post ): void {
		$keywords = EntrySchema::sanitizeTerms( get_post_meta( $post->ID, EntrySchema::KEYWORDS_META, true ) );
		$synonyms = EntrySchema::sanitizeTerms( get_post_meta( $post->ID, EntrySchema::SYNONYMS_META, true ) );
		$priority = '' === (string) get_post_meta( $post->ID, EntrySchema::PRIORITY_META, true ) ? 50 : (int) get_post_meta( $post->ID, EntrySchema::PRIORITY_META, true );
		$weight   = '' === (string) get_post_meta( $post->ID, EntrySchema::SEARCH_WEIGHT_META, true ) ? 100 : (int) get_post_meta( $post->ID, EntrySchema::SEARCH_WEIGHT_META, true );
		?>
		<div class="adam-bot-field-grid">
			<p><label for="adam-bot-keywords"><strong><?php esc_html_e( 'Keywords', 'adam-bot' ); ?></strong></label><textarea class="widefat" id="adam-bot-keywords" name="adam_bot_keywords" rows="3" placeholder="<?php esc_attr_e( 'renew, membership', 'adam-bot' ); ?>"><?php echo esc_textarea( implode( ', ', $keywords ) ); ?></textarea><span class="description"><?php esc_html_e( 'Separate words or phrases with commas.', 'adam-bot' ); ?></span></p>
			<p><label for="adam-bot-synonyms"><strong><?php esc_html_e( 'Synonyms', 'adam-bot' ); ?></strong></label><textarea class="widefat" id="adam-bot-synonyms" name="adam_bot_synonyms" rows="3" placeholder="<?php esc_attr_e( 'renewal, quota', 'adam-bot' ); ?>"><?php echo esc_textarea( implode( ', ', $synonyms ) ); ?></textarea><span class="description"><?php esc_html_e( 'Add alternative words visitors may use.', 'adam-bot' ); ?></span></p>
			<p><label for="adam-bot-priority"><strong><?php esc_html_e( 'Priority', 'adam-bot' ); ?></strong></label><input type="number" id="adam-bot-priority" name="adam_bot_priority" min="0" max="100" value="<?php echo esc_attr( (string) $priority ); ?>" /><span class="description"><?php esc_html_e( 'Default 50. Raise only when this answer should win close matches.', 'adam-bot' ); ?></span></p>
			<p><label for="adam-bot-search-weight"><strong><?php esc_html_e( 'Search Weight', 'adam-bot' ); ?></strong></label><input type="number" id="adam-bot-search-weight" name="adam_bot_search_weight" min="0" max="200" value="<?php echo esc_attr( (string) $weight ); ?>" /><span class="description"><?php esc_html_e( 'Default 100. Most entries should keep this value.', 'adam-bot' ); ?></span></p>
		</div>
		<?php
	}

	/** @param object $post Current post. */
	public function render_navigation_box( $post ): void {
		$page_id     = absint( get_post_meta( $post->ID, EntrySchema::RELATED_PAGE_META, true ) );
		$button_text = (string) get_post_meta( $post->ID, EntrySchema::BUTTON_TEXT_META, true );
		$button_url  = (string) get_post_meta( $post->ID, EntrySchema::BUTTON_URL_META, true );
		$pages       = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title' ) );
		?>
		<div class="adam-bot-field-grid">
			<p><label for="adam-bot-related-page"><strong><?php esc_html_e( 'Related page', 'adam-bot' ); ?></strong></label><select class="widefat" id="adam-bot-related-page" name="adam_bot_related_page"><option value="0"><?php esc_html_e( '— None —', 'adam-bot' ); ?></option><?php foreach ( $pages as $page ) : ?><option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( $page_id, (int) $page->ID ); ?>><?php echo esc_html( get_the_title( $page ) ); ?></option><?php endforeach; ?></select></p>
			<p><label for="adam-bot-button-text"><strong><?php esc_html_e( 'Button text', 'adam-bot' ); ?></strong></label><input class="widefat" type="text" id="adam-bot-button-text" name="adam_bot_button_text" maxlength="100" value="<?php echo esc_attr( $button_text ); ?>" placeholder="<?php esc_attr_e( 'Register', 'adam-bot' ); ?>" /></p>
			<p><label for="adam-bot-button-url"><strong><?php esc_html_e( 'Button URL', 'adam-bot' ); ?></strong></label><input class="widefat" type="url" id="adam-bot-button-url" name="adam_bot_button_url" value="<?php echo esc_attr( $button_url ); ?>" placeholder="/registration/" /><span class="description"><?php esc_html_e( 'Overrides the related page URL when supplied.', 'adam-bot' ); ?></span></p>
		</div>
		<?php
	}

	/** @param object $post Current post. */
	public function render_related_box( $post ): void {
		$selected = EntrySchema::sanitizeRelatedIds( get_post_meta( $post->ID, EntrySchema::RELATED_ENTRIES_META, true ), (int) $post->ID );
		$entries  = get_posts(
			array(
				'post_type'      => array( ManualSource::POST_TYPE, FAQSource::POST_TYPE ),
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'post__not_in'   => array( (int) $post->ID ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		usort(
			$entries,
			static function ( $left, $right ) use ( $selected ): int {
				$left_position  = array_search( (int) $left->ID, $selected, true );
				$right_position = array_search( (int) $right->ID, $selected, true );
				if ( false !== $left_position || false !== $right_position ) {
					$left_position  = false === $left_position ? PHP_INT_MAX : $left_position;
					$right_position = false === $right_position ? PHP_INT_MAX : $right_position;
					return $left_position <=> $right_position;
				}
				return strcasecmp( (string) $left->post_title, (string) $right->post_title );
			}
		);
		?>
		<p class="description"><?php esc_html_e( 'Choose the follow-up questions that should appear after this answer. Drag selected rows in the browser to control their order.', 'adam-bot' ); ?></p>
		<input class="widefat adam-bot-related-filter" type="search" placeholder="<?php esc_attr_e( 'Filter related knowledge…', 'adam-bot' ); ?>" />
		<div class="adam-bot-related-list">
			<?php foreach ( $entries as $entry ) : ?>
				<?php $question = (string) get_post_meta( $entry->ID, EntrySchema::QUESTION_META, true ); $question = '' !== $question ? $question : (string) $entry->post_title; ?>
				<label draggable="true" data-search="<?php echo esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $question ) : strtolower( $question ) ); ?>"><input type="checkbox" name="adam_bot_related_entries[]" value="<?php echo esc_attr( (string) $entry->ID ); ?>" <?php checked( in_array( (int) $entry->ID, $selected, true ) ); ?> /> <?php echo esc_html( $question ); ?> <small>(<?php echo esc_html( 'adam_bot_faq' === $entry->post_type ? __( 'FAQ', 'adam-bot' ) : __( 'Knowledge', 'adam-bot' ) ); ?>)</small></label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** @param object $post Current post. */
	public function render_response_builder( $post ): void {
		$blocks = EntrySchema::sanitizeBlocks( get_post_meta( $post->ID, EntrySchema::RESPONSE_BLOCKS_META, true ) );
		if ( empty( $blocks ) ) {
			$blocks = array( array( 'type' => 'paragraph', 'text' => '', 'url' => '' ) );
		}
		?>
		<p class="description"><?php esc_html_e( 'Build the answer with reusable blocks. No HTML is required. Drag blocks to reorder them.', 'adam-bot' ); ?></p>
		<div id="adam-bot-response-blocks">
			<?php foreach ( $blocks as $index => $block ) : $this->render_response_block( (int) $index, $block ); endforeach; ?>
		</div>
		<p><button type="button" class="button" id="adam-bot-add-block"><?php esc_html_e( 'Add response block', 'adam-bot' ); ?></button></p>
		<template id="adam-bot-response-block-template"><?php $this->render_response_block( 999999, array( 'type' => 'paragraph', 'text' => '', 'url' => '' ) ); ?></template>
		<?php
	}

	/** @param object $post Current post. */
	public function render_preview_box( $post ): void {
		$question = (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true );
		?>
		<p><label for="adam-bot-preview-question"><strong><?php esc_html_e( 'Question', 'adam-bot' ); ?></strong></label></p>
		<textarea class="widefat" id="adam-bot-preview-question" rows="3"><?php echo esc_textarea( $question ); ?></textarea>
		<p><button type="button" class="button button-primary" id="adam-bot-run-preview"><?php esc_html_e( 'Test search', 'adam-bot' ); ?></button></p>
		<div id="adam-bot-preview-result" aria-live="polite"><p class="description"><?php esc_html_e( 'Save the entry, then test a visitor question to see matched keywords, confidence, and the rendered response.', 'adam-bot' ); ?></p></div>
		<?php
	}

	/** @param object $post Current post. */
	public function render_duplicate_box( $post ): void {
		$question = (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true );
		$question = '' !== trim( $question ) ? $question : (string) $post->post_title;
		$matches  = $this->duplicate_detector->find( $question, (int) $post->ID );
		echo '<div id="adam-bot-duplicate-result" aria-live="polite">';
		$this->render_duplicate_matches( $matches );
		echo '</div>';
	}

	/** @param int $index Row index. @param array<string,mixed> $block Block. */
	private function render_response_block( int $index, array $block ): void {
		$type = sanitize_key( (string) ( $block['type'] ?? 'paragraph' ) );
		$text = (string) ( $block['text'] ?? '' );
		$url  = (string) ( $block['url'] ?? '' );
		$types = array(
			'paragraph'    => __( 'Paragraph', 'adam-bot' ),
			'heading'      => __( 'Heading', 'adam-bot' ),
			'bullet_list'  => __( 'Bullet List', 'adam-bot' ),
			'numbered_list'=> __( 'Numbered List', 'adam-bot' ),
			'button'       => __( 'Button', 'adam-bot' ),
			'link'         => __( 'Link', 'adam-bot' ),
			'warning'      => __( 'Warning', 'adam-bot' ),
			'information'  => __( 'Information Box', 'adam-bot' ),
			'success'      => __( 'Success Box', 'adam-bot' ),
		);
		?>
		<div class="adam-bot-response-block" draggable="true">
			<span class="dashicons dashicons-move adam-bot-block-handle" aria-hidden="true"></span>
			<select class="adam-bot-block-type" name="adam_bot_response_blocks[<?php echo esc_attr( (string) $index ); ?>][type]" aria-label="<?php esc_attr_e( 'Block type', 'adam-bot' ); ?>"><?php foreach ( $types as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<textarea name="adam_bot_response_blocks[<?php echo esc_attr( (string) $index ); ?>][text]" rows="3" placeholder="<?php esc_attr_e( 'Block content', 'adam-bot' ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
			<input class="adam-bot-block-url" type="url" name="adam_bot_response_blocks[<?php echo esc_attr( (string) $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://…" />
			<button type="button" class="button-link-delete adam-bot-remove-block"><?php esc_html_e( 'Remove', 'adam-bot' ); ?></button>
		</div>
		<?php
	}

	/** @param int $post_id Post ID. @param object $post Post. @param bool $update Updated. */
	public function save_entry_meta( int $post_id, $post, bool $update ): void {
		unset( $update );
		if ( ! isset( $post->post_type ) || ! in_array( $post->post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$question   = sanitize_text_field( wp_unslash( (string) ( $_POST['adam_bot_question'] ?? '' ) ) );
		$question   = '' !== $question ? $question : sanitize_text_field( (string) $post->post_title );
		$visibility = 'hidden' === sanitize_key( (string) ( $_POST['adam_bot_visibility'] ?? '' ) ) ? 'hidden' : 'published';
		$meta = array(
			EntrySchema::QUESTION_META        => $question,
			EntrySchema::KEYWORDS_META        => EntrySchema::sanitizeTerms( $_POST['adam_bot_keywords'] ?? array() ),
			EntrySchema::SYNONYMS_META        => EntrySchema::sanitizeTerms( $_POST['adam_bot_synonyms'] ?? array() ),
			EntrySchema::PRIORITY_META        => max( 0, min( 100, absint( $_POST['adam_bot_priority'] ?? 50 ) ) ),
			EntrySchema::SEARCH_WEIGHT_META   => max( 0, min( 200, absint( $_POST['adam_bot_search_weight'] ?? 100 ) ) ),
			EntrySchema::VISIBILITY_META      => $visibility,
			EntrySchema::ENABLED_META         => 'hidden' === $visibility ? '0' : '1',
			EntrySchema::RELATED_PAGE_META    => absint( $_POST['adam_bot_related_page'] ?? 0 ),
			EntrySchema::BUTTON_TEXT_META     => sanitize_text_field( wp_unslash( (string) ( $_POST['adam_bot_button_text'] ?? '' ) ) ),
			EntrySchema::BUTTON_URL_META      => esc_url_raw( wp_unslash( (string) ( $_POST['adam_bot_button_url'] ?? '' ) ) ),
			EntrySchema::RELATED_ENTRIES_META => EntrySchema::sanitizeRelatedIds( $_POST['adam_bot_related_entries'] ?? array(), $post_id ),
			EntrySchema::RESPONSE_BLOCKS_META => EntrySchema::sanitizeBlocks( $_POST['adam_bot_response_blocks'] ?? array() ),
		);
		$metadata_changed = false;
		foreach ( $meta as $key => $value ) {
			if ( get_post_meta( $post_id, $key, true ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- WordPress returns scalar metadata as strings.
				$metadata_changed = true;
			}
			update_post_meta( $post_id, $key, $value );
		}
		if ( $metadata_changed ) {
			$this->create_metadata_revision( $post_id, $post );
		}
	}

	/** @param int $post_id Post ID. @param object $post Post. @param bool $update Updated. */
	public function maybe_invalidate_content_cache( int $post_id, $post, bool $update ): void {
		unset( $update );
		if ( wp_is_post_revision( $post_id ) || ! isset( $post->post_type ) ) {
			return;
		}
		if ( 'page' === $post->post_type && in_array( $post_id, $this->settings->getPageIds(), true ) ) {
			$this->settings->bumpCacheVersion();
			return;
		}
		$event_types = apply_filters( 'adam_bot_knowledge_event_post_types', array() );
		$event_types = is_array( $event_types ) ? array_map( 'sanitize_key', $event_types ) : array();
		if ( in_array( $post->post_type, array_merge( array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), $event_types ), true ) ) {
			$this->settings->bumpCacheVersion();
		}
	}

	/** @return void */
	public function search_preview(): void {
		$this->authorize_ajax();
		$question = sanitize_textarea_field( wp_unslash( (string) ( $_POST['question'] ?? '' ) ) );
		if ( '' === $question ) {
			wp_send_json_error( array( 'message' => __( 'Enter a question to test.', 'adam-bot' ) ), 400 );
		}
		$search   = $this->search_service->search( $question );
		$response = $this->response_formatter->format( $search, $question )->toPublicArray();
		$top      = $search->getTopResult();
		wp_send_json_success(
			array(
				'confidence'       => $search->getConfidence(),
				'confidence_level' => $search->getConfidenceLevel(),
				'matched_keywords' => $search->getMatchedKeywords(),
				'matched_title'    => $top ? $top->getTitle() : '',
				'matched_provider' => $search->getMatchedProvider(),
				'response'         => (string) ( $response['message'] ?? '' ),
			)
		);
	}

	/** @return void */
	public function duplicate_check(): void {
		$this->authorize_ajax();
		$question = sanitize_text_field( wp_unslash( (string) ( $_POST['question'] ?? '' ) ) );
		$post_id  = absint( $_POST['post_id'] ?? 0 );
		wp_send_json_success( array( 'matches' => $this->duplicate_detector->find( $question, $post_id ) ) );
	}

	/** @param string $hook Current screen hook. @return void */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ! in_array( (string) ( $screen->post_type ?? '' ), array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) {
			return;
		}
		wp_enqueue_style( 'adam-bot-admin', ADAM_BOT_URL . 'assets/css/adam-bot-admin.css', array(), ADAM_BOT_VERSION );
		wp_enqueue_script( 'adam-bot-admin', ADAM_BOT_URL . 'assets/js/adam-bot-admin.js', array(), ADAM_BOT_VERSION, true );
		wp_localize_script(
			'adam-bot-admin',
			'adamBotKnowledgeAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE_ACTION ),
				'postId'  => isset( $GLOBALS['post']->ID ) ? (int) $GLOBALS['post']->ID : 0,
				'strings' => array( 'testing' => __( 'Testing…', 'adam-bot' ), 'error' => __( 'The preview could not be loaded.', 'adam-bot' ) ),
			)
		);
	}

	/** @param array<string,string> $columns Existing columns. @return array<string,string> */
	public function entry_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['adam_question'] = __( 'Question', 'adam-bot' );
				$new['adam_status']   = __( 'Status', 'adam-bot' );
				$new['adam_priority'] = __( 'Priority / Weight', 'adam-bot' );
				$new['adam_order']    = __( 'Order', 'adam-bot' );
			}
		}
		return $new;
	}

	public function render_entry_column( string $column, int $post_id ): void {
		if ( 'adam_question' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, EntrySchema::QUESTION_META, true ) );
		} elseif ( 'adam_status' === $column ) {
			$hidden = 'hidden' === (string) get_post_meta( $post_id, EntrySchema::VISIBILITY_META, true );
			echo esc_html( $hidden ? __( 'Hidden', 'adam-bot' ) : ucfirst( (string) get_post_status( $post_id ) ) );
		} elseif ( 'adam_priority' === $column ) {
			$priority = get_post_meta( $post_id, EntrySchema::PRIORITY_META, true );
			$weight   = get_post_meta( $post_id, EntrySchema::SEARCH_WEIGHT_META, true );
			echo esc_html( ( '' === (string) $priority ? '50' : (string) $priority ) . ' / ' . ( '' === (string) $weight ? '100' : (string) $weight ) );
		} elseif ( 'adam_order' === $column ) {
			$post = get_post( $post_id );
			echo esc_html( is_object( $post ) ? (string) ( $post->menu_order ?? 0 ) : '0' );
		}
	}

	/** @param array<string,string> $columns Sortable columns. @return array<string,string> */
	public function sortable_columns( array $columns ): array {
		$columns['adam_order'] = 'menu_order';
		return $columns;
	}

	/** Extends native list search to the administrator-authored question and search terms. */
	public function extend_admin_search( string $search, $query ): string {
		if ( ! is_admin() || ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() ) {
			return $search;
		}
		$post_type = $query->get( 'post_type' );
		if ( ! in_array( $post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) {
			return $search;
		}
		$term = trim( (string) $query->get( 's' ) );
		if ( '' === $term ) {
			return $search;
		}

		global $wpdb;
		$like   = '%' . $wpdb->esc_like( $term ) . '%';
		$exists = $wpdb->prepare(
			" OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} adam_search_meta WHERE adam_search_meta.post_id = {$wpdb->posts}.ID AND adam_search_meta.meta_key IN (%s, %s, %s) AND adam_search_meta.meta_value LIKE %s )",
			EntrySchema::QUESTION_META,
			EntrySchema::KEYWORDS_META,
			EntrySchema::SYNONYMS_META,
			$like
		);
		$base = preg_replace( '/^\s*AND\s*/i', '', $search ) ?? $search;
		return ' AND (' . $base . $exists . ') ';
	}

	/** @param array<string,string> $actions Existing actions. @return array<string,string> */
	public function bulk_actions( array $actions ): array {
		$actions['adam_bot_publish'] = __( 'Set published', 'adam-bot' );
		$actions['adam_bot_draft']   = __( 'Move to draft', 'adam-bot' );
		$actions['adam_bot_hide']    = __( 'Set hidden', 'adam-bot' );
		return $actions;
	}

	/** @param string $redirect URL. @param string $action Action. @param array<int,int> $post_ids IDs. */
	public function handle_bulk_actions( string $redirect, string $action, array $post_ids ): string {
		if ( ! in_array( $action, array( 'adam_bot_publish', 'adam_bot_draft', 'adam_bot_hide' ), true ) ) {
			return $redirect;
		}
		$count = 0;
		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			if ( 'adam_bot_draft' === $action ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			} else {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
				$visibility = 'adam_bot_hide' === $action ? 'hidden' : 'published';
				update_post_meta( $post_id, EntrySchema::VISIBILITY_META, $visibility );
				update_post_meta( $post_id, EntrySchema::ENABLED_META, 'hidden' === $visibility ? '0' : '1' );
			}
			$count++;
		}
		$this->settings->bumpCacheVersion();
		return add_query_arg( array( 'adam_bot_bulk_updated' => $count ), $redirect );
	}

	/** @param array<string,string> $states States. @param object $post Post. @return array<string,string> */
	public function display_post_states( array $states, $post ): array {
		if ( in_array( (string) ( $post->post_type ?? '' ), array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) && 'hidden' === (string) get_post_meta( $post->ID, EntrySchema::VISIBILITY_META, true ) ) {
			$states['adam_bot_hidden'] = __( 'Hidden', 'adam-bot' );
		}
		return $states;
	}

	/** @param string $placeholder Existing placeholder. @param object $post Post. */
	public function title_placeholder( string $placeholder, $post ): string {
		return in_array( (string) ( $post->post_type ?? '' ), array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ? __( 'Internal title', 'adam-bot' ) : $placeholder;
	}

	/** @return void */
	public function render_list_tools(): void {
		$post_type = sanitize_key( (string) ( $_GET['post_type'] ?? '' ) );
		if ( ! in_array( $post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=adam-bot-settings#adam-bot-import-export' );
		$categories_url = admin_url( 'edit-tags.php?taxonomy=' . EntrySchema::TAXONOMY . '&post_type=' . $post_type );
		echo '<a class="button" href="' . esc_url( $categories_url ) . '">' . esc_html__( 'Manage Categories', 'adam-bot' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Import / Export', 'adam-bot' ) . '</a>';
	}

	/** @return void */
	public function render_category_add_fields(): void {
		?>
		<div class="form-field"><label for="adam-bot-category-color"><?php esc_html_e( 'Colour', 'adam-bot' ); ?></label><input type="color" id="adam-bot-category-color" name="adam_bot_category_color" value="#2271b1" /></div>
		<div class="form-field"><label for="adam-bot-category-icon"><?php esc_html_e( 'Icon', 'adam-bot' ); ?></label><input type="text" id="adam-bot-category-icon" name="adam_bot_category_icon" maxlength="80" placeholder="dashicons-groups" /><p><?php esc_html_e( 'Use a Dashicon class or a single emoji.', 'adam-bot' ); ?></p></div>
		<?php
	}

	/** @param object $term Term. @return void */
	public function render_category_edit_fields( $term ): void {
		$color = (string) get_term_meta( $term->term_id, EntrySchema::TERM_COLOR_META, true );
		$icon  = (string) get_term_meta( $term->term_id, EntrySchema::TERM_ICON_META, true );
		?>
		<tr class="form-field"><th scope="row"><label for="adam-bot-category-color"><?php esc_html_e( 'Colour', 'adam-bot' ); ?></label></th><td><input type="color" id="adam-bot-category-color" name="adam_bot_category_color" value="<?php echo esc_attr( $color ?: '#2271b1' ); ?>" /></td></tr>
		<tr class="form-field"><th scope="row"><label for="adam-bot-category-icon"><?php esc_html_e( 'Icon', 'adam-bot' ); ?></label></th><td><input type="text" id="adam-bot-category-icon" name="adam_bot_category_icon" maxlength="80" value="<?php echo esc_attr( $icon ); ?>" /><p class="description"><?php esc_html_e( 'Use a Dashicon class or a single emoji.', 'adam-bot' ); ?></p></td></tr>
		<?php
	}

	public function save_category_fields( int $term_id ): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$color = sanitize_hex_color( (string) ( $_POST['adam_bot_category_color'] ?? '' ) );
		$icon  = sanitize_text_field( wp_unslash( (string) ( $_POST['adam_bot_category_icon'] ?? '' ) ) );
		update_term_meta( $term_id, EntrySchema::TERM_COLOR_META, $color ?: '#2271b1' );
		update_term_meta( $term_id, EntrySchema::TERM_ICON_META, $icon );
	}

	/** Renders source selection and page controls inside the main Settings page. */
	public function render_settings_fields(): void {
		$settings = $this->settings->all();
		$pages    = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title' ) );
		$option   = KnowledgeSettings::OPTION_KEY;
		?>
		<h2><?php esc_html_e( 'Knowledge Providers', 'adam-bot' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Enabled providers are searched automatically. Providers installed by other ADAM plugins appear here when they register a label.', 'adam-bot' ); ?></p>
		<fieldset><?php foreach ( $this->settings->sources() as $source => $label ) : ?><label class="adam-bot-provider-option"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled_sources][]" value="<?php echo esc_attr( $source ); ?>" <?php checked( in_array( $source, $settings['enabled_sources'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset>
		<h3><?php esc_html_e( 'Selected Website Pages', 'adam-bot' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Published pages selected here become administrator-managed knowledge sources.', 'adam-bot' ); ?></p>
		<div class="adam-bot-page-picker"><?php foreach ( $pages as $page ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[page_ids][]" value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php checked( in_array( (int) $page->ID, $settings['page_ids'], true ) ); ?> /> <?php echo esc_html( get_the_title( $page ) ); ?></label><?php endforeach; ?></div>
		<?php
	}

	/** Renders JSON/CSV transfer controls inside Settings. */
	public function render_transfer_tools(): void {
		$export_base = wp_nonce_url( admin_url( 'admin-post.php?action=adam_bot_export_knowledge' ), 'adam_bot_knowledge_transfer' );
		?>
		<div id="adam-bot-import-export">
			<h2><?php esc_html_e( 'Import & Export', 'adam-bot' ); ?></h2>
			<p><?php esc_html_e( 'Back up or migrate all Knowledge Base and FAQ entries, including response blocks, search metadata, relationships, status, and ordering.', 'adam-bot' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'json', $export_base ) ); ?>"><?php esc_html_e( 'Export JSON', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'csv', $export_base ) ); ?>"><?php esc_html_e( 'Export CSV', 'adam-bot' ); ?></a></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="adam_bot_import_knowledge" />
				<?php wp_nonce_field( 'adam_bot_knowledge_transfer' ); ?>
				<input type="file" name="adam_bot_import_file" accept=".json,.csv,application/json,text/csv" required />
				<?php submit_button( __( 'Import Knowledge', 'adam-bot' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/** @param array<int,array<string,int|string>> $matches Similar rows. */
	private function render_duplicate_matches( array $matches ): void {
		if ( empty( $matches ) ) {
			echo '<p class="description">' . esc_html__( 'No likely duplicates detected.', 'adam-bot' ) . '</p>';
			return;
		}
		echo '<p><strong>' . esc_html__( 'Possible duplicate detected', 'adam-bot' ) . '</strong></p><ul>';
		foreach ( $matches as $match ) {
			$url = get_edit_post_link( (int) $match['id'] );
			echo '<li><a href="' . esc_url( (string) $url ) . '">' . esc_html( (string) $match['question'] ) . '</a><br><strong>' . esc_html( (string) $match['similarity'] ) . '%</strong> ' . esc_html__( 'similar', 'adam-bot' ) . '</li>';
		}
		echo '</ul>';
	}

	private function authorize_ajax(): void {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'adam-bot' ) ), 403 );
		}
	}

	/** Ensures metadata-only edits receive a restorable native revision on WordPress 6.3+. */
	private function create_metadata_revision( int $post_id, $post ): void {
		if ( ! function_exists( 'wp_save_post_revision' ) || ( function_exists( 'wp_revisions_enabled' ) && ! wp_revisions_enabled( $post ) ) ) {
			return;
		}
		$revision_id = wp_save_post_revision( $post_id );
		if ( false === $revision_id && function_exists( '_wp_put_post_revision' ) ) {
			_wp_put_post_revision( $post_id );
		}
	}
}
