=== ADAM BOT ===
Contributors: adam
Tags: chat, assistant, api
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A polished, accessible chat interface for the ADAM virtual assistant.

== Description ==

ADAM BOT provides a responsive public chat interface connected to the versioned
ADAM REST endpoint. The frontend remains independent of the configured AI
provider. OpenAI is supported through a provider-neutral service layer, and a
lightweight knowledge engine supplies relevant information from enabled ADAM
sources before each response.

== Installation ==

1. Copy the `adam-bot` directory into `/wp-content/plugins/`.
2. Activate **ADAM BOT** from the WordPress Plugins screen.
3. Open **ADAM BOT -> Settings** and configure an OpenAI API key.
4. Open **ADAM BOT -> Knowledge** to enable sources and select website pages.
5. Add FAQs or manual knowledge entries as needed.
6. Open any public page and use the ADAM BOT launcher in the bottom-left corner.

== Knowledge Integrations ==

Existing ADAM components can contribute authoritative structured records through
the `adam_bot_knowledge_membership_items` and `adam_bot_knowledge_event_items`
filters. Event plugins can also expose their post types through
`adam_bot_knowledge_event_post_types`. Trigger the
`adam_bot_knowledge_invalidate_cache` action after external source data changes.

== Changelog ==

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
