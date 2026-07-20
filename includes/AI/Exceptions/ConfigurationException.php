<?php
/**
 * AI configuration exception.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Exceptions;

defined( 'ABSPATH' ) || exit;

/** Raised when server-side AI configuration is invalid. */
final class ConfigurationException extends AIException {
}
