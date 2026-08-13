<?php
/**
 * Lazy-hydrated public guided assistant widget.
 *
 * @package AdamBot
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="adam-bot-root" class="adam-bot" data-adam-bot data-adam-guided>
	<button type="button" class="adam-bot__launcher" data-adam-launcher aria-label="<?php esc_attr_e( 'Abrir o assistente ADAM BOT', 'adam-bot' ); ?>" aria-controls="adam-bot-panel" aria-haspopup="dialog" aria-expanded="false">
		<span class="adam-bot__brand adam-bot__brand--launcher" aria-hidden="true"><span class="adam-bot__brand-mark">A</span><span class="adam-bot__brand-spark"></span></span>
		<span class="adam-bot__notification" aria-hidden="true"></span>
	</button>

	<template data-adam-template>
		<div class="adam-bot__backdrop" data-adam-backdrop aria-hidden="true"></div>
		<section id="adam-bot-panel" class="adam-bot__panel" data-adam-panel role="dialog" aria-modal="true" aria-labelledby="adam-bot-title" aria-describedby="adam-bot-subtitle" aria-hidden="true">
			<header class="adam-bot__header">
				<div class="adam-bot__brand adam-bot__brand--header" aria-hidden="true"><span class="adam-bot__brand-mark">A</span><span class="adam-bot__brand-spark"></span></div>
				<div class="adam-bot__heading"><div class="adam-bot__presence"><span class="adam-bot__online-dot" aria-hidden="true"></span><?php esc_html_e( 'Online', 'adam-bot' ); ?></div><h2 id="adam-bot-title"><span aria-hidden="true">🤖</span> ADAM BOT</h2><p id="adam-bot-subtitle"><?php esc_html_e( 'Assistente virtual da ADAM', 'adam-bot' ); ?></p></div>
				<div class="adam-bot__header-actions"><button type="button" class="adam-bot__header-home" data-guided-home aria-label="<?php esc_attr_e( 'Voltar ao início', 'adam-bot' ); ?>" title="<?php esc_attr_e( 'Início', 'adam-bot' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 11 8-7 8 7"></path><path d="M6.5 9.5V20h11V9.5M10 20v-6h4v6"></path></svg></button><button type="button" class="adam-bot__close" data-adam-close aria-label="<?php esc_attr_e( 'Fechar assistente', 'adam-bot' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"></path></svg></button></div>
			</header>
			<nav class="adam-bot__conversation-toolbar" data-guided-toolbar aria-label="<?php esc_attr_e( 'Navegação do assistente', 'adam-bot' ); ?>"><button type="button" data-guided-back disabled><span aria-hidden="true">←</span> <span><?php esc_html_e( 'Voltar', 'adam-bot' ); ?></span></button><button type="button" data-guided-home><span aria-hidden="true">⌂</span> <span><?php esc_html_e( 'Início', 'adam-bot' ); ?></span></button><button type="button" data-guided-reset><span aria-hidden="true">↻</span> <span><?php esc_html_e( 'Nova conversa', 'adam-bot' ); ?></span></button></nav>
			<div class="adam-bot__conversation" data-adam-conversation><div class="adam-bot__guided-stage" data-guided-stage role="region" aria-live="polite" aria-busy="false"></div></div>
			<div class="adam-bot__sr-only" data-guided-status role="status" aria-live="polite" aria-atomic="true"></div>
		</section>
	</template>
</div>
