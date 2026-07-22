=== ADAM BOT ===
Contributors: adam
Tags: chat, assistant, knowledge
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A polished, accessible chat interface for the ADAM virtual assistant.

== Description ==

ADAM BOT provides a responsive public chat interface connected to a deterministic
ADAM Knowledge Response Engine. It searches every enabled knowledge provider,
ranks normalized results, and formats the best official content as conversational
text, navigation buttons, lists, links, or event cards.

The Phase 6 interface remains unchanged: administrator-configurable quick actions,
safe rich Markdown, contextual follow-ups, trusted website navigation, temporary
conversation recovery, mobile optimization, and accessible keyboard behavior.
Phase 7 removes external AI services, API keys, model settings, and internet
connectivity from the response path. Phase 8 turns that engine into a complete
WordPress-native content management system. Administrators control questions,
answers, structured response blocks, search terms, synonyms, weighting,
navigation, categories, related questions, visibility, ordering, and revisions.
Anonymous Search Analytics stores no visitor identifiers or transcripts.

Phase 9 adds intent-aware live providers for events, community teams, associated
fields, partners, news, documents, and public membership information. Providers
are registered lazily and return reusable rich cards. Static knowledge remains a
ranked fallback, so administrators keep full control while live platform data is
preferred whenever it is relevant.

Phase 10 completes the ADAM Assistant 1.0 production milestone with a health
dashboard, analytical charts and filters, an unanswered-question workflow,
search-improvement suggestions, provider inspection, full backups, scheduled
maintenance, administrator notices, object caching, saved searches, bulk and
keyboard ordering, diagnostic search output for administrators, and a complete
accessibility and mobile pass. The public and administrative interface is in
European Portuguese.

Version 1.10 stores Knowledge and FAQ as two editable types in one Knowledge Base.
Every generated record is visible and carries its language, source and sync state.
Website changes create an out-of-date proposal that administrators can compare,
apply or keep without overwriting their editorial work.

== Installation ==

1. Copy the `adam-bot` directory into `/wp-content/plugins/`.
2. Activate **ADAM BOT** from the WordPress Plugins screen.
3. Open **ADAM BOT -> Knowledge Base** to create and organize answers.
4. Choose **Type: FAQ** in the same editor for frequently asked questions.
5. Open **ADAM BOT -> Settings** to enable providers, select website pages,
   import/export content, and customize the quick-action cards.
6. Open any public page and use the ADAM BOT launcher in the bottom-left corner.

== Knowledge Administration ==

Knowledge and FAQ records share one editor, database, list and search system. They
support unlimited coloured/icon categories,
published/draft/hidden states, keywords, synonyms, editorial priority, search
weight, related pages, administrator-authored buttons, and related knowledge.
The no-code Rich Response Builder supports paragraphs, headings, bullet and
numbered lists, buttons, links, warnings, information boxes, and success boxes.

Each editor includes a Search Preview with matched keywords, confidence, provider,
and rendered response, plus duplicate warnings. WordPress revisions include the
knowledge metadata so administrators can compare and restore old versions.
JSON and CSV import/export includes response blocks, relationships, ordering,
statuses, and category appearance metadata.

Search Analytics reports common questions, unanswered questions, low-confidence
searches, average confidence, average response time, and most-viewed entries.
It also reports the detected intent, selected provider, provider duration, result
count, confidence, and fallback path for recent anonymous searches.
All records are anonymous aggregates; the Conversations screen intentionally does
not retain transcripts or visitor identifiers.

== Knowledge Integrations ==

Ecosystem plugins implement
`AdamBot\Knowledge\Dynamic\DynamicProviderInterface` and can register at any
time after ADAM BOT loads:

`adam_bot()->providers()->register( new CommunityProvider() );`

Plugins that initialize earlier can register on the
`adam_bot_register_dynamic_providers` action. The interface declares provider
availability, supported intents, search, follow-up suggestions, priority, and
cache lifetime. The registry discovers providers without ADAM BOT referring to
the owning plugin, and newly registered providers receive a Settings toggle.

