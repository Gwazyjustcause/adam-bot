<?php
/**
 * Public chat widget template.
 *
 * @package AdamBot
 *
 * @var string[] $suggestions Suggested questions.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="adam-bot" data-adam-bot-root>
	<div class="adam-bot__backdrop" data-adam-bot-backdrop aria-hidden="true"></div>

	<button
		class="adam-bot__launcher"
		type="button"
		data-adam-bot-launcher
		aria-controls="adam-bot-panel"
		aria-expanded="false"
		aria-label="<?php esc_attr_e( 'Abrir o ADAM BOT', 'adam-bot' ); ?>"
	>
		<svg class="adam-bot__launcher-icon" aria-hidden="true" viewBox="0 0 24 24" width="28" height="28" focusable="false">
			<path d="M5 5.75h14A2.25 2.25 0 0 1 21.25 8v7A2.25 2.25 0 0 1 19 17.25h-7.07l-4.55 3.1a.75.75 0 0 1-1.17-.62v-2.48H5A2.25 2.25 0 0 1 2.75 15V8A2.25 2.25 0 0 1 5 5.75Z" fill="currentColor"/>
			<circle cx="8" cy="11.5" r="1.05" fill="var(--adam-bot-launcher-dot)"/>
			<circle cx="12" cy="11.5" r="1.05" fill="var(--adam-bot-launcher-dot)"/>
			<circle cx="16" cy="11.5" r="1.05" fill="var(--adam-bot-launcher-dot)"/>
		</svg>
		<span class="adam-bot__launcher-pulse" aria-hidden="true"></span>
	</button>

	<section
		class="adam-bot__panel"
		id="adam-bot-panel"
		data-adam-bot-panel
		role="dialog"
		aria-modal="false"
		aria-labelledby="adam-bot-title"
		aria-describedby="adam-bot-subtitle"
		aria-hidden="true"
		hidden
	>
		<header class="adam-bot__header">
			<div class="adam-bot__brand-mark" aria-hidden="true">
				<svg viewBox="0 0 44 44" width="44" height="44" focusable="false">
					<path d="M22 4.5 38 13.7v16.6L22 39.5 6 30.3V13.7L22 4.5Z" fill="currentColor" opacity=".18"/>
					<path d="M14.8 30.5 21 13.2h2.2l6.1 17.3h-4.1l-1-3.4h-5l-1.1 3.4h-3.3Zm5.4-6.8h3l-1.5-5-1.5 5Z" fill="currentColor"/>
				</svg>
			</div>
			<div class="adam-bot__brand-copy">
				<h2 class="adam-bot__title" id="adam-bot-title"><span aria-hidden="true">🤖</span> <?php esc_html_e( 'ADAM BOT', 'adam-bot' ); ?></h2>
				<p class="adam-bot__subtitle" id="adam-bot-subtitle"><?php esc_html_e( 'Assistente Virtual da ADAM', 'adam-bot' ); ?></p>
			</div>
			<span class="adam-bot__status" title="<?php esc_attr_e( 'Disponível', 'adam-bot' ); ?>">
				<span class="adam-bot__screen-reader-text"><?php esc_html_e( 'Disponível', 'adam-bot' ); ?></span>
			</span>
			<button class="adam-bot__close" type="button" data-adam-bot-close aria-label="<?php esc_attr_e( 'Fechar o ADAM BOT', 'adam-bot' ); ?>">
				<svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" focusable="false">
					<path d="m6.75 6.75 10.5 10.5m0-10.5-10.5 10.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
				</svg>
			</button>
		</header>

		<div class="adam-bot__conversation" data-adam-bot-conversation>
			<div class="adam-bot__messages" data-adam-bot-messages role="log" aria-live="polite" aria-relevant="additions text">
				<div class="adam-bot__empty-state" data-adam-bot-empty-state>
					<div class="adam-bot__welcome-icon" aria-hidden="true">👋</div>
					<div class="adam-bot__welcome-copy">
						<p class="adam-bot__greeting"><?php esc_html_e( 'Olá!', 'adam-bot' ); ?></p>
						<p><?php esc_html_e( 'Sou o ADAM BOT.', 'adam-bot' ); ?></p>
						<p><?php esc_html_e( 'Posso ajudar com:', 'adam-bot' ); ?></p>
						<ul class="adam-bot__topics">
							<li><?php esc_html_e( 'Associação', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Sócios', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Eventos', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Airsoft', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Website', 'adam-bot' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Escolha uma sugestão abaixo ou escreva uma pergunta.', 'adam-bot' ); ?></p>
					</div>

					<div class="adam-bot__suggestions" aria-label="<?php esc_attr_e( 'Perguntas sugeridas', 'adam-bot' ); ?>">
						<?php foreach ( $suggestions as $suggestion ) : ?>
							<button class="adam-bot__suggestion" type="button" data-adam-bot-suggestion>
								<?php echo esc_html( $suggestion ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<form class="adam-bot__composer" data-adam-bot-form>
			<label class="adam-bot__screen-reader-text" for="adam-bot-input"><?php esc_html_e( 'Pergunte ao ADAM BOT', 'adam-bot' ); ?></label>
			<div class="adam-bot__input-wrap">
				<textarea
					class="adam-bot__input"
					id="adam-bot-input"
					data-adam-bot-input
					rows="1"
					maxlength="2000"
					placeholder="<?php esc_attr_e( 'Pergunte ao ADAM BOT...', 'adam-bot' ); ?>"
				></textarea>
				<button class="adam-bot__send" type="submit" data-adam-bot-send disabled aria-label="<?php esc_attr_e( 'Enviar mensagem', 'adam-bot' ); ?>">
					<span><?php esc_html_e( 'Enviar', 'adam-bot' ); ?></span>
					<svg aria-hidden="true" viewBox="0 0 24 24" width="19" height="19" focusable="false">
						<path d="m5 12 14-7-4.6 14-3-5.4L5 12Zm6.4 1.6L19 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>
			<p class="adam-bot__input-hint"><?php esc_html_e( 'Enter para enviar · Shift+Enter para nova linha', 'adam-bot' ); ?></p>
		</form>
	</section>
</div>
