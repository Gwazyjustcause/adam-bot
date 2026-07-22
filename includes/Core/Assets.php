<?php
/**
 * Frontend asset management.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

use AdamBot\UX\ExperienceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues the public stylesheet and script.
 */
final class Assets {
	/** @var ExperienceSettings */
	private $experience_settings;

	/** @param ExperienceSettings $experience_settings Public experience settings. */
	public function __construct( ExperienceSettings $experience_settings ) {
		$this->experience_settings = $experience_settings;
	}

	/**
	 * Registers frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! $this->is_public_page() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers and enqueues the public assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_public_page() ) {
			return;
		}

		$style_path  = 'assets/css/adam-bot.css';
		$script_path = 'assets/js/adam-bot.js';

		wp_register_style(
			'adam-bot',
			ADAM_BOT_URL . $style_path,
			array(),
			$this->get_asset_version( $style_path )
		);

		wp_register_script(
			'adam-bot',
			ADAM_BOT_URL . $script_path,
			array(),
			$this->get_asset_version( $script_path ),
			true
		);

		wp_localize_script(
			'adam-bot',
			'adamBotSettings',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'adam-bot/v1/chat' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'quickActions' => $this->experience_settings->all()['quick_actions'],
				'strings'      => array(
					'error'             => __( 'Desculpe. Neste momento não consegui responder. Tente novamente daqui a alguns instantes.', 'adam-bot' ),
					'userLabel'         => __( 'Você:', 'adam-bot' ),
					'assistantLabel'    => __( 'ADAM BOT:', 'adam-bot' ),
					'typing'            => __( 'ADAM BOT está a escrever', 'adam-bot' ),
					'followUps'         => __( 'Também poderá estar à procura de:', 'adam-bot' ),
					'followUpsEn'       => 'You may also be looking for:',
					'browseTopics'      => __( 'Explorar temas', 'adam-bot' ),
					'browseTopicsEn'    => 'Browse Topics',
					'homeRestored'      => __( 'Ecrã inicial reposto.', 'adam-bot' ),
					'homeRestoredEn'    => 'Home screen restored.',
					'inputPlaceholder'  => __( 'Pergunte ao ADAM BOT…', 'adam-bot' ),
					'inputPlaceholderEn' => 'Ask ADAM BOT…',
					'inputLabel'        => __( 'Mensagem para o ADAM BOT', 'adam-bot' ),
					'inputLabelEn'      => 'Message for ADAM BOT',
					'sendLabel'         => __( 'Enviar mensagem', 'adam-bot' ),
					'sendLabelEn'       => 'Send message',
					'relatedPages'      => __( 'Páginas relacionadas', 'adam-bot' ),
					'events'            => __( 'Eventos', 'adam-bot' ),
					'results'           => __( 'Resultados', 'adam-bot' ),
					'view'              => __( 'Ver', 'adam-bot' ),
					'restored'          => __( 'Conversa desta sessão restaurada.', 'adam-bot' ),
					'debugSummary'      => __( 'Diagnóstico da pesquisa', 'adam-bot' ),
					'debugProvider'     => __( 'Fornecedor', 'adam-bot' ),
					'debugIntent'       => __( 'Intenção', 'adam-bot' ),
					'debugScore'        => __( 'Pontuação', 'adam-bot' ),
					'debugKeywords'     => __( 'Palavras correspondentes', 'adam-bot' ),
					'debugConfidence'   => __( 'Confiança', 'adam-bot' ),
					'debugTime'         => __( 'Tempo do fornecedor', 'adam-bot' ),
					'debugFallback'     => __( 'Fornecedor alternativo', 'adam-bot' ),
				),
			)
		);

		wp_enqueue_style( 'adam-bot' );
		wp_enqueue_script( 'adam-bot' );
	}

	/**
	 * Gets the cache-busting version for an asset.
	 *
	 * Development builds use the file modification time. Production builds use
	 * the stable plugin version.
	 *
	 * @param string $relative_path Asset path relative to the plugin directory.
	 * @return string
	 */
	private function get_asset_version( string $relative_path ): string {
		$is_development = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			|| ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
		$absolute_path  = ADAM_BOT_PATH . $relative_path;

		if ( $is_development && is_readable( $absolute_path ) ) {
			$modified_time = filemtime( $absolute_path );

			if ( false !== $modified_time ) {
				return (string) $modified_time;
			}
		}

		return ADAM_BOT_VERSION;
	}

	/**
	 * Determines whether frontend assets are allowed for the current request.
	 *
	 * @return bool
	 */
	private function is_public_page(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( function_exists( 'is_login' ) && is_login() ) {
			return false;
		}

		return 'wp-login.php' !== ( $GLOBALS['pagenow'] ?? '' );
	}
}