The built-in filter adapters accept public records from:
`adam_bot_dynamic_events`, `adam_bot_dynamic_teams`,
`adam_bot_dynamic_fields`, `adam_bot_dynamic_partners`,
`adam_bot_dynamic_news`, `adam_bot_dynamic_documents`, and
`adam_bot_dynamic_membership`. Each filter receives the empty result list, query,
intent, and provider. Records may include title/name, content/description,
keywords, synonyms, URL, image, priority, and type-specific public fields. Set
`public` to false to exclude a record, or `matched` to true when the owning
plugin has already performed non-lexical matching. Membership records must
contain public information only; the adapter never requests or renders member
accounts.

The older `adam_bot_knowledge_membership_items` and
`adam_bot_knowledge_event_items` filters remain supported by their live adapters.
External event post types are opt-in through
`adam_bot_knowledge_event_post_types`. Their owning plugin can enrich each base
post record through `adam_bot_knowledge_event_post_item`; ADAM BOT contains no
plugin-specific post-type or metadata assumptions. Trigger
`adam_bot_knowledge_invalidate_cache` when owning data changes.

== Privacy and Connectivity ==

Chat answers make no external HTTP requests and require no API key, model, or AI
provider. During website indexing only, public Portuguese source text may be sent
to the MyMemory translation endpoint to create the stored English variant. This
can be replaced with `adam_bot_site_index_translation` or disabled with
`adam_bot_site_index_remote_translation`. Conversation messages are never sent
to that service and are held only in browser
session storage for recovery and are cleared when the browsing session ends.
Server analytics contain aggregate counters and scrubbed common-question samples.

== Changelog ==

= 1.11.0 =
* Added permanent Home and New Conversation controls that reset messages and topic without closing the assistant or reloading the page.
* Expanded the welcome screen with suggested questions, website topics, search guidance, and direct conversational navigation.
* Added three to six clickable follow-up questions after every response, prioritizing related Knowledge and using configured navigation actions as fallbacks.
* Added an always-available bilingual Browse Topics section for association, membership, events, teams, fields, partners, and contacts.
* Preserved active topics between turns while preventing delayed responses from reappearing after a conversation reset.
* Improved mobile touch targets, safe-area spacing, focus restoration, screen-reader labels, high contrast, and English/Portuguese navigation copy.

= 1.10.0 =
* Unified Knowledge and FAQ into one canonical, fully editable Knowledge Base with a record type field and lossless legacy FAQ migration.
* Added editable language and source metadata plus source-page, last-indexed, last-synced, and sync-state information.
* Added Synced, Modified, and Out of date workflows with side-by-side comparisons and explicit update or keep-current actions.
* Prevented website reindexing from overwriting administrator edits or deleting records whose source sections changed or disappeared.
* Added unified source, language, type, sync state, category, priority, and editing filters and operational notices.
* Extended JSON/CSV backup and WordPress revisions to preserve unified records and synchronization metadata.
* Removed direct selected-page searching so all static website answers now come from normal, administrator-editable Knowledge records.

= 1.9.0 =
* Added one-time automatic indexing of all published public WordPress pages and news into editable Knowledge Base and FAQ entries.
* Added structured section extraction, natural questions, keywords, categories, source pages, and contextual navigation buttons without placeholder content.
* Added persisted Portuguese/English variants, background translation, language-aware ranking, and bilingual fallback messages.
* Added provenance metadata, explicit administrator rebuild controls, stale-entry handling, revision/export support, and protected manual entries from automatic changes.

= 1.8.1 =
* Deferred text-domain loading and all translated service initialization until the WordPress `init` hook.
* Prevented activation callbacks, provider registration, and provider constructors from triggering just-in-time translation loading.
* Added a regression test that fails when the `adam-bot` domain is evaluated before `init`.

= 1.8.0 =
* Completed the ADAM Assistant 1.0 production milestone.
* Added the assistant health dashboard, filtered analytics charts, search trends, popular categories, keyword and provider usage.
* Added an unanswered-question queue with one-click draft creation and repeated-question consolidation suggestions.
* Added the Provider Inspector with availability, priority, indexed-item, timing, update, and error diagnostics.
* Added administrator-only search debug output, provider health monitoring, and operational dashboard notices.
* Added persistent object-cache support, provider/search cache layers, lazy loading, namespace invalidation, and daily background maintenance.
* Added complete JSON/CSV backups for knowledge, FAQ, analytics, anonymous search logs, settings, and provider health.
* Added saved admin searches, entry duplication, bulk state actions, and mouse/keyboard ordering.
* Improved mobile safe areas, scroll and keyboard behaviour, 44-pixel touch targets, high contrast, reduced motion, focus handling, and ARIA labelling.
* Standardized all user-facing interface text in European Portuguese and added extension documentation in `docs/DEVELOPER.md`.

