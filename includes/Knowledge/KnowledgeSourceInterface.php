<?php
/**
 * Common knowledge-source contract.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Every knowledge source searches its own data behind this stable boundary.
 */
interface KnowledgeSourceInterface extends KnowledgeProviderInterface {
}
