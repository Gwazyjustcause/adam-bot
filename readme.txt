=== ADAM BOT ===
Contributors: adam
Tags: chat, assistant, api
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular foundation for the ADAM virtual assistant.

== Description ==

ADAM BOT Phase 2 provides the internal plugin architecture, frontend asset
registration, a public mount point, and a versioned REST API readiness endpoint.
It does not include a chatbot interface or any AI functionality.

== Installation ==

1. Copy the `adam-bot` directory into `/wp-content/plugins/`.
2. Activate **ADAM BOT** from the WordPress Plugins screen.
3. The plugin is now ready for future UI and service integrations.

== Changelog ==

= 1.0.0 =
* Added the modular Phase 2 plugin architecture.
* Added the REST API readiness endpoint and frontend mount point.
* Added development-aware assets and a lightweight logger.
