<?php
/**
 * Frontend widget renderer.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the chat shell in the public site footer.
 */
final class Widget {
	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_footer', array( $this, 'render' ), 100 );
	}

	/**
	 * Renders the widget template.
	 *
	 * @return void
	 */
	public function render(): void {
		$suggestions = array(
			__( 'O que é a ADAM?', 'adam-bot' ),
			__( 'Como me posso tornar sócio?', 'adam-bot' ),
			__( 'Próximos eventos', 'adam-bot' ),
			__( 'Quanto custa ser sócio?', 'adam-bot' ),
			__( 'Como renovar a quota?', 'adam-bot' ),
			__( 'Legislação do Airsoft', 'adam-bot' ),
		);

		/**
		 * Filters the questions shown in the Phase 1 empty state.
		 *
		 * @param string[] $suggestions Suggested questions.
		 */
		$suggestions = (array) apply_filters( 'adam_bot_suggested_questions', $suggestions );

		include ADAM_BOT_PATH . 'templates/chat-widget.php';
	}
}
