<?php
/**
 * Public assistant experience settings.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\UX;

defined( 'ABSPATH' ) || exit;

/**
 * Owns administrator-configurable quick actions shown when chat opens.
 */
final class ExperienceSettings {
	/** WordPress option name. */
	public const OPTION_KEY = 'adam_bot_experience_settings';

	/** Maximum number of public quick actions. */
	private const MAX_ACTIONS = 8;

	/**
	 * Returns the configured experience with safe defaults applied.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array_merge( $this->defaults(), $stored );

		$values['quick_actions'] = $this->sanitizeActions( $values['quick_actions'] ?? array() );

		if ( empty( $values['quick_actions'] ) ) {
			$values['quick_actions'] = $this->defaults()['quick_actions'];
		}

		return $values;
	}

	/**
	 * Returns the initial public actions.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'quick_actions' => array(
				array( 'icon' => '💬', 'label' => __( 'O que é a ADAM?', 'adam-bot' ), 'prompt' => __( 'O que é a ADAM?', 'adam-bot' ) ),
				array( 'icon' => '👤', 'label' => __( 'Tornar-me sócio', 'adam-bot' ), 'prompt' => __( 'Como me posso tornar sócio?', 'adam-bot' ) ),
				array( 'icon' => '📅', 'label' => __( 'Próximos eventos', 'adam-bot' ), 'prompt' => __( 'Quais são os próximos eventos?', 'adam-bot' ) ),
				array( 'icon' => '🎯', 'label' => __( 'Regras de Airsoft', 'adam-bot' ), 'prompt' => __( 'Quais são as principais regras de Airsoft?', 'adam-bot' ) ),
				array( 'icon' => '💳', 'label' => __( 'Tipos de sócio', 'adam-bot' ), 'prompt' => __( 'Quais são os tipos de sócio e respetivas quotas?', 'adam-bot' ) ),
				array( 'icon' => '📞', 'label' => __( 'Contactar', 'adam-bot' ), 'prompt' => __( 'Como posso contactar a ADAM?', 'adam-bot' ) ),
			),
		);
	}

	/**
	 * Sanitizes the experience settings form.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$actions = $this->sanitizeActions( $input['quick_actions'] ?? array() );

		return array(
			'quick_actions' => empty( $actions ) ? $this->defaults()['quick_actions'] : $actions,
		);
	}

	/** @return void */
	public function ensureDefaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $this->defaults(), '', 'no' );
		}
	}

	/** @return void */
	public static function activate(): void {
		// Defaults are created during init, after the text domain is available.
	}

	/**
	 * Normalizes quick-action rows and removes empty entries.
	 *
	 * @param mixed $actions Candidate rows.
	 * @return array<int, array<string, string>>
	 */
	private function sanitizeActions( $actions ): array {
		if ( ! is_array( $actions ) ) {
			return array();
		}

		$clean = array();

		foreach ( array_slice( $actions, 0, self::MAX_ACTIONS ) as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}

			$icon   = $this->truncate( sanitize_text_field( (string) ( $action['icon'] ?? '' ) ), 8 );
			$label  = $this->truncate( sanitize_text_field( (string) ( $action['label'] ?? '' ) ), 60 );
			$prompt = $this->truncate( sanitize_textarea_field( (string) ( $action['prompt'] ?? '' ) ), 240 );

			if ( '' === $label || '' === $prompt ) {
				continue;
			}

			$clean[] = compact( 'icon', 'label', 'prompt' );
		}

		return $clean;
	}

	/** @return string */
	private function truncate( string $value, int $maximum ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum ) : substr( $value, 0, $maximum );
	}
}
