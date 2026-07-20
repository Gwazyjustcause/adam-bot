<?php
/**
 * Common AI provider contract.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Providers;

use AdamBot\AI\DTO\ChatRequest;
use AdamBot\AI\DTO\ChatResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Every provider adapter must implement this stable boundary.
 */
interface AIProviderInterface {
	/**
	 * Generates a response for a provider-neutral chat request.
	 *
	 * @param ChatRequest $request Prepared request.
	 * @return ChatResponse
	 */
	public function generateResponse( ChatRequest $request ): ChatResponse;
}
