<?php
/** Non-destructive migration assistant for legacy Knowledge entries. */

declare(strict_types=1);

namespace AdamBot\Guided;

use AdamBot\Knowledge\EntrySchema;
use AdamBot\Knowledge\Sources\ManualSource;

defined( 'ABSPATH' ) || exit;

/** Reports and optionally creates draft guided answers from existing entries. */
final class GuidedFlowMigration {
	private const ACTION = 'adam_bot_create_guided_drafts';
	private const NONCE = 'adam_bot_guided_migration';

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 26 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'create_drafts' ) );
	}

	public function register_menu(): void {
		add_submenu_page( 'adam-bot', __( 'Migrar para estrutura guiada', 'adam-bot' ), __( 'Migrar conteúdo', 'adam-bot' ), 'manage_options', 'adam-bot-guided-migration', array( $this, 'render_page' ) );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão recusada.', 'adam-bot' ) );
		$rows = $this->scan();
		$counts = array_count_values( array_map( static function ( array $row ): string { return $row['result']; }, $rows ) );
		$action = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::NONCE );
		?><div class="wrap"><h1><?php esc_html_e( 'Migrar conteúdo para a estrutura guiada', 'adam-bot' ); ?></h1><p><?php esc_html_e( 'Esta ferramenta cria apenas nós de resposta em rascunho. As entradas da Base de Conhecimento não são alteradas, publicadas ou apagadas.', 'adam-bot' ); ?></p><div class="notice notice-info"><p><?php echo esc_html( sprintf( __( '%d entradas analisadas · %d prontas para criar · %d já migradas · %d sem correspondência ou sem conteúdo.', 'adam-bot' ), count( $rows ), $counts['ready'] ?? 0, $counts['existing'] ?? 0, ( $counts['unmatched'] ?? 0 ) + ( $counts['empty'] ?? 0 ) + ( $counts['duplicate'] ?? 0 ) ) ); ?></p></div><?php if ( ! empty( $rows ) ) : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Entrada', 'adam-bot' ); ?></th><th><?php esc_html_e( 'Categoria sugerida', 'adam-bot' ); ?></th><th><?php esc_html_e( 'Resultado', 'adam-bot' ); ?></th><th><?php esc_html_e( 'Notas', 'adam-bot' ); ?></th></tr></thead><tbody><?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['label'] ); ?> <small>(#<?php echo esc_html( (string) $row['id'] ); ?>)</small></td><td><?php echo esc_html( $row['parent_label'] ?: '—' ); ?></td><td><strong><?php echo esc_html( $this->result_label( $row['result'] ) ); ?></strong></td><td><?php echo esc_html( $row['notes'] ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p><?php esc_html_e( 'Não foram encontradas entradas de conhecimento para analisar.', 'adam-bot' ); ?></p><?php endif; ?><p><a class="button button-primary" href="<?php echo esc_url( $action ); ?>"><?php esc_html_e( 'Criar nós de rascunho prontos para revisão', 'adam-bot' ); ?></a></p><p class="description"><?php esc_html_e( 'Depois de criar os rascunhos, reveja cada resposta, ajuste o pai e as ações, e publique apenas o que estiver confirmado.', 'adam-bot' ); ?></p></div><?php
	}

	public function create_drafts(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão recusada.', 'adam-bot' ) );
		check_admin_referer( self::NONCE );
		$created = 0;
		foreach ( $this->scan() as $row ) {
			if ( 'ready' !== $row['result'] ) continue;
			$source = get_post( $row['id'] );
			if ( ! is_object( $source ) ) continue;
			$node_id = wp_insert_post( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => 'draft', 'post_title' => $row['label'], 'post_content' => wp_kses_post( (string) $source->post_content ), 'post_parent' => $row['parent_id'], 'menu_order' => 0 ), true );
			if ( is_wp_error( $node_id ) ) continue;
			update_post_meta( $node_id, FlowSchema::NODE_TYPE_META, 'answer' );
			update_post_meta( $node_id, FlowSchema::LABEL_META, $row['label'] );
			update_post_meta( $node_id, FlowSchema::LANGUAGE_META, EntrySchema::sanitizeLanguage( get_post_meta( $row['id'], EntrySchema::LANGUAGE_META, true ) ) );
			update_post_meta( $node_id, FlowSchema::MIGRATION_STATUS_META, 'unreviewed' );
			update_post_meta( $node_id, FlowSchema::LEGACY_ID_META, $row['id'] );
			update_post_meta( $node_id, FlowSchema::MIGRATION_NOTES_META, $row['notes'] );
			$blocks = EntrySchema::sanitizeBlocks( get_post_meta( $row['id'], EntrySchema::RESPONSE_BLOCKS_META, true ) );
			if ( ! empty( $blocks ) ) update_post_meta( $node_id, EntrySchema::RESPONSE_BLOCKS_META, $blocks );
			$actions = $this->source_actions( $row['id'] );
			if ( ! empty( $actions ) ) update_post_meta( $node_id, FlowSchema::ACTIONS_META, $actions );
			$created++;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'adam-bot-guided-migration', 'created' => $created ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** @return array<int,array<string,mixed>> */
	private function scan(): array {
		$posts = get_posts( array( 'post_type' => ManualSource::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
		$rows = array();
		foreach ( $posts as $post ) {
			$id = (int) $post->ID;
			$label = trim( (string) get_post_meta( $id, EntrySchema::QUESTION_META, true ) );
			$label = '' !== $label ? $label : (string) $post->post_title;
			$existing = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 1, 'meta_key' => FlowSchema::LEGACY_ID_META, 'meta_value' => $id, 'no_found_rows' => true ) );
			if ( ! empty( $existing ) ) { $rows[] = $this->row( $id, $label, 'existing', 0, '', __( 'Já existe um nó ligado a esta entrada.', 'adam-bot' ) ); continue; }
			$content = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
			$blocks = EntrySchema::sanitizeBlocks( get_post_meta( $id, EntrySchema::RESPONSE_BLOCKS_META, true ) );
			if ( '' === $content && empty( $blocks ) ) { $rows[] = $this->row( $id, $label, 'empty', 0, '', __( 'Não há resposta para migrar.', 'adam-bot' ) ); continue; }
			$categories = get_the_terms( $id, EntrySchema::TAXONOMY );
			$category_names = is_array( $categories ) ? array_map( static function ( $term ): string { return (string) $term->name; }, $categories ) : array();
			$parent = $this->parent_for( $category_names );
			if ( 0 === $parent['id'] ) { $rows[] = $this->row( $id, $label, 'unmatched', 0, '', sprintf( __( 'Categoria original: %s. Escolha manualmente um destino.', 'adam-bot' ), implode( ', ', $category_names ) ?: __( 'sem categoria', 'adam-bot' ) ) ); continue; }
			if ( $this->same_label_exists( $label, $parent['id'] ) ) { $rows[] = $this->row( $id, $label, 'duplicate', 0, '', __( 'Existe um nó com o mesmo rótulo neste ramo.', 'adam-bot' ) ); continue; }
			$rows[] = $this->row( $id, $label, 'ready', $parent['id'], $parent['label'], __( 'Será criado como rascunho e marcado por rever.', 'adam-bot' ) );
		}
		return $rows;
	}

	private function parent_for( array $categories ): array {
		$haystack = strtolower( implode( ' ', $categories ) );
		$map = array( 'Sócios e inscrições' => array( 'membership', 'sócio', 'socio', 'quota', 'inscri' ), 'Jogos e eventos' => array( 'event', 'jogo' ), 'Começar no Airsoft' => array( 'airsoft', 'regra', 'inici' ), 'Equipas' => array( 'team', 'equipa', 'equip' ), 'Campos' => array( 'field', 'campo' ), 'Sobre a ADAM' => array( 'association', 'associa', 'adam', 'about' ), 'Parcerias e colaboração' => array( 'partner', 'parceir', 'colabora' ), 'Ajuda e contactos' => array( 'contact', 'contacto', 'ajuda', 'help' ) );
		foreach ( $map as $label => $terms ) foreach ( $terms as $term ) if ( false !== strpos( $haystack, strtolower( $term ) ) ) { $roots = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'title' => $label, 'post_parent' => 0, 'posts_per_page' => 1, 'no_found_rows' => true ) ); return ! empty( $roots ) ? array( 'id' => (int) $roots[0]->ID, 'label' => $label ) : array( 'id' => 0, 'label' => '' ); }
		return array( 'id' => 0, 'label' => '' );
	}

	private function same_label_exists( string $label, int $parent_id ): bool {
		$nodes = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'post_parent' => $parent_id, 'posts_per_page' => -1, 'no_found_rows' => true ) );
		$needle = strtolower( trim( $label ) );
		foreach ( $nodes as $node ) if ( $needle === strtolower( trim( (string) get_post_meta( $node->ID, FlowSchema::LABEL_META, true ) ?: $node->post_title ) ) ) return true;
		return false;
	}

	private function source_actions( int $id ): array {
		$actions = array();
		$page_id = absint( get_post_meta( $id, EntrySchema::RELATED_PAGE_META, true ) );
		$button = sanitize_text_field( (string) get_post_meta( $id, EntrySchema::BUTTON_TEXT_META, true ) );
		$url = esc_url_raw( (string) get_post_meta( $id, EntrySchema::BUTTON_URL_META, true ) );
		if ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) $actions[] = array( 'label' => $button ?: get_the_title( $page_id ), 'type' => 'page', 'page_id' => $page_id, 'node_id' => 0, 'url' => '' );
		elseif ( '' !== $url ) $actions[] = array( 'label' => $button ?: __( 'Saber mais', 'adam-bot' ), 'type' => 'url', 'page_id' => 0, 'node_id' => 0, 'url' => $url );
		return FlowSchema::sanitizeActions( $actions );
	}

	private function row( int $id, string $label, string $result, int $parent_id, string $parent_label, string $notes ): array { return compact( 'id', 'label', 'result', 'parent_id', 'parent_label', 'notes' ); }
	private function result_label( string $result ): string { $labels = array( 'ready' => __( 'Pronto', 'adam-bot' ), 'existing' => __( 'Já migrado', 'adam-bot' ), 'unmatched' => __( 'Sem destino', 'adam-bot' ), 'empty' => __( 'Sem conteúdo', 'adam-bot' ), 'duplicate' => __( 'Possível duplicado', 'adam-bot' ) ); return $labels[ $result ] ?? $result; }
}
