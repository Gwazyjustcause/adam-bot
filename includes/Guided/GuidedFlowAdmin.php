<?php
/** Guided ADAM BOT content administration. */

declare(strict_types=1);

namespace AdamBot\Guided;

use AdamBot\Knowledge\Dynamic\DynamicProviderRegistry;

defined( 'ABSPATH' ) || exit;

/** Registers the guided tree, editor, validation, and safe draft seed. */
final class GuidedFlowAdmin {
	private const NONCE = 'adam_bot_flow_nonce';
	private DynamicProviderRegistry $providers;

	public function __construct( DynamicProviderRegistry $providers ) {
		$this->providers = $providers;
	}

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_content_type' ), 2 );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . FlowSchema::POST_TYPE, array( $this, 'save_node' ), 20, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'seed_root_nodes' ) );
	}

	public function register_content_type(): void {
		register_post_type( FlowSchema::POST_TYPE, array(
			'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_menu' => false,
			'show_in_rest' => false, 'hierarchical' => true, 'supports' => array( 'title', 'editor', 'revisions', 'page-attributes' ),
			'capability_type' => 'post', 'map_meta_cap' => false,
			'capabilities' => array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'manage_options' ),
			'labels' => array(
				'name' => __( 'Estrutura do ADAM BOT', 'adam-bot' ), 'singular_name' => __( 'Nó do ADAM BOT', 'adam-bot' ),
				'add_new_item' => __( 'Adicionar nó ao ADAM BOT', 'adam-bot' ), 'edit_item' => __( 'Editar nó do ADAM BOT', 'adam-bot' ),
			),
			'menu_icon' => 'dashicons-networking',
		) );
	}

	public function register_menu(): void {
		add_submenu_page( 'adam-bot', __( 'Estrutura guiada', 'adam-bot' ), __( 'Estrutura guiada', 'adam-bot' ), 'manage_options', 'adam-bot-flow', array( $this, 'render_structure' ) );
	}

	public function register_meta_boxes(): void {
		add_meta_box( 'adam-bot-flow-settings', __( 'Configuração do nó', 'adam-bot' ), array( $this, 'render_settings_box' ), FlowSchema::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'adam-bot-flow-actions', __( 'Ações', 'adam-bot' ), array( $this, 'render_actions_box' ), FlowSchema::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'adam-bot-flow-migration', __( 'Migração e revisão', 'adam-bot' ), array( $this, 'render_migration_box' ), FlowSchema::POST_TYPE, 'side', 'default' );
	}

	public function render_settings_box( $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		$type = FlowSchema::nodeType( get_post_meta( $post->ID, FlowSchema::NODE_TYPE_META, true ) );
		$label = (string) get_post_meta( $post->ID, FlowSchema::LABEL_META, true );
		$icon = (string) get_post_meta( $post->ID, FlowSchema::ICON_META, true );
		$intro = (string) get_post_meta( $post->ID, FlowSchema::INTRO_META, true );
		$provider = sanitize_key( (string) get_post_meta( $post->ID, FlowSchema::PROVIDER_META, true ) );
		$parents = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'post__not_in' => array( (int) $post->ID ), 'orderby' => 'menu_order title', 'order' => 'ASC', 'fields' => 'all' ) );
		?>
		<div class="adam-bot-flow-form-grid">
			<p><label for="adam-bot-flow-type"><strong><?php esc_html_e( 'Tipo de nó', 'adam-bot' ); ?></strong></label><select id="adam-bot-flow-type" name="adam_bot_flow_node_type" data-flow-type><?php foreach ( FlowSchema::nodeTypes() as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select></p>
			<p><label for="adam-bot-flow-label"><strong><?php esc_html_e( 'Rótulo público', 'adam-bot' ); ?></strong></label><input class="widefat" id="adam-bot-flow-label" name="adam_bot_flow_label" maxlength="120" value="<?php echo esc_attr( $label ); ?>" /><span class="description"><?php esc_html_e( 'O texto que o visitante verá no menu ou ação.', 'adam-bot' ); ?></span></p>
			<p><label for="adam-bot-flow-icon"><strong><?php esc_html_e( 'Ícone', 'adam-bot' ); ?></strong></label><input class="small-text" id="adam-bot-flow-icon" name="adam_bot_flow_icon" maxlength="8" value="<?php echo esc_attr( $icon ); ?>" placeholder="🤝" /></p>
			<p><label for="adam-bot-flow-parent"><strong><?php esc_html_e( 'Nó pai', 'adam-bot' ); ?></strong></label><select class="widefat" id="adam-bot-flow-parent" name="post_parent"><option value="0"><?php esc_html_e( 'Raiz principal', 'adam-bot' ); ?></option><?php foreach ( $parents as $parent ) : ?><option value="<?php echo esc_attr( (string) $parent->ID ); ?>" <?php selected( (int) $post->post_parent, (int) $parent->ID ); ?>><?php echo esc_html( str_repeat( '— ', $this->depth( $parent ) ) . $this->publicLabel( $parent ) ); ?></option><?php endforeach; ?></select></p>
			<p><label for="adam-bot-flow-order"><strong><?php esc_html_e( 'Ordem', 'adam-bot' ); ?></strong></label><input type="number" id="adam-bot-flow-order" name="menu_order" min="0" max="9999" value="<?php echo esc_attr( (string) $post->menu_order ); ?>" /></p>
			<p class="flow-type-menu"><label for="adam-bot-flow-intro"><strong><?php esc_html_e( 'Introdução do menu', 'adam-bot' ); ?></strong></label><textarea class="widefat" id="adam-bot-flow-intro" name="adam_bot_flow_intro" rows="3" maxlength="600"><?php echo esc_textarea( $intro ); ?></textarea></p>
			<p class="flow-type-dynamic"><label for="adam-bot-flow-provider"><strong><?php esc_html_e( 'Fornecedor', 'adam-bot' ); ?></strong></label><select class="widefat" id="adam-bot-flow-provider" name="adam_bot_flow_provider"><option value=""><?php esc_html_e( 'Selecionar fornecedor', 'adam-bot' ); ?></option><?php foreach ( $this->providers->labels() as $key => $text ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $provider, $key ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select><span class="description"><?php esc_html_e( 'A configuração detalhada dos fornecedores será ligada numa fase posterior.', 'adam-bot' ); ?></span></p>
		</div>
		<p class="description flow-type-answer flow-type-redirect flow-type-dynamic"><?php esc_html_e( 'Use o editor principal acima para escrever a resposta ou as instruções verificadas. Não introduza informação que ainda não tenha sido confirmada.', 'adam-bot' ); ?></p>
		<?php
	}

	public function render_actions_box( $post ): void {
		$actions = FlowSchema::sanitizeActions( get_post_meta( $post->ID, FlowSchema::ACTIONS_META, true ) );
		if ( empty( $actions ) ) $actions = array( array( 'label' => '', 'type' => 'node', 'node_id' => 0, 'page_id' => 0, 'url' => '' ) );
		$nodes = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'post__not_in' => array( (int) $post->ID ), 'orderby' => 'menu_order title', 'order' => 'ASC', 'fields' => 'all' ) );
		$pages = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order,post_title' ) );
		?><p class="description"><?php esc_html_e( 'A primeira ação será apresentada como principal. Pode adicionar quantas ações forem necessárias.', 'adam-bot' ); ?></p><div id="adam-bot-flow-actions-list"><?php foreach ( $actions as $index => $action ) $this->render_action( (string) $index, $action, $nodes, $pages ); ?></div><p><button type="button" class="button" data-flow-add-action><?php esc_html_e( 'Adicionar ação', 'adam-bot' ); ?></button></p><template id="adam-bot-flow-action-template"><?php $this->render_action( '__INDEX__', array( 'label' => '', 'type' => 'node', 'node_id' => 0, 'page_id' => 0, 'url' => '' ), $nodes, $pages ); ?></template><?php
	}

	private function render_action( string $index, array $action, array $nodes, array $pages ): void {
		$type = FlowSchema::actionType( $action['type'] ?? 'node' );
		?><div class="adam-bot-flow-action" data-flow-action><span class="dashicons dashicons-menu" aria-hidden="true"></span><input class="regular-text" name="adam_bot_flow_actions[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( (string) ( $action['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Rótulo da ação', 'adam-bot' ); ?>" /><select name="adam_bot_flow_actions[<?php echo esc_attr( $index ); ?>][type]" data-flow-action-type><?php foreach ( FlowSchema::actionTypes() as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select><select name="adam_bot_flow_actions[<?php echo esc_attr( $index ); ?>][node_id]" data-flow-action-node><option value="0"><?php esc_html_e( 'Selecionar nó', 'adam-bot' ); ?></option><?php foreach ( $nodes as $node ) : ?><option value="<?php echo esc_attr( (string) $node->ID ); ?>" <?php selected( (int) ( $action['node_id'] ?? 0 ), (int) $node->ID ); ?>><?php echo esc_html( $this->publicLabel( $node ) ); ?></option><?php endforeach; ?></select><select name="adam_bot_flow_actions[<?php echo esc_attr( $index ); ?>][page_id]" data-flow-action-page><option value="0"><?php esc_html_e( 'Selecionar página', 'adam-bot' ); ?></option><?php foreach ( $pages as $page ) : ?><option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( (int) ( $action['page_id'] ?? 0 ), (int) $page->ID ); ?>><?php echo esc_html( get_the_title( $page ) ); ?></option><?php endforeach; ?></select><input type="url" name="adam_bot_flow_actions[<?php echo esc_attr( $index ); ?>][url]" data-flow-action-url value="<?php echo esc_attr( (string) ( $action['url'] ?? '' ) ); ?>" placeholder="https://…" /><button type="button" class="button-link-delete" data-flow-remove-action><?php esc_html_e( 'Remover', 'adam-bot' ); ?></button></div><?php
	}

	public function render_migration_box( $post ): void {
		$status = FlowSchema::migrationStatus( get_post_meta( $post->ID, FlowSchema::MIGRATION_STATUS_META, true ) );
		?><p><label for="adam-bot-flow-migration-status"><strong><?php esc_html_e( 'Estado de revisão', 'adam-bot' ); ?></strong></label><select class="widefat" id="adam-bot-flow-migration-status" name="adam_bot_flow_migration_status"><?php foreach ( FlowSchema::migrationStatuses() as $value => $text ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select></p><p><label for="adam-bot-flow-legacy-id"><strong><?php esc_html_e( 'ID de conhecimento legado', 'adam-bot' ); ?></strong></label><input class="small-text" type="number" id="adam-bot-flow-legacy-id" name="adam_bot_flow_legacy_id" min="0" value="<?php echo esc_attr( (string) absint( get_post_meta( $post->ID, FlowSchema::LEGACY_ID_META, true ) ) ); ?>" /></p><p><label for="adam-bot-flow-notes"><strong><?php esc_html_e( 'Notas de migração', 'adam-bot' ); ?></strong></label><textarea class="widefat" id="adam-bot-flow-notes" name="adam_bot_flow_migration_notes" rows="4" maxlength="2000"><?php echo esc_textarea( (string) get_post_meta( $post->ID, FlowSchema::MIGRATION_NOTES_META, true ) ); ?></textarea></p><?php
	}

	public function save_node( int $post_id, $post, bool $update ): void {
		unset( $update );
		static $saving = false;
		if ( $saving ) return;
		if ( ! is_object( $post ) || ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ), self::NONCE ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'manage_options' ) ) return;
		$type = FlowSchema::nodeType( $_POST['adam_bot_flow_node_type'] ?? 'menu' );
		$parent = absint( $_POST['post_parent'] ?? 0 );
		if ( $parent === $post_id || ( $parent > 0 && FlowSchema::POST_TYPE !== get_post_type( $parent ) ) ) $parent = 0;
		if ( $parent > 0 && $this->is_descendant( $parent, $post_id ) ) $parent = 0;
		$saving = true;
		wp_update_post( array( 'ID' => $post_id, 'post_parent' => $parent, 'menu_order' => max( 0, min( 9999, (int) ( $_POST['menu_order'] ?? 0 ) ) ) ) );
		$saving = false;
		update_post_meta( $post_id, FlowSchema::NODE_TYPE_META, $type );
		update_post_meta( $post_id, FlowSchema::LABEL_META, sanitize_text_field( wp_unslash( (string) ( $_POST['adam_bot_flow_label'] ?? '' ) ) ) );
		update_post_meta( $post_id, FlowSchema::ICON_META, substr( sanitize_text_field( wp_unslash( (string) ( $_POST['adam_bot_flow_icon'] ?? '' ) ) ), 0, 8 ) );
		update_post_meta( $post_id, FlowSchema::INTRO_META, sanitize_textarea_field( wp_unslash( (string) ( $_POST['adam_bot_flow_intro'] ?? '' ) ) ) );
		$provider = sanitize_key( (string) ( $_POST['adam_bot_flow_provider'] ?? '' ) );
		$known = array_keys( $this->providers->labels() );
		update_post_meta( $post_id, FlowSchema::PROVIDER_META, in_array( $provider, $known, true ) ? $provider : '' );
		update_post_meta( $post_id, FlowSchema::ACTIONS_META, FlowSchema::sanitizeActions( $_POST['adam_bot_flow_actions'] ?? array() ) );
		update_post_meta( $post_id, FlowSchema::MIGRATION_STATUS_META, FlowSchema::migrationStatus( $_POST['adam_bot_flow_migration_status'] ?? 'new' ) );
		update_post_meta( $post_id, FlowSchema::LEGACY_ID_META, absint( $_POST['adam_bot_flow_legacy_id'] ?? 0 ) );
		update_post_meta( $post_id, FlowSchema::MIGRATION_NOTES_META, sanitize_textarea_field( wp_unslash( (string) ( $_POST['adam_bot_flow_migration_notes'] ?? '' ) ) ) );
	}

	public function render_structure(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão recusada.', 'adam-bot' ) );
		$nodes = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
		$children = array(); foreach ( $nodes as $node ) $children[ (int) $node->post_parent ][] = $node;
		?><div class="wrap"><h1><?php esc_html_e( 'Estrutura guiada do ADAM BOT', 'adam-bot' ); ?> <a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FlowSchema::POST_TYPE ) ); ?>"><?php esc_html_e( 'Adicionar nó', 'adam-bot' ); ?></a></h1><p><?php esc_html_e( 'Esta é a futura árvore pública do ADAM BOT. Os nós continuam em rascunho até o conteúdo ser revisto.', 'adam-bot' ); ?></p><div class="adam-bot-flow-tree"><div class="adam-bot-flow-tree-root"><strong><?php esc_html_e( 'ADAM BOT — raiz principal', 'adam-bot' ); ?></strong><span class="description"><?php esc_html_e( 'A raiz não é um item editável; contém os nós sem pai.', 'adam-bot' ); ?></span></div><?php $this->render_tree( $children, 0, 0 ); if ( empty( $children[0] ?? array() ) ) echo '<p class="description">' . esc_html__( 'Ainda não existem nós na raiz.', 'adam-bot' ) . '</p>'; ?></div></div><?php
	}

	private function render_tree( array $children, int $parent, int $depth ): void { foreach ( $children[ $parent ] ?? array() as $node ) { $type = FlowSchema::nodeType( get_post_meta( $node->ID, FlowSchema::NODE_TYPE_META, true ) ); $status = FlowSchema::migrationStatus( get_post_meta( $node->ID, FlowSchema::MIGRATION_STATUS_META, true ) ); ?><div class="adam-bot-flow-tree-row" style="--flow-depth:<?php echo esc_attr( (string) $depth ); ?>"><span class="adam-bot-flow-tree-branch" aria-hidden="true">↳</span><span class="adam-bot-flow-tree-icon"><?php echo esc_html( (string) get_post_meta( $node->ID, FlowSchema::ICON_META, true ) ); ?></span><a href="<?php echo esc_url( get_edit_post_link( $node->ID ) ); ?>"><strong><?php echo esc_html( $this->publicLabel( $node ) ); ?></strong></a><span class="adam-bot-flow-badge adam-bot-flow-badge-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( FlowSchema::nodeTypes()[ $type ] ); ?></span><span class="adam-bot-flow-badge adam-bot-flow-status-<?php echo esc_attr( $node->post_status ); ?>"><?php echo esc_html( get_post_status_object( $node->post_status )->label ?? $node->post_status ); ?></span><span class="adam-bot-flow-badge adam-bot-flow-review-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( FlowSchema::migrationStatuses()[ $status ] ); ?></span><span class="adam-bot-flow-tree-actions"><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FlowSchema::POST_TYPE . '&post_parent=' . $node->ID ) ); ?>"><?php esc_html_e( 'Adicionar filho', 'adam-bot' ); ?></a></span></div><?php $this->render_tree( $children, (int) $node->ID, $depth + 1 ); } }

	private function publicLabel( $node ): string { $label = (string) get_post_meta( $node->ID, FlowSchema::LABEL_META, true ); return '' !== $label ? $label : (string) $node->post_title; }
	private function depth( $node ): int { $depth = 0; while ( $node && $node->post_parent && $depth < 20 ) { $depth++; $node = get_post( $node->post_parent ); } return $depth; }
	private function is_descendant( int $candidate, int $post_id ): bool { $seen = array(); while ( $candidate > 0 && ! isset( $seen[ $candidate ] ) ) { if ( $candidate === $post_id ) return true; $seen[ $candidate ] = true; $candidate = (int) wp_get_post_parent_id( $candidate ); } return false; }

	public function enqueue_assets( string $hook ): void { if ( false === strpos( $hook, 'adam-bot-flow' ) && ( ! function_exists( 'get_current_screen' ) || ! ( get_current_screen() && FlowSchema::POST_TYPE === get_current_screen()->post_type ) ) ) return; wp_enqueue_style( 'adam-bot-flow-admin', ADAM_BOT_URL . 'assets/css/adam-bot-flow-admin.css', array(), ADAM_BOT_VERSION ); wp_enqueue_script( 'adam-bot-flow-admin', ADAM_BOT_URL . 'assets/js/adam-bot-flow-admin.js', array(), ADAM_BOT_VERSION, true ); }

	public function seed_root_nodes(): void {
		if ( get_option( FlowSchema::SEEDED_OPTION, 0 ) || ! current_user_can( 'manage_options' ) ) return;
		$roots = array( array( '🤝', 'Sócios e inscrições' ), array( '🎯', 'Jogos e eventos' ), array( '🪖', 'Começar no Airsoft' ), array( '👥', 'Equipas' ), array( '🗺️', 'Campos' ), array( '🏛️', 'Sobre a ADAM' ), array( '🤝', 'Parcerias e colaboração' ), array( '💬', 'Ajuda e contactos' ) );
		foreach ( $roots as $index => $root ) { $existing = get_page_by_title( $root[1], OBJECT, FlowSchema::POST_TYPE ); if ( $existing ) continue; $id = wp_insert_post( array( 'post_type' => FlowSchema::POST_TYPE, 'post_title' => $root[1], 'post_status' => 'draft', 'menu_order' => $index, 'post_parent' => 0 ), true ); if ( is_wp_error( $id ) ) continue; update_post_meta( $id, FlowSchema::NODE_TYPE_META, 'menu' ); update_post_meta( $id, FlowSchema::LABEL_META, $root[1] ); update_post_meta( $id, FlowSchema::ICON_META, $root[0] ); update_post_meta( $id, FlowSchema::MIGRATION_STATUS_META, 'new' ); }
		update_option( FlowSchema::SEEDED_OPTION, 1, false );
	}
}
