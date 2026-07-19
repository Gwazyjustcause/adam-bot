<?php
/**
 * Frontend asset loader.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Frontend;

use AdamBot\Chat\FakeResponseService;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the widget's only stylesheet and script.
 */
final class Assets {
	/**
	 * Fake response provider.
	 *
	 * @var FakeResponseService
	 */
	private $fake_response_service;

	/**
	 * Creates the asset module.
	 *
	 * @param FakeResponseService $fake_response_service Phase 1 response provider.
	 */
	public function __construct( FakeResponseService $fake_response_service ) {
		$this->fake_response_service = $fake_response_service;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Loads the small, dependency-free frontend bundle.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		wp_enqueue_style(
			'adam-bot-widget',
			ADAM_BOT_URL . 'assets/css/widget.css',
			array(),
			ADAM_BOT_VERSION
		);

		wp_enqueue_script(
			'adam-bot-widget',
			ADAM_BOT_URL . 'assets/js/widget.js',
			array(),
			ADAM_BOT_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_add_inline_script(
			'adam-bot-widget',
			'window.AdamBotConfig = ' . wp_json_encode( $this->get_script_config() ) . ';',
			'before'
		);
	}

	/**
	 * Builds safe configuration for the browser-side fake service.
	 *
	 * @return array<string, mixed>
	 */
	private function get_script_config(): array {
		return array(
			'fakeResponse' => array(
				'message' => $this->fake_response_service->get_response(),
				'delay'   => $this->fake_response_service->get_delay(),
			),
			'strings'      => array(
				'userLabel'      => __( 'A sua mensagem', 'adam-bot' ),
				'assistantLabel' => __( 'Resposta do ADAM BOT', 'adam-bot' ),
				'typingLabel'    => __( 'O ADAM BOT está a escrever', 'adam-bot' ),
			),
		);
	}
}
