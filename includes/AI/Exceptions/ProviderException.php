<?php
/**
 * AI provider exception.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Exceptions;

defined( 'ABSPATH' ) || exit;

/** Raised when a configured AI provider cannot produce a response. */
final class ProviderException extends AIException {
}
