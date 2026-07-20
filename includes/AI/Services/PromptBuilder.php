<?php
/**
 * Trusted system-prompt builder.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Services;

use AdamBot\AI\DTO\ChatRequest;
use AdamBot\AI\Settings\AISettings;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures raw user input is always paired with a server-controlled prompt.
 */
final class PromptBuilder {
	/** @var AISettings */
	private $settings;

	/**
	 * Creates the builder.
	 *
	 * @param AISettings $settings Settings repository.
	 */
	public function __construct( AISettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Attaches the current database-backed system prompt.
	 *
	 * @param ChatRequest $request Raw request DTO.
	 * @return ChatRequest
	 */
	public function build( ChatRequest $request ): ChatRequest {
		$settings = $this->settings->all();

		return $request->withSystemPrompt( (string) $settings['system_prompt'] );
	}
}
