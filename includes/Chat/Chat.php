<?php
/**
 * Chat component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Chat;

use AdamBot\Services\ChatService;

defined( 'ABSPATH' ) || exit;

/**
 * Reserves the chat component boundary for a future phase.
 */
final class Chat {
	/**
	 * Future chat service implementation.
	 *
	 * @var ChatService
	 */
	private $chat_service;

	/**
	 * Creates the chat component.
	 *
	 * @param ChatService $chat_service Chat service.
	 */
	public function __construct( ChatService $chat_service ) {
		$this->chat_service = $chat_service;
	}

	/**
	 * Registers chat hooks in future phases.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Chat hooks will be registered when chat behavior is implemented.
	}
}
