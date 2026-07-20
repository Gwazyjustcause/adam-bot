=== ADAM BOT ===
Contributors: adam
Tags: chat, assistant, knowledge
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.5.0
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
connectivity from the response path. Only a lightweight topic and recently shown
result IDs are used as current-session search context. Aggregate analytics store
no visitor identifiers or conversation transcripts.

== Installation ==

1. Copy the `adam-bot` directory into `/wp-content/plugins/`.
2. Activate **ADAM BOT** from the WordPress Plugins screen.
3. Open **ADAM BOT -> Knowledge** to enable sources and select website pages.
4. Add FAQs or manual knowledge entries as needed.
5. Open **ADAM BOT -> Settings** to customize the quick-action cards.
6. Open any public page and use the ADAM BOT launcher in the bottom-left corner.

== Knowledge Integrations ==

Existing ADAM components can contribute authoritative structured records through
the `adam_bot_knowledge_membership_items` and `adam_bot_knowledge_event_items`
filters. Event plugins can also expose their post types through
`adam_bot_knowledge_event_post_types`. Trigger the
`adam_bot_knowledge_invalidate_cache` action after external source data changes.

Additional providers implement `AdamBot\Knowledge\KnowledgeProviderInterface`,
register through `adam_bot_knowledge_providers`, and add their key and label to
`adam_bot_knowledge_provider_registry`. Provider results use the normalized
`KnowledgeResult` contract, keeping the REST and frontend contracts unchanged.

== Privacy and Connectivity ==

Chat answers make no external HTTP requests and require no API key, model, AI
provider, or internet connection. Conversation messages are held only in browser
session storage for recovery and are cleared when the browsing session ends.
Server analytics contain aggregate counters and scrubbed common-question samples.

== Changelog ==

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
