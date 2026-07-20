=== ADAM BOT ===
Contributors: adam
Tags: chat, assistant, api
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A polished, accessible chat interface for the ADAM virtual assistant.

== Description ==

ADAM BOT provides a responsive public chat interface connected to the versioned
ADAM REST endpoint. The frontend remains independent of the AI provider and does
not include an AI integration.

== Installation ==

1. Copy the `adam-bot` directory into `/wp-content/plugins/`.
2. Activate **ADAM BOT** from the WordPress Plugins screen.
3. Open any public page and use the ADAM BOT launcher in the bottom-left corner.

== Changelog ==

= 1.1.0 =
* Added the responsive Phase 3 chat interface and animated launcher.
* Added suggested questions, accessible conversation controls, and typing states.
* Connected the composer to the existing chat REST endpoint.
* Added dark mode, reduced-motion support, and frontend-only loading guards.

= 1.0.0 =
* Added the modular Phase 2 plugin architecture.
* Added the REST API readiness endpoint and frontend mount point.
* Added development-aware assets and a lightweight logger.
