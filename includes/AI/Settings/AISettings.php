<?php
/**
 * AI settings repository.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Owns persisted AI configuration and its server-side allowlists.
 */
final class AISettings {
	/** WordPress option name. */
	public const OPTION_KEY = 'adam_bot_ai_settings';

	/** Only provider currently available. */
	public const DEFAULT_PROVIDER = 'openai';

	/** Balanced default model for the public assistant. */
	public const DEFAULT_MODEL = 'gpt-5.6-terra';

	/** Maximum user-message length accepted by the public API. */
	public const MAX_PROMPT_CHARACTERS = 4000;

	/** Maximum editable system-prompt length. */
	public const MAX_SYSTEM_PROMPT_CHARACTERS = 12000;

	/** Default system prompt persisted on installation. */
	public const DEFAULT_SYSTEM_PROMPT = "You are ADAM BOT, the official virtual assistant of ADAM – Associação Desportiva de Airsoft do Mondego.\n\nBe polite.\n\nReply in European Portuguese unless the user writes in another language.\n\nDo not invent information.\n\nIf you do not know something, say so.\n\nDo not provide legal, medical or financial advice.\n\nKeep responses concise unless the user asks for detail.";

	/**
	 * Returns the complete configuration with safe defaults applied.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_merge( $this->defaults(), $stored );
		$models   = $this->models();

		if ( ! array_key_exists( (string) $settings['model'], $models ) ) {
			$settings['model'] = array_key_exists( self::DEFAULT_MODEL, $models )
				? self::DEFAULT_MODEL
				: (string) key( $models );
		}

		if ( self::DEFAULT_PROVIDER !== $settings['provider'] ) {
			$settings['provider'] = self::DEFAULT_PROVIDER;
		}

		$settings['temperature'] = $this->clampFloat( $settings['temperature'], 0.0, 2.0, 0.3 );
		$settings['max_tokens']  = $this->clampInt( $settings['max_tokens'], 1, 4000, 500 );
		$settings['timeout']     = $this->clampInt( $settings['timeout'], 1, 60, 20 );

		return $settings;
	}

	/**
	 * Returns default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'provider'      => self::DEFAULT_PROVIDER,
			'openai_api_key' => '',
			'model'         => self::DEFAULT_MODEL,
			'temperature'   => 0.3,
			'max_tokens'    => 500,
			'timeout'       => 20,
			'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
		);
	}

	/**
	 * Returns the server-controlled OpenAI model registry.
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		$models = array(
			'gpt-5.6-terra' => __( 'GPT-5.6 Terra (balanced)', 'adam-bot' ),
			'gpt-5.6-sol'   => __( 'GPT-5.6 Sol (highest quality)', 'adam-bot' ),
			'gpt-5.6-luna'  => __( 'GPT-5.6 Luna (cost efficient)', 'adam-bot' ),
		);

		/**
		 * Filters the server-side OpenAI model allowlist.
		 *
		 * @param array<string, string> $models Model IDs mapped to admin labels.
		 */
		$filtered = apply_filters( 'adam_bot_openai_models', $models );

		if ( ! is_array( $filtered ) || empty( $filtered ) ) {
			return $models;
		}

		$validated = array();

		foreach ( $filtered as $model => $label ) {
			if ( is_string( $model ) && preg_match( '/^[a-zA-Z0-9._-]+$/', $model ) && is_string( $label ) ) {
				$validated[ $model ] = $label;
			}
		}

