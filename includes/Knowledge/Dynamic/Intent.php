<?php
/**
 * Dynamic search intent identifiers.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Dynamic;

defined( 'ABSPATH' ) || exit;

/** Stable intent keys shared by the resolver and third-party providers. */
final class Intent {
	public const EVENTS = 'search_events';
	public const TEAMS = 'search_teams';
	public const FIELDS = 'search_fields';
	public const PARTNERS = 'search_partners';
	public const NEWS = 'search_news';
	public const DOCUMENTS = 'search_documents';
	public const MEMBERSHIP = 'membership_questions';
	public const WEBSITE = 'website_navigation';
	public const KNOWLEDGE = 'knowledge_question';
}
