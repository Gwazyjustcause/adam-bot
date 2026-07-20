<?php
/**
 * Chat service.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Services;

use AdamBot\Helpers\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Placeholder for future chat orchestration.
 */
final class ChatService {
	/**
	 * Plugin logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Creates the chat service.
	 *
	 * @param Logger $logger Plugin logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}
}
