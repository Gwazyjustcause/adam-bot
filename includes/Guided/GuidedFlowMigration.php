<?php
/** Populates the guided tree from the existing ADAM Bot knowledge model. */

declare(strict_types=1);

namespace AdamBot\Guided;

use AdamBot\Knowledge\EntrySchema;
use AdamBot\Knowledge\Sources\ManualSource;

defined( 'ABSPATH' ) || exit;

/** Builds a reviewable, deduplicated guided structure without changing legacy content. */
final class GuidedFlowMigration {
	private const ACTION = 'adam_bot_populate_guided_flow';
	private const NONCE = 'adam_bot_guided_migration';
	private const VERSION = 2;
	private const ROOTS = array(
		'membership' => array( '🤝', 'Sócios e inscrições' ),
		'events' => array( '🎯', 'Jogos e eventos' ),
		'airsoft' => array( '🪖', 'Começar no Airsoft' ),
		'teams' => array( '👥', 'Equipas' ),
		'fields' => array( '🗺️', 'Campos' ),
		'about' => array( '🏛️', 'Sobre a ADAM' ),
		'partners' => array( '🤝', 'Parcerias e colaboração' ),
		'help' => array( '💬', 'Ajuda e contactos' ),
	);

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 26 );
		add_action( 'admin_init', array( $this, 'maybe_populate' ), 31 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'populate' ) );
	}

	public function maybe_populate(): void {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wp_insert_post' ) || self::VERSION <= (int) get_option( FlowSchema::POPULATED_OPTION, 0 ) ) return;
		$this->populate_structure();
	}

	public function register_menu(): void {
		add_submenu_page( 'adam-bot', 'Migrar para estrutura guiada', 'Migrar conteúdo', 'manage_options', 'adam-bot-guided-migration', array( $this, 'render_page' ) );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão recusada.', 'adam-bot' ) );
		$rows = $this->scan();
		$counts = array_count_values( array_map( static function ( array $row ): string { return $row['result']; }, $rows ) );
		$action = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::NONCE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Popular a estrutura guiada', 'adam-bot' ); ?></h1>
			<p><?php esc_html_e( 'Esta migração lê a Base de Conhecimento existente, consolida perguntas semelhantes, liga páginas e URLs verificadas, cria ramos dinâmicos e publica apenas conteúdo seguro. As entradas originais não são alteradas.', 'adam-bot' ); ?></p>
			<div class="notice notice-info"><p><?php echo esc_html( sprintf( __( '%d entradas analisadas · %d com conteúdo utilizável · %d sem conteúdo ou categoria clara · %d já ligadas.', 'adam-bot' ), count( $rows ), $counts['ready'] ?? 0, ( $counts['empty'] ?? 0 ) + ( $counts['unmatched'] ?? 0 ), $counts['existing'] ?? 0 ) ); ?></p></div>
			<?php if ( ! empty( $rows ) ) : ?>
			<table class="widefat striped"><thead><tr><th>Entrada</th><th>Ramo</th><th>Estado</th><th>Notas</th></tr></thead><tbody>
			<?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['label'] ); ?> <small>(#<?php echo esc_html( (string) $row['id'] ); ?>)</small></td><td><?php echo esc_html( $row['parent_label'] ?: '—' ); ?></td><td><strong><?php echo esc_html( $this->result_label( $row['result'] ) ); ?></strong></td><td><?php echo esc_html( $row['notes'] ); ?></td></tr><?php endforeach; ?>
			</tbody></table>
			<?php endif; ?>
			<p><a class="button button-primary" href="<?php echo esc_url( $action ); ?>"><?php esc_html_e( 'Popular e publicar a estrutura segura', 'adam-bot' ); ?></a></p>
			<p class="description"><?php esc_html_e( 'A operação é idempotente. Nós com informação insuficiente ficam marcados como “Por rever” e permanecem fora do fluxo público.', 'adam-bot' ); ?></p>
		</div>
		<?php
	}

	public function populate(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão recusada.', 'adam-bot' ) );
		check_admin_referer( self::NONCE );
		$this->populate_structure();
		wp_safe_redirect( add_query_arg( array( 'page' => 'adam-bot-guided-migration', 'populated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function populate_structure(): void {
		$roots = array();
		foreach ( self::ROOTS as $key => $spec ) {
			$roots[ $key ] = $this->ensure_node( 'root-' . $key, $spec[1], 'menu', 0, $spec[0], 'publish', 'migrated' );
		}
		$this->ensure_dynamic_branch( $roots['membership'], 'membership', 'Quotas e renovação', 'membership' );
		$this->ensure_dynamic_branch( $roots['events'], 'events', 'Próximos jogos e eventos', 'event' );
		$this->ensure_dynamic_branch( $roots['teams'], 'teams', 'Encontrar uma equipa', 'teams' );
		$this->ensure_dynamic_branch( $roots['fields'], 'fields', 'Encontrar um campo', 'fields' );
		$this->ensure_dynamic_branch( $roots['partners'], 'partners', 'Parceiros e vantagens', 'partners' );
		$this->ensure_dynamic_branch( $roots['help'], 'documents', 'Documentos e recursos', 'documents' );
		$groups = array();
		foreach ( $this->scan() as $row ) {
			if ( 'ready' !== $row['result'] || 0 === (int) $row['parent_id'] ) continue;
			$groups[ $row['parent_id'] . '|' . $row['group_key'] ][] = $row;
		}
		foreach ( $groups as $rows ) $this->create_group_node( $rows );
		update_option( FlowSchema::POPULATED_OPTION, self::VERSION, false );
	}

	/** @return array<int,array<string,mixed>> */
	private function scan(): array {
		$posts = get_posts( array( 'post_type' => ManualSource::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
		$rows = array();
		foreach ( $posts as $post ) {
			$id = (int) $post->ID;
			$label = trim( (string) get_post_meta( $id, EntrySchema::QUESTION_META, true ) ) ?: (string) $post->post_title;
			$existing = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 1, 'meta_key' => FlowSchema::LEGACY_ID_META, 'meta_value' => $id, 'no_found_rows' => true ) );
			if ( ! empty( $existing ) ) { $rows[] = $this->row( $id, $label, 'existing', 0, '', '', 'Já existe uma ligação guiada para esta entrada.' ); continue; }
			if ( '' === $this->content( $post ) ) { $rows[] = $this->row( $id, $label, 'empty', 0, '', '', 'Sem resposta verificável.' ); continue; }
			$classification = $this->classify( $label . ' ' . $this->content( $post ) . ' ' . $this->categories( $id ) );
			if ( ! isset( self::ROOTS[ $classification['root'] ?? '' ] ) ) { $rows[] = $this->row( $id, $label, 'unmatched', 0, '', '', 'Não foi possível atribuir este conteúdo com segurança.' ); continue; }
			$parent = $this->find_node( 'root-' . $classification['root'] );
			$rows[] = $this->row( $id, $label, 'ready', $parent, self::ROOTS[ $classification['root'] ][1], $classification['group'], $classification['note'] );
		}
		return $rows;
	}

	private function create_group_node( array $rows ): void {
		$first = $rows[0];
		$parent = (int) $first['parent_id'];
		$key = 'group-' . md5( $parent . '|' . $first['group_key'] );
		$label = $this->group_label( (string) $first['group_key'] );
		$all_published = true;
		$content = array(); $blocks = array(); $actions = array(); $legacy_ids = array();
		foreach ( $rows as $row ) {
			$source = get_post( (int) $row['id'] );
			if ( ! is_object( $source ) ) continue;
			if ( 'publish' !== (string) $source->post_status ) $all_published = false;
			$legacy_ids[] = (int) $row['id'];
			$answer = $this->content( $source ); if ( '' !== $answer ) $content[] = $answer;
			$blocks = array_merge( $blocks, EntrySchema::sanitizeBlocks( get_post_meta( $source->ID, EntrySchema::RESPONSE_BLOCKS_META, true ) ) );
			$actions = array_merge( $actions, $this->source_actions( $source->ID ) );
		}
		$node = $this->ensure_node( $key, $label, 'answer', $parent, '', $all_published ? 'publish' : 'draft', $all_published ? 'migrated' : 'unreviewed' );
		$seen = array(); $deduped = array();
		foreach ( $actions as $action ) { $signature = md5( wp_json_encode( $action ) ); if ( isset( $seen[ $signature ] ) ) continue; $seen[ $signature ] = true; $deduped[] = $action; }
		update_post_meta( $node, FlowSchema::DIRECT_ANSWER_META, $this->first_sentence( $content[0] ?? '' ) );
		update_post_meta( $node, EntrySchema::RESPONSE_BLOCKS_META, EntrySchema::sanitizeBlocks( $blocks ) );
		update_post_meta( $node, FlowSchema::ACTIONS_META, FlowSchema::sanitizeActions( $deduped ) );
		update_post_meta( $node, FlowSchema::LEGACY_ID_META, $legacy_ids[0] ?? 0 );
		update_post_meta( $node, FlowSchema::MIGRATION_NOTES_META, sprintf( 'Consolidado de %d entradas legadas.', count( $legacy_ids ) ) );
		wp_update_post( array( 'ID' => $node, 'post_content' => wp_kses_post( implode( "\n\n", array_unique( $content ) ) ) ) );
	}

	private function ensure_dynamic_branch( int $parent, string $key, string $label, string $provider ): int {
		$id = $this->ensure_node( 'dynamic-' . $key, $label, 'dynamic', $parent, '🔎', 'publish', 'migrated' );
		update_post_meta( $id, FlowSchema::PROVIDER_META, $provider );
		update_post_meta( $id, FlowSchema::INTRO_META, 'Informação atualizada a partir dos dados disponíveis no ADAM BOT.' );
		return $id;
	}

	private function ensure_node( string $key, string $label, string $type, int $parent, string $icon, string $status, string $migration ): int {
		$existing = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 1, 'meta_key' => FlowSchema::MIGRATION_KEY_META, 'meta_value' => $key, 'no_found_rows' => true ) );
		if ( empty( $existing ) ) $existing = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 1, 'title' => $label, 'post_parent' => $parent, 'no_found_rows' => true ) );
		$id = ! empty( $existing ) ? (int) $existing[0]->ID : (int) wp_insert_post( array( 'post_type' => FlowSchema::POST_TYPE, 'post_title' => $label, 'post_status' => $status, 'post_parent' => $parent, 'menu_order' => 0, 'post_content' => '' ), true );
		if ( $id <= 0 || is_wp_error( $id ) ) return 0;
		wp_update_post( array( 'ID' => $id, 'post_title' => $label, 'post_parent' => $parent, 'post_status' => $status ) );
		update_post_meta( $id, FlowSchema::MIGRATION_KEY_META, $key ); update_post_meta( $id, FlowSchema::NODE_TYPE_META, $type ); update_post_meta( $id, FlowSchema::LABEL_META, $label ); update_post_meta( $id, FlowSchema::ICON_META, $icon ); update_post_meta( $id, FlowSchema::LANGUAGE_META, 'pt' ); update_post_meta( $id, FlowSchema::MIGRATION_STATUS_META, $migration );
		return $id;
	}

	private function find_node( string $key ): int { $posts = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 1, 'meta_key' => FlowSchema::MIGRATION_KEY_META, 'meta_value' => $key, 'fields' => 'ids', 'no_found_rows' => true ) ); return empty( $posts ) ? 0 : (int) $posts[0]; }
	private function content( $post ): string { $blocks = EntrySchema::sanitizeBlocks( get_post_meta( $post->ID, EntrySchema::RESPONSE_BLOCKS_META, true ) ); $value = ! empty( $blocks ) ? EntrySchema::blocksToText( $blocks ) : (string) $post->post_content; return trim( wp_strip_all_tags( strip_shortcodes( $value ) ) ); }
	private function categories( int $id ): string { $terms = get_the_terms( $id, EntrySchema::TAXONOMY ); return is_array( $terms ) ? implode( ' ', array_map( static function ( $term ): string { return (string) $term->name; }, $terms ) ) : ''; }

	/** @return array{root:string,group:string,note:string} */
	private function classify( string $value ): array {
		$text = strtolower( remove_accents( $value ) );
		$rules = array( 'membership' => array( 'socio', 'inscri', 'quota', 'membro', 'membership', 'renew', 'renov' ), 'events' => array( 'evento', 'jogo', 'game', 'partida', 'calend' ), 'airsoft' => array( 'airsoft', 'comec', 'regra', 'equipamento', 'seguranca', 'inici' ), 'teams' => array( 'equipa', 'team' ), 'fields' => array( 'campo', 'field', 'local' ), 'partners' => array( 'parceir', 'partner', 'desconto', 'vantagem', 'colabora' ), 'about' => array( 'adam', 'associa', 'sobre', 'historia', 'missao' ), 'help' => array( 'ajuda', 'contact', 'contacto', 'document', 'estatuto', 'duvida' ) );
		foreach ( $rules as $root => $terms ) foreach ( $terms as $term ) if ( false !== strpos( $text, $term ) ) return array( 'root' => $root, 'group' => $this->canonical_group( $root, $text ), 'note' => '' );
		return array( 'root' => '', 'group' => '', 'note' => 'Classificação incerta; mantido fora do fluxo público.' );
	}

	private function canonical_group( string $root, string $text ): string { if ( 'membership' === $root && ( false !== strpos( $text, 'quota' ) || false !== strpos( $text, 'renov' ) ) ) return 'membership-renew'; if ( 'membership' === $root ) return 'membership-join'; if ( 'events' === $root ) return 'events-participate'; if ( 'airsoft' === $root ) return 'airsoft-start'; if ( 'about' === $root ) return 'about-adam'; if ( 'partners' === $root ) return 'partners-info'; if ( 'help' === $root ) return 'help-contact'; return $root . '-info'; }
	private function group_label( string $key ): string { $labels = array( 'membership-join' => 'Quero tornar-me sócio', 'membership-renew' => 'Quotas e renovação', 'events-participate' => 'Participar nos jogos e eventos', 'airsoft-start' => 'Como começar no airsoft', 'about-adam' => 'O que é a ADAM?', 'partners-info' => 'Parcerias e vantagens', 'help-contact' => 'Ajuda e contactos', 'teams-info' => 'Informação sobre equipas', 'fields-info' => 'Informação sobre campos' ); return $labels[ $key ] ?? 'Mais informação'; }
	private function first_sentence( string $text ): string { $text = trim( $text ); if ( '' === $text ) return ''; $parts = preg_split( '/(?<=[.!?])\s+/u', $text, 2 ); return trim( (string) ( $parts[0] ?? $text ) ); }
	private function source_actions( int $id ): array { $actions = array(); $page_id = absint( get_post_meta( $id, EntrySchema::RELATED_PAGE_META, true ) ); $button = sanitize_text_field( (string) get_post_meta( $id, EntrySchema::BUTTON_TEXT_META, true ) ); $url = esc_url_raw( (string) get_post_meta( $id, EntrySchema::BUTTON_URL_META, true ) ); if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) $actions[] = array( 'label' => $button ?: get_the_title( $page_id ), 'type' => 'page', 'page_id' => $page_id, 'node_id' => 0, 'url' => '' ); elseif ( '' !== $url ) $actions[] = array( 'label' => $button ?: 'Saber mais', 'type' => 'url', 'page_id' => 0, 'node_id' => 0, 'url' => $url ); return $actions; }
	private function row( int $id, string $label, string $result, int $parent_id, string $parent_label, string $group_key, string $notes ): array { return compact( 'id', 'label', 'result', 'parent_id', 'parent_label', 'group_key', 'notes' ); }
	private function result_label( string $result ): string { return array( 'ready' => 'Pronto', 'existing' => 'Já ligado', 'unmatched' => 'Precisa de revisão', 'empty' => 'Sem conteúdo' )[ $result ] ?? $result; }
}
