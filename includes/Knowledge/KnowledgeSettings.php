<?php
/**
 * Knowledge settings repository.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Owns source enablement, selected pages, and cache versioning.
 */
final class KnowledgeSettings {
	/** WordPress option name. */
	public const OPTION_KEY = 'adam_bot_knowledge_settings';

	/** Cache namespace version option. */
	public const CACHE_VERSION_KEY = 'adam_bot_knowledge_cache_version';

	/**
	 * Returns the supported source registry.
	 *
	 * @return array<string, string>
	 */
	public function sources(): array {
		$sources = apply_filters(
			'adam_bot_knowledge_provider_registry',
			array(
				'faq'        => __( 'Perguntas frequentes', 'adam-bot' ),
				'page'       => __( 'Páginas WordPress selecionadas', 'adam-bot' ),
				'membership' => __( 'Informação de sócios', 'adam-bot' ),
				'event'      => __( 'Informação de eventos', 'adam-bot' ),
				'manual'     => __( 'Entradas manuais de conhecimento', 'adam-bot' ),
			)
		);
		$sources = is_array( $sources ) ? $sources : array();
		$clean   = array();

		foreach ( $sources as $key => $label ) {
			$key = sanitize_key( (string) $key );
			if ( '' !== $key && is_scalar( $label ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $label );
			}
		}

		return $clean;
	}

	/**
	 * Returns safe settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array_merge( $this->defaults(), $stored );
		$valid  = array_keys( $this->sources() );
		$known  = isset( $stored['known_sources'] ) && is_array( $stored['known_sources'] )
			? array_values( array_intersect( $valid, array_map( 'sanitize_key', $stored['known_sources'] ) ) )
			: array( 'faq', 'page', 'membership', 'event', 'manual' );
		$new_sources = array_values( array_diff( $valid, $known ) );

		$values['enabled_sources'] = isset( $values['enabled_sources'] ) && is_array( $values['enabled_sources'] )
			? array_values( array_unique( array_merge( array_intersect( $valid, array_map( 'sanitize_key', $values['enabled_sources'] ) ), $new_sources ) ) )
			: array();
		$values['known_sources'] = $valid;
		$values['page_ids'] = isset( $values['page_ids'] ) && is_array( $values['page_ids'] )
			? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $values['page_ids'] ) ) ) ), 0, 50 )
			: array();
		$values['debug_mode'] = ! empty( $values['debug_mode'] ) ? 1 : 0;

		return $values;
	}

	/**
	 * Returns default source settings.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'enabled_sources' => $this->sourceKeys(),
			'known_sources'   => $this->sourceKeys(),
			'page_ids'        => array(),
			'debug_mode'      => 0,
		);
	}

	/**
	 * Sanitizes the Knowledge settings form and invalidates cached searches.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$valid   = array_keys( $this->sources() );
		$enabled = isset( $input['enabled_sources'] ) && is_array( $input['enabled_sources'] )
			? array_values( array_intersect( $valid, array_map( 'sanitize_key', $input['enabled_sources'] ) ) )
			: array();
		$pages   = isset( $input['page_ids'] ) && is_array( $input['page_ids'] )
			? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $input['page_ids'] ) ) ) ), 0, 50 )
			: array();

		$this->bumpCacheVersion();

		return array(
			'enabled_sources' => $enabled,
			'known_sources'   => $valid,
			'page_ids'        => $pages,
			'debug_mode'      => ! empty( $input['debug_mode'] ) ? 1 : 0,
		);
	}

	/** @return bool */
	public function isSourceEnabled( string $source ): bool {
		$settings = $this->all();
		$source   = sanitize_key( $source );

		// Providers added through the runtime provider filter are enabled on first
		// registration, even before they add an optional settings label.
		if ( ! array_key_exists( $source, $this->sources() ) ) {
			return true;
		}

		return in_array( $source, $settings['enabled_sources'], true );
	}

	/** @return array<int, int> */
	public function getPageIds(): array {
		$settings = $this->all();

		return $settings['page_ids'];
	}

	public function isDebugMode(): bool {
		return ! empty( $this->all()['debug_mode'] );
	}

	/** @return int */
	public function getCacheVersion(): int {
		return max( 1, (int) get_option( self::CACHE_VERSION_KEY, 1 ) );
	}

	/**
	 * Invalidates search caches without having to enumerate transient keys.
	 *
	 * @return void
	 */
	public function bumpCacheVersion(): void {
		update_option( self::CACHE_VERSION_KEY, $this->getCacheVersion() + 1, false );
	}

	/** @return void */
	public function ensureDefaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $this->defaults(), '', 'no' );
		}

		if ( false === get_option( self::CACHE_VERSION_KEY, false ) ) {
			add_option( self::CACHE_VERSION_KEY, 1, '', 'no' );
		}
	}

	/** @return void */
	public static function activate(): void {
		if ( false === get_option( self::CACHE_VERSION_KEY, false ) ) {
			add_option( self::CACHE_VERSION_KEY, 1, '', 'no' );
		}
	}

	/** Returns source identifiers without evaluating any translated labels. */
	private function sourceKeys(): array {
		return array( 'faq', 'page', 'membership', 'event', 'manual' );
	}
}