= 1.7.0 =
* Added DynamicProviderInterface, DynamicProviderRegistry, ProviderResolver, and the stable `adam_bot()->providers()` registration API.
* Added lazy, filter-backed live providers for events, teams, associated fields, partners, news, documents, and public membership information.
* Added lightweight English/Portuguese intent detection and provider priorities with relevant static-knowledge fallback.
* Added reusable Event, Team, Field, Partner, News, and Document Cards, button groups, information boxes, and warning boxes.
* Added multi-result card rendering with images, metadata, primary/secondary actions, and downloadable document links.
* Added provider and response caching without querying unrelated dynamic providers.
* Added anonymous intent, provider, duration, result-count, confidence, and fallback diagnostics to Search Analytics.
* Removed core assumptions about third-party plugin classes and event post types.

= 1.6.0 =
* Added the ADAM BOT Dashboard, Conversations, Knowledge Base, FAQ, Search Analytics, and Settings navigation.
* Added complete Knowledge Base and FAQ CRUD fields, statuses, list columns, bulk actions, ordering, and search.
* Added unlimited hierarchical categories with colours and icons.
* Added keywords, synonyms, priority, search weight, navigation buttons, related pages, and related knowledge.
* Added a no-code rich response builder with paragraph, heading, list, button, link, warning, information, and success blocks.
* Added in-editor search previews with confidence, matched terms, provider, and response output.
* Added deterministic duplicate detection and warnings.
* Added anonymous missing-answer, low-confidence, timing, confidence, common-query, and entry-view analytics.
* Added JSON/CSV import and export with two-pass relationship migration and category appearance metadata.
* Added WordPress revision comparison and restore support for Knowledge metadata.
* Removed hardcoded topic follow-ups and inferred navigation labels; providers now own this content.
* Kept the provider interface open so future ADAM plugins are searched without core changes.

= 1.5.0 =
* Replaced the external AI pipeline with SearchService, ResultRanker, and ResponseFormatter.
* Added centralized weighted ranking for title, keyword, synonym, category, priority, term coverage, and session topic.
* Added high, medium, low, and no-confidence response behavior without exposing confidence to visitors.
* Added deterministic rich responses, smart navigation buttons, related questions, and event cards.
* Replaced transcript context with a temporary topic and recently shown result IDs.
* Added response caching and structured search diagnostics for provider, keywords, confidence, and timing.
* Removed OpenAI code, API-key settings, model controls, provider selection, and general-AI consent.
* Added confidence-level analytics while retaining privacy-friendly aggregate reporting.

= 1.4.0 =
* Added configurable quick-action cards and contextual follow-up suggestions.
* Added safe Markdown rendering, trusted page buttons, and smart raw-link labels.
* Added temporary session context, conversation recovery, and a first-visit welcome.
* Added explicit consent before non-official general-knowledge answers.
* Added internal official/general/mixed classification and privacy-friendly aggregate analytics.
* Added lazy chat hydration, request debouncing, session caching, mobile keyboard handling, and expanded accessibility.

= 1.3.0 =
* Added the Phase 5 WordPress-native knowledge engine with confidence scoring.
* Added FAQ, selected-page, membership, event, and manual-entry knowledge sources.
* Added weighted keyword matching, bounded relevant context, attribution guidance, and cached searches.
* Added FAQ/manual managers and source/page controls under ADAM BOT -> Knowledge.

= 1.2.0 =
* Added the provider-neutral Phase 4 AI infrastructure and OpenAI provider.
* Added AI settings, an editable database-backed system prompt, and restore-default control.
* Added prompt validation, friendly provider errors, redacted metadata logging, and IP rate limiting.

= 1.1.0 =
* Added the responsive Phase 3 chat interface and animated launcher.
* Added suggested questions, accessible conversation controls, and typing states.
* Connected the composer to the existing chat REST endpoint.
* Added dark mode, reduced-motion support, and frontend-only loading guards.

= 1.0.0 =
* Added the modular Phase 2 plugin architecture.
* Added the REST API readiness endpoint and frontend mount point.
* Added development-aware assets and a lightweight logger.
