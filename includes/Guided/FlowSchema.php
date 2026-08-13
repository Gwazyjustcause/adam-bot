<?php
/**
 * Schema for the guided ADAM BOT content tree.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Guided;

defined( 'ABSPATH' ) || exit;

/** Centralizes guided-node metadata and validation. */
final class FlowSchema {
	public const POST_TYPE = 'adam_bot_flow';
	public const NODE_TYPE_META = '_adam_bot_flow_node_type';
	public const LABEL_META = '_adam_bot_flow_label';
	public const ICON_META = '_adam_bot_flow_icon';
	public const INTRO_META = '_adam_bot_flow_intro';
	public const DIRECT_ANSWER_META = '_adam_bot_flow_direct_answer';
	public const LANGUAGE_META = '_adam_bot_flow_language';
	public const PROVIDER_META = '_adam_bot_flow_provider';
	public const ACTIONS_META = '_adam_bot_flow_actions';
	public const MIGRATION_STATUS_META = '_adam_bot_flow_migration_status';
	public const LEGACY_ID_META = '_adam_bot_flow_legacy_id';
	public const MIGRATION_NOTES_META = '_adam_bot_flow_migration_notes';
	public const SEEDED_OPTION = 'adam_bot_guided_seed_version';

	public static function nodeTypes(): array {
		return array(
			'menu' => __( 'Menu', 'adam-bot' ),
			'answer' => __( 'Resposta', 'adam-bot' ),
			'dynamic' => __( 'Dinâmico', 'adam-bot' ),
			'redirect' => __( 'Redirecionamento', 'adam-bot' ),
		);
	}

	public static function actionTypes(): array {
		return array(
			'node' => __( 'Nó ADAM BOT', 'adam-bot' ),
			'page' => __( 'Página WordPress', 'adam-bot' ),
			'url' => __( 'URL externa', 'adam-bot' ),
		);
	}

	public static function migrationStatuses(): array {
		return array(
			'new' => __( 'Novo', 'adam-bot' ),
			'unreviewed' => __( 'Por rever', 'adam-bot' ),
			'reviewed' => __( 'Revisto', 'adam-bot' ),
			'migrated' => __( 'Migrado', 'adam-bot' ),
			'duplicate' => __( 'Duplicado', 'adam-bot' ),
			'not_suitable' => __( 'Não adequado', 'adam-bot' ),
		);
	}

	public static function nodeType( $value ): string {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, self::nodeTypes() ) ? $value : 'menu';
	}

	public static function language( $value ): string {
		return 'en' === sanitize_key( (string) $value ) ? 'en' : 'pt';
	}

	public static function migrationStatus( $value ): string {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, self::migrationStatuses() ) ? $value : 'new';
	}

	public static function actionType( $value ): string {
		$value = sanitize_key( (string) $value );
		return array_key_exists( $value, self::actionTypes() ) ? $value : 'node';
	}

	public static function sanitizeActions( $value ): array {
		if ( ! is_array( $value ) ) return array();
		$actions = array();
		foreach ( $value as $action ) {
			if ( ! is_array( $action ) ) continue;
			$label = sanitize_text_field( (string) ( $action['label'] ?? '' ) );
			$type = self::actionType( $action['type'] ?? 'node' );
			$node_id = absint( $action['node_id'] ?? 0 );
			$page_id = absint( $action['page_id'] ?? 0 );
			$url = esc_url_raw( (string) ( $action['url'] ?? '' ) );
			if ( '' === $label ) continue;
			if ( 'node' === $type && $node_id <= 0 ) continue;
			if ( 'page' === $type && $page_id <= 0 ) continue;
			if ( 'url' === $type && '' === $url ) continue;
			$actions[] = array( 'label' => substr( $label, 0, 120 ), 'type' => $type, 'node_id' => $node_id, 'page_id' => $page_id, 'url' => substr( $url, 0, 1000 ) );
			if ( count( $actions ) >= 20 ) break;
		}
		return $actions;
	}
}
