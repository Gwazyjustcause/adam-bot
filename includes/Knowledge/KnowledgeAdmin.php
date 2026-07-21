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
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_entry_meta' ), 20, 3 );
		add_action( 'save_post', array( $this, 'maybe_invalidate_content_cache' ), 30, 3 );
		add_action( 'adam_bot_knowledge_invalidate_cache', array( $this->settings, 'bumpCacheVersion' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_adam_bot_search_preview', array( $this, 'search_preview' ) );
		add_action( 'wp_ajax_adam_bot_duplicate_check', array( $this, 'duplicate_check' ) );
		add_action( 'wp_ajax_adam_bot_reorder_entries', array( $this, 'reorder_entries' ) );
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
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_quick_edit' ), 25, 3 );
		add_filter( 'bulk_actions-edit-' . ManualSource::POST_TYPE, array( $this, 'bulk_actions' ) );
		add_filter( 'bulk_actions-edit-' . FAQSource::POST_TYPE, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . ManualSource::POST_TYPE, array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-' . FAQSource::POST_TYPE, array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_list_tools' ) );
		add_filter( 'posts_search', array( $this, 'extend_admin_search' ), 10, 2 );
		add_action( 'admin_post_adam_bot_save_search', array( $this, 'save_admin_search' ) );
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
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options',
				'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options',
				'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options',
				'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options',
				'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'manage_options',
			),
		);

		register_post_type(
			ManualSource::POST_TYPE,
			array_merge(
				$common,
				array(
					'labels' => array(
						'name'               => __( 'Base de Conhecimento', 'adam-bot' ),
						'singular_name'      => __( 'Entrada de conhecimento', 'adam-bot' ),
						'add_new'            => __( 'Adicionar entrada', 'adam-bot' ),
						'add_new_item'       => __( 'Adicionar entrada de conhecimento', 'adam-bot' ),
						'edit_item'          => __( 'Editar entrada de conhecimento', 'adam-bot' ),
						'new_item'           => __( 'Nova entrada de conhecimento', 'adam-bot' ),
						'search_items'       => __( 'Pesquisar na Base de Conhecimento', 'adam-bot' ),
						'not_found'          => __( 'Não foram encontradas entradas de conhecimento.', 'adam-bot' ),
						'item_updated'       => __( 'Entrada de conhecimento atualizada.', 'adam-bot' ),
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
						'add_new'       => __( 'Adicionar FAQ', 'adam-bot' ),
						'add_new_item'  => __( 'Adicionar FAQ', 'adam-bot' ),
						'edit_item'     => __( 'Editar FAQ', 'adam-bot' ),
						'search_items'  => __( 'Pesquisar FAQ', 'adam-bot' ),
						'not_found'     => __( 'Não foram encontradas FAQ.', 'adam-bot' ),
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
					'name'          => __( 'Categorias de conhecimento', 'adam-bot' ),
					'singular_name' => __( 'Categoria de conhecimento', 'adam-bot' ),
					'add_new_item'  => __( 'Adicionar categoria de conhecimento', 'adam-bot' ),
					'edit_item'     => __( 'Editar categoria de conhecimento', 'adam-bot' ),
					'search_items'  => __( 'Pesquisar categorias de conhecimento', 'adam-bot' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'capabilities'      => array(
					'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options',
					'delete_terms' => 'manage_options', 'assign_terms' => 'manage_options',
				),
				'rewrite'           => false,
			)
		);
	}

	/** @return void */
	public function register_menu(): void {
		add_submenu_page(
			'adam-bot',
			__( 'Base de Conhecimento', 'adam-bot' ),
			__( 'Base de Conhecimento', 'adam-bot' ),
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
			add_meta_box( 'adam-bot-basic-information', __( 'Informação básica', 'adam-bot' ), array( $this, 'render_basic_box' ), $post_type, 'normal', 'high' );
			add_meta_box( 'adam-bot-response-builder', __( 'Compositor de resposta avançada', 'adam-bot' ), array( $this, 'render_response_builder' ), $post_type, 'normal', 'high' );
			add_meta_box( 'adam-bot-search-fields', __( 'Pesquisa', 'adam-bot' ), array( $this, 'render_search_box' ), $post_type, 'normal', 'default' );
			add_meta_box( 'adam-bot-navigation', __( 'Navegação', 'adam-bot' ), array( $this, 'render_navigation_box' ), $post_type, 'normal', 'default' );
			add_meta_box( 'adam-bot-related', __( 'Conhecimento relacionado', 'adam-bot' ), array( $this, 'render_related_box' ), $post_type, 'normal', 'default' );
			add_meta_box( 'adam-bot-search-preview', __( 'Pré-visualização da pesquisa', 'adam-bot' ), array( $this, 'render_preview_box' ), $post_type, 'side', 'high' );
			add_meta_box( 'adam-bot-duplicates', __( 'Deteção de duplicados', 'adam-bot' ), array( $this, 'render_duplicate_box' ), $post_type, 'side', 'default' );
		}
	}

	/** @param object $post Current post. */
	public function render_basic_box( $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$question   = (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true );
		$visibility = (string) get_post_meta( $post->ID, EntrySchema::VISIBILITY_META, true );
		$visibility = in_array( $visibility, array( 'published', 'hidden' ), true ) ? $visibility : 'published';
		$language   = EntrySchema::sanitizeLanguage( get_post_meta( $post->ID, EntrySchema::LANGUAGE_META, true ) );
		?>
		<p><label for="adam-bot-question"><strong><?php esc_html_e( 'Pergunta', 'adam-bot' ); ?></strong></label></p>
		<input class="widefat" type="text" id="adam-bot-question" name="adam_bot_question" maxlength="500" value="<?php echo esc_attr( $question ); ?>" placeholder="<?php esc_attr_e( 'Como posso tornar-me sócio?', 'adam-bot' ); ?>" />
		<p class="description"><?php esc_html_e( 'Use o editor do WordPress para uma resposta simples ou componha uma resposta estruturada no compositor avançado.', 'adam-bot' ); ?></p>
		<p><label for="adam-bot-visibility"><strong><?php esc_html_e( 'Visibilidade', 'adam-bot' ); ?></strong></label></p>
		<select id="adam-bot-visibility" name="adam_bot_visibility">
			<option value="published" <?php selected( $visibility, 'published' ); ?>><?php esc_html_e( 'Publicado', 'adam-bot' ); ?></option>
			<option value="hidden" <?php selected( $visibility, 'hidden' ); ?>><?php esc_html_e( 'Oculto', 'adam-bot' ); ?></option>
		</select>
		<p><label for="adam-bot-language"><strong><?php esc_html_e( 'Idioma', 'adam-bot' ); ?></strong></label></p>
		<select id="adam-bot-language" name="adam_bot_language">
			<option value="pt" <?php selected( $language, 'pt' ); ?>><?php esc_html_e( 'Português', 'adam-bot' ); ?></option>
			<option value="en" <?php selected( $language, 'en' ); ?>><?php esc_html_e( 'Inglês', 'adam-bot' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Use o painel Publicar para guardar como rascunho. As entradas ocultas continuam editáveis, mas não são pesquisadas.', 'adam-bot' ); ?></p>
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
			<p><label for="adam-bot-keywords"><strong><?php esc_html_e( 'Palavras-chave', 'adam-bot' ); ?></strong></label><textarea class="widefat" id="adam-bot-keywords" name="adam_bot_keywords" rows="3" placeholder="<?php esc_attr_e( 'renovar, sócio', 'adam-bot' ); ?>"><?php echo esc_textarea( implode( ', ', $keywords ) ); ?></textarea><span class="description"><?php esc_html_e( 'Separe palavras ou expressões com vírgulas.', 'adam-bot' ); ?></span></p>
			<p><label for="adam-bot-synonyms"><strong><?php esc_html_e( 'Sinónimos', 'adam-bot' ); ?></strong></label><textarea class="widefat" id="adam-bot-synonyms" name="adam_bot_synonyms" rows="3" placeholder="<?php esc_attr_e( 'renovação, quota', 'adam-bot' ); ?>"><?php echo esc_textarea( implode( ', ', $synonyms ) ); ?></textarea><span class="description"><?php esc_html_e( 'Adicione termos alternativos que os visitantes possam utilizar.', 'adam-bot' ); ?></span></p>
			<p><label for="adam-bot-priority"><strong><?php esc_html_e( 'Prioridade', 'adam-bot' ); ?></strong></label><input type="number" id="adam-bot-priority" name="adam_bot_priority" min="0" max="100" value="<?php echo esc_attr( (string) $priority ); ?>" /><span class="description"><?php esc_html_e( 'O valor predefinido é 50. Aumente apenas quando esta resposta deve prevalecer em correspondências próximas.', 'adam-bot' ); ?></span></p>
			<p><label for="adam-bot-search-weight"><strong><?php esc_html_e( 'Peso na pesquisa', 'adam-bot' ); ?></strong></label><input type="number" id="adam-bot-search-weight" name="adam_bot_search_weight" min="0" max="200" value="<?php echo esc_attr( (string) $weight ); ?>" /><span class="description"><?php esc_html_e( 'O valor predefinido é 100 e é adequado à maioria das entradas.', 'adam-bot' ); ?></span></p>
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
			<p><label for="adam-bot-related-page"><strong><?php esc_html_e( 'Página relacionada', 'adam-bot' ); ?></strong></label><select class="widefat" id="adam-bot-related-page" name="adam_bot_related_page"><option value="0"><?php esc_html_e( '— Nenhuma —', 'adam-bot' ); ?></option><?php foreach ( $pages as $page ) : ?><option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( $page_id, (int) $page->ID ); ?>><?php echo esc_html( get_the_title( $page ) ); ?></option><?php endforeach; ?></select></p>
			<p><label for="adam-bot-button-text"><strong><?php esc_html_e( 'Texto do botão', 'adam-bot' ); ?></strong></label><input class="widefat" type="text" id="adam-bot-button-text" name="adam_bot_button_text" maxlength="100" value="<?php echo esc_attr( $button_text ); ?>" placeholder="<?php esc_attr_e( 'Inscrever-me', 'adam-bot' ); ?>" /></p>
			<p><label for="adam-bot-button-url"><strong><?php esc_html_e( 'URL do botão', 'adam-bot' ); ?></strong></label><input class="widefat" type="url" id="adam-bot-button-url" name="adam_bot_button_url" value="<?php echo esc_attr( $button_url ); ?>" placeholder="/inscricao/" /><span class="description"><?php esc_html_e( 'Quando preenchido, substitui o endereço da página relacionada.', 'adam-bot' ); ?></span></p>
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
		<p class="description"><?php esc_html_e( 'Escolha as perguntas seguintes que devem aparecer após esta resposta. Arraste as linhas selecionadas para definir a ordem.', 'adam-bot' ); ?></p>
		<input class="widefat adam-bot-related-filter" type="search" placeholder="<?php esc_attr_e( 'Filtrar conhecimento relacionado…', 'adam-bot' ); ?>" />
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
		<p class="description"><?php esc_html_e( 'Construa a resposta com blocos reutilizáveis, sem escrever HTML. Arraste os blocos para os reordenar.', 'adam-bot' ); ?></p>
		<div id="adam-bot-response-blocks">
			<?php foreach ( $blocks as $index => $block ) : $this->render_response_block( (int) $index, $block ); endforeach; ?>
		</div>
		<p><button type="button" class="button" id="adam-bot-add-block"><?php esc_html_e( 'Adicionar bloco de resposta', 'adam-bot' ); ?></button></p>
		<template id="adam-bot-response-block-template"><?php $this->render_response_block( 999999, array( 'type' => 'paragraph', 'text' => '', 'url' => '' ) ); ?></template>
		<?php
	}

	/** @param object $post Current post. */
	public function render_preview_box( $post ): void {
		$question = (string) get_post_meta( $post->ID, EntrySchema::QUESTION_META, true );
		?>
		<p><label for="adam-bot-preview-question"><strong><?php esc_html_e( 'Pergunta', 'adam-bot' ); ?></strong></label></p>
		<textarea class="widefat" id="adam-bot-preview-question" rows="3"><?php echo esc_textarea( $question ); ?></textarea>
		<p><button type="button" class="button button-primary" id="adam-bot-run-preview"><?php esc_html_e( 'Testar pesquisa', 'adam-bot' ); ?></button></p>
		<div id="adam-bot-preview-result" aria-live="polite"><p class="description"><?php esc_html_e( 'Guarde a entrada e teste uma pergunta para ver as palavras correspondentes, a confiança e a resposta final.', 'adam-bot' ); ?></p></div>
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
			'paragraph'    => __( 'Parágrafo', 'adam-bot' ),
			'heading'      => __( 'Título', 'adam-bot' ),
			'bullet_list'  => __( 'Lista com marcadores', 'adam-bot' ),
			'numbered_list'=> __( 'Lista numerada', 'adam-bot' ),
			'button'       => __( 'Botão', 'adam-bot' ),
			'link'         => __( 'Ligação', 'adam-bot' ),
			'warning'      => __( 'Aviso', 'adam-bot' ),
			'information'  => __( 'Caixa de informação', 'adam-bot' ),
			'success'      => __( 'Caixa de sucesso', 'adam-bot' ),
		);
		?>
		<div class="adam-bot-response-block" draggable="true">
			<span class="dashicons dashicons-move adam-bot-block-handle" aria-hidden="true"></span>
			<select class="adam-bot-block-type" name="adam_bot_response_blocks[<?php echo esc_attr( (string) $index ); ?>][type]" aria-label="<?php esc_attr_e( 'Tipo de bloco', 'adam-bot' ); ?>"><?php foreach ( $types as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<textarea name="adam_bot_response_blocks[<?php echo esc_attr( (string) $index ); ?>][text]" rows="3" placeholder="<?php esc_attr_e( 'Conteúdo do bloco', 'adam-bot' ); ?>"><?php echo esc_textarea( $text ); ?></textarea>
			<input class="adam-bot-block-url" type="url" name="adam_bot_response_blocks[<?php echo esc_attr( (string) $index ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://…" />
			<button type="button" class="button-link-delete adam-bot-remove-block"><?php esc_html_e( 'Remover', 'adam-bot' ); ?></button>
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
			EntrySchema::LANGUAGE_META        => EntrySchema::sanitizeLanguage( $_POST['adam_bot_language'] ?? 'pt' ),
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
			wp_send_json_error( array( 'message' => __( 'Introduza uma pergunta para testar.', 'adam-bot' ) ), 400 );
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

	/** Persists accessible drag/keyboard ordering from the list table. */
	public function reorder_entries(): void {
		$this->authorize_ajax();
		$ids = preg_split( '/,/', sanitize_text_field( wp_unslash( (string) ( $_POST['ids'] ?? '' ) ) ), -1, PREG_SPLIT_NO_EMPTY );
		$ids = array_slice( array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ), 0, 500 );
		foreach ( $ids as $order => $post_id ) {
			$post = get_post( $post_id );
			if ( ! is_object( $post ) || ! in_array( (string) $post->post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) || ! current_user_can( 'edit_post', $post_id ) ) { continue; }
			wp_update_post( array( 'ID' => $post_id, 'menu_order' => $order ) );
		}
		$this->settings->bumpCacheVersion();
		wp_send_json_success( array( 'updated' => count( $ids ) ) );
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
				'strings' => array(
					'testing' => __( 'A testar…', 'adam-bot' ), 'error' => __( 'Não foi possível carregar a pré-visualização.', 'adam-bot' ),
					'selected' => __( 'Entrada selecionada', 'adam-bot' ), 'matched' => __( 'Palavras-chave correspondentes', 'adam-bot' ),
					'confidence' => __( 'Confiança', 'adam-bot' ), 'preview' => __( 'Pré-visualização da resposta', 'adam-bot' ),
					'duplicate' => __( 'Possível duplicado detetado', 'adam-bot' ), 'noDuplicate' => __( 'Não foram detetados duplicados prováveis.', 'adam-bot' ),
					'similar' => __( 'semelhante', 'adam-bot' ), 'empty' => __( '—', 'adam-bot' ),
				),
			)
		);
	}

	/** @param array<string,string> $columns Existing columns. @return array<string,string> */
	public function entry_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['adam_question'] = __( 'Pergunta', 'adam-bot' );
				$new['adam_status']   = __( 'Estado', 'adam-bot' );
				$new['adam_priority'] = __( 'Prioridade / peso', 'adam-bot' );
				$new['adam_order']    = __( 'Ordem', 'adam-bot' );
			}
		}
		return $new;
	}

	public function render_entry_column( string $column, int $post_id ): void {
		if ( 'adam_question' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, EntrySchema::QUESTION_META, true ) );
		} elseif ( 'adam_status' === $column ) {
			$hidden = 'hidden' === (string) get_post_meta( $post_id, EntrySchema::VISIBILITY_META, true );
			echo esc_html( $hidden ? __( 'Oculto', 'adam-bot' ) : ( 'publish' === get_post_status( $post_id ) ? __( 'Publicado', 'adam-bot' ) : __( 'Rascunho', 'adam-bot' ) ) );
		} elseif ( 'adam_priority' === $column ) {
			$priority = get_post_meta( $post_id, EntrySchema::PRIORITY_META, true );
			$weight   = get_post_meta( $post_id, EntrySchema::SEARCH_WEIGHT_META, true );
			echo esc_html( ( '' === (string) $priority ? '50' : (string) $priority ) . ' / ' . ( '' === (string) $weight ? '100' : (string) $weight ) );
			echo '<span class="adam-bot-quick-data" hidden data-priority="' . esc_attr( '' === (string) $priority ? '50' : (string) $priority ) . '" data-weight="' . esc_attr( '' === (string) $weight ? '100' : (string) $weight ) . '" data-visibility="' . esc_attr( (string) ( get_post_meta( $post_id, EntrySchema::VISIBILITY_META, true ) ?: 'published' ) ) . '"></span>';
		} elseif ( 'adam_order' === $column ) {
			$post = get_post( $post_id );
			echo '<button type="button" class="button-link adam-bot-order-handle" aria-label="' . esc_attr__( 'Reordenar entrada. Use as setas para mover.', 'adam-bot' ) . '"><span class="dashicons dashicons-move" aria-hidden="true"></span> ' . esc_html( is_object( $post ) ? (string) ( $post->menu_order ?? 0 ) : '0' ) . '</button>';
		}
	}

	/** Adds the search controls to WordPress Quick Edit. */
	public function render_quick_edit( string $column, string $post_type ): void {
		if ( 'adam_priority' !== $column || ! in_array( $post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) { return; }
		?>
		<fieldset class="inline-edit-col-right adam-bot-quick-edit"><div class="inline-edit-col">
			<h4><?php esc_html_e( 'Pesquisa do ADAM BOT', 'adam-bot' ); ?></h4>
			<label><span class="title"><?php esc_html_e( 'Prioridade', 'adam-bot' ); ?></span><span class="input-text-wrap"><input type="number" name="adam_bot_quick_priority" min="0" max="100" value="50" /></span></label>
			<label><span class="title"><?php esc_html_e( 'Peso', 'adam-bot' ); ?></span><span class="input-text-wrap"><input type="number" name="adam_bot_quick_weight" min="0" max="200" value="100" /></span></label>
			<label><span class="title"><?php esc_html_e( 'Visibilidade', 'adam-bot' ); ?></span><select name="adam_bot_quick_visibility"><option value="published"><?php esc_html_e( 'Publicado', 'adam-bot' ); ?></option><option value="hidden"><?php esc_html_e( 'Oculto', 'adam-bot' ); ?></option></select></label>
		</div></fieldset>
		<?php
	}

	/** Saves fields submitted by WordPress Quick Edit after verifying its nonce. */
	public function save_quick_edit( int $post_id, $post, bool $update ): void {
		unset( $update );
		if ( ! isset( $_POST['_inline_edit'], $_POST['adam_bot_quick_priority'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_inline_edit'] ) ), 'inlineeditnonce' ) || ! is_object( $post ) || ! in_array( (string) $post->post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$visibility = 'hidden' === sanitize_key( (string) ( $_POST['adam_bot_quick_visibility'] ?? '' ) ) ? 'hidden' : 'published';
		update_post_meta( $post_id, EntrySchema::PRIORITY_META, max( 0, min( 100, absint( $_POST['adam_bot_quick_priority'] ) ) ) );
		update_post_meta( $post_id, EntrySchema::SEARCH_WEIGHT_META, max( 0, min( 200, absint( $_POST['adam_bot_quick_weight'] ?? 100 ) ) ) );
		update_post_meta( $post_id, EntrySchema::VISIBILITY_META, $visibility );
		update_post_meta( $post_id, EntrySchema::ENABLED_META, 'hidden' === $visibility ? '0' : '1' );
		$this->settings->bumpCacheVersion();
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
		$actions['adam_bot_publish']   = __( 'Definir como publicado', 'adam-bot' );
		$actions['adam_bot_draft']     = __( 'Mover para rascunho', 'adam-bot' );
		$actions['adam_bot_hide']      = __( 'Definir como oculto', 'adam-bot' );
		$actions['adam_bot_duplicate'] = __( 'Duplicar', 'adam-bot' );
		return $actions;
	}

	/** @param string $redirect URL. @param string $action Action. @param array<int,int> $post_ids IDs. */
	public function handle_bulk_actions( string $redirect, string $action, array $post_ids ): string {
		if ( ! in_array( $action, array( 'adam_bot_publish', 'adam_bot_draft', 'adam_bot_hide', 'adam_bot_duplicate' ), true ) ) {
			return $redirect;
		}
		$count = 0;
		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			if ( 'adam_bot_duplicate' === $action ) {
				$post = get_post( $post_id );
				if ( ! is_object( $post ) ) { continue; }
				$new_id = wp_insert_post( array( 'post_type' => $post->post_type, 'post_status' => 'draft', 'post_title' => sprintf( __( 'Cópia de %s', 'adam-bot' ), $post->post_title ), 'post_content' => $post->post_content, 'menu_order' => (int) ( $post->menu_order ?? 0 ) ), true );
				if ( is_wp_error( $new_id ) ) { continue; }
				foreach ( array_merge( EntrySchema::revisionMetaKeys(), array( EntrySchema::ENABLED_META, EntrySchema::LEGACY_CATEGORY_META ) ) as $meta_key ) {
					update_post_meta( (int) $new_id, $meta_key, get_post_meta( $post_id, $meta_key, true ) );
				}
				if ( function_exists( 'wp_get_object_terms' ) && function_exists( 'wp_set_object_terms' ) ) {
					$term_ids = wp_get_object_terms( $post_id, EntrySchema::TAXONOMY, array( 'fields' => 'ids' ) );
					if ( is_array( $term_ids ) ) { wp_set_object_terms( (int) $new_id, array_map( 'intval', $term_ids ), EntrySchema::TAXONOMY ); }
				}
			} elseif ( 'adam_bot_draft' === $action ) {
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
			$states['adam_bot_hidden'] = __( 'Oculto', 'adam-bot' );
		}
		return $states;
	}

	/** @param string $placeholder Existing placeholder. @param object $post Post. */
	public function title_placeholder( string $placeholder, $post ): string {
		return in_array( (string) ( $post->post_type ?? '' ), array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ? __( 'Título interno', 'adam-bot' ) : $placeholder;
	}

	/** @return void */
	public function render_list_tools(): void {
		$post_type = sanitize_key( (string) ( $_GET['post_type'] ?? '' ) );
		if ( ! in_array( $post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=adam-bot-settings#adam-bot-import-export' );
		$categories_url = admin_url( 'edit-tags.php?taxonomy=' . EntrySchema::TAXONOMY . '&post_type=' . $post_type );
		echo '<a class="button" href="' . esc_url( $categories_url ) . '">' . esc_html__( 'Gerir categorias', 'adam-bot' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Importar / exportar', 'adam-bot' ) . '</a>';
		$current_search = sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) );
		$current_status = sanitize_key( wp_unslash( (string) ( $_GET['post_status'] ?? '' ) ) );
		$save_url = wp_nonce_url( add_query_arg( array( 'action' => 'adam_bot_save_search', 'post_type' => $post_type, 's' => $current_search, 'post_status' => $current_status ), admin_url( 'admin-post.php' ) ), 'adam_bot_save_search' );
		echo ' <a class="button" href="' . esc_url( $save_url ) . '">' . esc_html__( 'Guardar pesquisa atual', 'adam-bot' ) . '</a>';
		$saved = get_option( 'adam_bot_saved_searches', array() );
		if ( is_array( $saved ) && ! empty( $saved ) ) {
			echo ' <label class="screen-reader-text" for="adam-bot-saved-search">' . esc_html__( 'Pesquisas guardadas', 'adam-bot' ) . '</label><select id="adam-bot-saved-search" onchange="if(this.value){window.location.href=this.value;}"><option value="">' . esc_html__( 'Pesquisas guardadas', 'adam-bot' ) . '</option>';
			foreach ( $saved as $item ) { if ( is_array( $item ) && $post_type === ( $item['post_type'] ?? '' ) ) { echo '<option value="' . esc_url( (string) ( $item['url'] ?? '' ) ) . '">' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</option>'; } }
			echo '</select>';
		}
	}

	/** Saves the current advanced list filters as an administrator shortcut. */
	public function save_admin_search(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Não tem permissão para guardar pesquisas.', 'adam-bot' ) ); }
		check_admin_referer( 'adam_bot_save_search' );
		$post_type = sanitize_key( wp_unslash( (string) ( $_GET['post_type'] ?? '' ) ) );
		$search = sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) );
		$status = sanitize_key( wp_unslash( (string) ( $_GET['post_status'] ?? '' ) ) );
		if ( ! in_array( $post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) { wp_safe_redirect( admin_url() ); exit; }
		$url = add_query_arg( array_filter( array( 'post_type' => $post_type, 's' => $search, 'post_status' => $status ) ), admin_url( 'edit.php' ) );
		$label = trim( $search . ( $status ? ' · ' . $status : '' ) );
		$label = '' !== $label ? $label : __( 'Todas as entradas', 'adam-bot' );
		$saved = get_option( 'adam_bot_saved_searches', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$saved[ md5( $url ) ] = array( 'post_type' => $post_type, 'label' => $label, 'url' => $url );
		update_option( 'adam_bot_saved_searches', array_slice( $saved, -20, null, true ), false );
		wp_safe_redirect( $url );
		exit;
	}

	/** @return void */
	public function render_category_add_fields(): void {
		?>
		<div class="form-field"><label for="adam-bot-category-color"><?php esc_html_e( 'Cor', 'adam-bot' ); ?></label><input type="color" id="adam-bot-category-color" name="adam_bot_category_color" value="#2271b1" /></div>
		<div class="form-field"><label for="adam-bot-category-icon"><?php esc_html_e( 'Ícone', 'adam-bot' ); ?></label><input type="text" id="adam-bot-category-icon" name="adam_bot_category_icon" maxlength="80" placeholder="dashicons-groups" /><p><?php esc_html_e( 'Use uma classe Dashicon ou um único emoji.', 'adam-bot' ); ?></p></div>
		<?php
	}

	/** @param object $term Term. @return void */
	public function render_category_edit_fields( $term ): void {
		$color = (string) get_term_meta( $term->term_id, EntrySchema::TERM_COLOR_META, true );
		$icon  = (string) get_term_meta( $term->term_id, EntrySchema::TERM_ICON_META, true );
		?>
		<tr class="form-field"><th scope="row"><label for="adam-bot-category-color"><?php esc_html_e( 'Cor', 'adam-bot' ); ?></label></th><td><input type="color" id="adam-bot-category-color" name="adam_bot_category_color" value="<?php echo esc_attr( $color ?: '#2271b1' ); ?>" /></td></tr>
		<tr class="form-field"><th scope="row"><label for="adam-bot-category-icon"><?php esc_html_e( 'Ícone', 'adam-bot' ); ?></label></th><td><input type="text" id="adam-bot-category-icon" name="adam_bot_category_icon" maxlength="80" value="<?php echo esc_attr( $icon ); ?>" /><p class="description"><?php esc_html_e( 'Use uma classe Dashicon ou um único emoji.', 'adam-bot' ); ?></p></td></tr>
		<?php
	}

	public function save_category_fields( int $term_id ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
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
		<h2><?php esc_html_e( 'Fornecedores de conhecimento', 'adam-bot' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Os fornecedores ativos são pesquisados automaticamente. Os fornecedores de outros plugins ADAM aparecem aqui após o registo.', 'adam-bot' ); ?></p>
		<fieldset><?php foreach ( $this->settings->sources() as $source => $label ) : ?><label class="adam-bot-provider-option"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled_sources][]" value="<?php echo esc_attr( $source ); ?>" <?php checked( in_array( $source, $settings['enabled_sources'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset>
		<h3><?php esc_html_e( 'Páginas selecionadas do website', 'adam-bot' ); ?></h3>
		<p class="description"><?php esc_html_e( 'As páginas publicadas aqui selecionadas tornam-se fontes de conhecimento geridas por administradores.', 'adam-bot' ); ?></p>
		<div class="adam-bot-page-picker"><?php foreach ( $pages as $page ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[page_ids][]" value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php checked( in_array( (int) $page->ID, $settings['page_ids'], true ) ); ?> /> <?php echo esc_html( get_the_title( $page ) ); ?></label><?php endforeach; ?></div>
		<h3><?php esc_html_e( 'Modo de diagnóstico', 'adam-bot' ); ?></h3>
		<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[debug_mode]" value="1" <?php checked( ! empty( $settings['debug_mode'] ) ); ?> /> <?php esc_html_e( 'Mostrar detalhes de pesquisa apenas a administradores autenticados.', 'adam-bot' ); ?></label>
		<?php
	}

	/** Renders JSON/CSV transfer controls inside Settings. */
	public function render_transfer_tools(): void {
		$export_base = wp_nonce_url( admin_url( 'admin-post.php?action=adam_bot_export_knowledge' ), 'adam_bot_knowledge_transfer' );
		$backup_base = wp_nonce_url( admin_url( 'admin-post.php?action=adam_bot_export_backup' ), 'adam_bot_knowledge_transfer' );
		?>
		<div id="adam-bot-import-export">
			<h2><?php esc_html_e( 'Importar e exportar', 'adam-bot' ); ?></h2>
			<p><?php esc_html_e( 'Faça uma cópia ou migre todas as entradas da Base de Conhecimento e FAQ, incluindo blocos, pesquisa, relações, estado e ordem.', 'adam-bot' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'json', $export_base ) ); ?>"><?php esc_html_e( 'Exportar JSON', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'csv', $export_base ) ); ?>"><?php esc_html_e( 'Exportar CSV', 'adam-bot' ); ?></a></p>
			<h3><?php esc_html_e( 'Cópia de segurança completa', 'adam-bot' ); ?></h3>
			<p><?php esc_html_e( 'Inclui Base de Conhecimento, FAQ, analítica, registos de pesquisa anónimos, definições e saúde dos fornecedores.', 'adam-bot' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'json', $backup_base ) ); ?>"><?php esc_html_e( 'Exportar cópia JSON', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( add_query_arg( 'format', 'csv', $backup_base ) ); ?>"><?php esc_html_e( 'Exportar cópia CSV', 'adam-bot' ); ?></a></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="adam_bot_import_knowledge" />
				<?php wp_nonce_field( 'adam_bot_knowledge_transfer' ); ?>
				<input type="file" name="adam_bot_import_file" accept=".json,.csv,application/json,text/csv" required />
				<?php submit_button( __( 'Importar conhecimento', 'adam-bot' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/** @param array<int,array<string,int|string>> $matches Similar rows. */
	private function render_duplicate_matches( array $matches ): void {
		if ( empty( $matches ) ) {
			echo '<p class="description">' . esc_html__( 'Não foram detetados duplicados prováveis.', 'adam-bot' ) . '</p>';
			return;
		}
		echo '<p><strong>' . esc_html__( 'Possível duplicado detetado', 'adam-bot' ) . '</strong></p><ul>';
		foreach ( $matches as $match ) {
			$url = get_edit_post_link( (int) $match['id'] );
			echo '<li><a href="' . esc_url( (string) $url ) . '">' . esc_html( (string) $match['question'] ) . '</a><br><strong>' . esc_html( (string) $match['similarity'] ) . '%</strong> ' . esc_html__( 'semelhante', 'adam-bot' ) . '</li>';
		}
		echo '</ul>';
	}

	private function authorize_ajax(): void {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissão recusada.', 'adam-bot' ) ), 403 );
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