		return empty( $validated ) ? $models : $validated;
	}

	/**
	 * Sanitizes the settings form without ever returning a secret to the page.
	 *
	 * A blank API-key field preserves an already configured key.
	 *
	 * @param mixed $input Submitted option value.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$current = $this->all();
		$input   = is_array( $input ) ? $input : array();
		$output  = $current;
		$models  = $this->models();

		$output['provider'] = self::DEFAULT_PROVIDER;

		$model = isset( $input['model'] ) ? sanitize_text_field( (string) $input['model'] ) : '';
		if ( array_key_exists( $model, $models ) ) {
			$output['model'] = $model;
		} else {
			add_settings_error(
				self::OPTION_KEY,
				'adam_bot_invalid_model',
				__( 'The selected model is not available.', 'adam-bot' )
			);
		}

		$output['temperature'] = $this->clampFloat( $input['temperature'] ?? null, 0.0, 2.0, 0.3 );
		$output['max_tokens']  = $this->clampInt( $input['max_tokens'] ?? null, 1, 4000, 500 );
		$output['timeout']     = $this->clampInt( $current['timeout'] ?? null, 1, 60, 20 );

		$submitted_key = isset( $input['openai_api_key'] )
			? trim( sanitize_text_field( (string) $input['openai_api_key'] ) )
			: '';

		if ( '' !== $submitted_key ) {
			if ( $this->isValidApiKey( $submitted_key ) ) {
				$output['openai_api_key'] = $submitted_key;
			} else {
				add_settings_error(
					self::OPTION_KEY,
					'adam_bot_invalid_api_key',
					__( 'The OpenAI API key format is invalid. The previous key was kept.', 'adam-bot' )
				);
			}
		}

		if ( isset( $_POST['adam_bot_restore_prompt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the settings nonce.
			$output['system_prompt'] = self::DEFAULT_SYSTEM_PROMPT;
			add_settings_error(
				self::OPTION_KEY,
				'adam_bot_prompt_restored',
				__( 'The default system prompt was restored.', 'adam-bot' ),
				'updated'
			);

			return $output;
		}

		$system_prompt = isset( $input['system_prompt'] )
			? trim( sanitize_textarea_field( (string) $input['system_prompt'] ) )
			: '';

		if ( '' === $system_prompt ) {
			$output['system_prompt'] = self::DEFAULT_SYSTEM_PROMPT;
			add_settings_error(
				self::OPTION_KEY,
				'adam_bot_empty_system_prompt',
				__( 'The system prompt cannot be empty. The default prompt was restored.', 'adam-bot' )
			);
		} else {
			$output['system_prompt'] = $this->truncate( $system_prompt, self::MAX_SYSTEM_PROMPT_CHARACTERS );
		}

		return $output;
	}

	/**
	 * Ensures the defaults, including the system prompt, exist in the database.
	 *
	 * @return void
	 */
	public function ensureDefaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $this->defaults(), '', 'no' );
		}
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		( new self() )->ensureDefaults();
	}

	/**
	 * Validates an OpenAI secret key without making a network request.
	 *
	 * @param string $api_key Candidate key.
	 * @return bool
	 */
	public function isValidApiKey( string $api_key ): bool {
		$length = strlen( $api_key );

		return $length >= 20
			&& $length <= 255
			&& 1 === preg_match( '/^sk-[A-Za-z0-9_-]+$/', $api_key );
	}

	/**
	 * Returns a string truncated by characters when multibyte support is present.
	 *
	 * @param string $value Value to truncate.
	 * @param int    $maximum Maximum characters.
	 * @return string
	 */
	private function truncate( string $value, int $maximum ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $maximum );
		}

		return substr( $value, 0, $maximum );
	}

	/**
	 * Clamps a number to a float range.
	 *
	 * @param mixed $value Value to normalize.
	 * @param float $minimum Minimum value.
	 * @param float $maximum Maximum value.
	 * @param float $fallback Fallback value.
	 * @return float
	 */
	private function clampFloat( $value, float $minimum, float $maximum, float $fallback ): float {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $minimum, min( $maximum, (float) $value ) );
	}

	/**
	 * Clamps a number to an integer range.
	 *
	 * @param mixed $value Value to normalize.
	 * @param int   $minimum Minimum value.
	 * @param int   $maximum Maximum value.
	 * @param int   $fallback Fallback value.
	 * @return int
	 */
	private function clampInt( $value, int $minimum, int $maximum, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $minimum, min( $maximum, (int) $value ) );
	}
}
