<?php
/**
 * Phase 1 fake chat service.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies the local canned response used before an AI provider is connected.
 */
final class FakeResponseService {
	/**
	 * Returns the response displayed for every Phase 1 message.
	 *
	 * @return string
	 */
	public function get_response(): string {
		$response = __( "Obrigado pela sua pergunta.\n\nNesta primeira versão ainda não estou ligado ao motor de IA.\n\nEm breve poderei responder automaticamente.", 'adam-bot' );

		/**
		 * Filters the temporary Phase 1 response.
		 *
		 * @param string $response Temporary response text.
		 */
		return (string) apply_filters( 'adam_bot_fake_response', $response );
	}

	/**
	 * Returns the simulated response delay in milliseconds.
	 *
	 * @return int
	 */
	public function get_delay(): int {
		/**
		 * Filters the temporary response delay.
		 *
		 * @param int $delay Delay in milliseconds.
		 */
		$delay = (int) apply_filters( 'adam_bot_fake_response_delay', 1000 );

		return max( 0, $delay );
	}
}
