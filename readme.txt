=== ADAM BOT ===
Contributors: adam
Tags: chat, assistant, knowledge
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.6.0
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

== Installation ==

1. Copy the `adam-bot` directory into `/wp-content/plugins/`.
2. Activate **ADAM BOT** from the WordPress Plugins screen.
3. Open **ADAM BOT -> Knowledge Base** to create and organize answers.
4. Use **ADAM BOT -> FAQ** for frequently asked questions.
5. Open **ADAM BOT -> Settings** to enable providers, select website pages,
   import/export content, and customize the quick-action cards.
6. Open any public page and use the ADAM BOT launcher in the bottom-left corner.

== Knowledge Administration ==

Knowledge Base and FAQ entries support unlimited coloured/icon categories,
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
All records are anonymous aggregates; the Conversations screen intentionally does
not retain transcripts or visitor identifiers.

== Knowledge Integrations ==

Existing ADAM components can contribute authoritative structured records through
the `adam_bot_knowledge_membership_items` and `adam_bot_knowledge_event_items`
filters. Event plugins can also expose their post types through
`adam_bot_knowledge_event_post_types`. Trigger the
`adam_bot_knowledge_invalidate_cache` action after external source data changes.

Additional providers implement `AdamBot\Knowledge\KnowledgeProviderInterface`,
register through `adam_bot_knowledge_providers`, and are searched immediately.
They may add their key and label to `adam_bot_knowledge_provider_registry` to
expose an enable/disable control in Settings. Newly registered providers are
enabled by default. Provider results use the normalized `KnowledgeResult`
contract, including optional keywords, synonyms, search weight, button label,
response blocks, related questions, and object ID, while keeping the REST and
frontend contracts stable.

== Privacy and Connectivity ==

Chat answers make no external HTTP requests and require no API key, model, AI
provider, or internet connection. Conversation messages are held only in browser
session storage for recovery and are cleared when the browsing session ends.
Server analytics contain aggregate counters and scrubbed common-question samples.

== Changelog ==

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
