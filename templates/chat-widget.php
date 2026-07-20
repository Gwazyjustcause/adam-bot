<?php
/**
 * Public chat widget markup.
 *
 * @package AdamBot
 */

defined( 'ABSPATH' ) || exit;

$suggestions = array(
	__( 'O que é a ADAM?', 'adam-bot' ),
	__( 'Como me posso tornar sócio?', 'adam-bot' ),
	__( 'Próximos eventos', 'adam-bot' ),
	__( 'Quanto custa ser sócio?', 'adam-bot' ),
	__( 'Como renovar a quota?', 'adam-bot' ),
	__( 'Legislação do Airsoft', 'adam-bot' ),
);
?>
<div id="adam-bot-root" class="adam-bot" data-adam-bot>
	<div class="adam-bot__backdrop" data-adam-backdrop aria-hidden="true"></div>

	<button
		type="button"
		class="adam-bot__launcher"
		data-adam-launcher
		aria-label="<?php esc_attr_e( 'Abrir conversa com o ADAM BOT', 'adam-bot' ); ?>"
		aria-controls="adam-bot-panel"
		aria-expanded="false"
	>
		<span class="adam-bot__brand adam-bot__brand--launcher" aria-hidden="true">
			<span class="adam-bot__brand-mark">A</span>
			<span class="adam-bot__brand-spark"></span>
		</span>
		<span class="adam-bot__notification" aria-hidden="true"></span>
	</button>

	<section
		id="adam-bot-panel"
		class="adam-bot__panel"
		data-adam-panel
		role="dialog"
		aria-modal="true"
		aria-labelledby="adam-bot-title"
		aria-describedby="adam-bot-subtitle"
		aria-hidden="true"
		inert
	>
		<header class="adam-bot__header">
			<div class="adam-bot__brand adam-bot__brand--header" aria-hidden="true">
				<span class="adam-bot__brand-mark">A</span>
				<span class="adam-bot__brand-spark"></span>
			</div>

			<div class="adam-bot__heading">
				<div class="adam-bot__presence">
					<span class="adam-bot__online-dot" aria-hidden="true"></span>
					<?php esc_html_e( 'Online', 'adam-bot' ); ?>
				</div>
				<h2 id="adam-bot-title"><span aria-hidden="true">🤖</span> ADAM BOT</h2>
				<p id="adam-bot-subtitle"><?php esc_html_e( 'Assistente Virtual da ADAM', 'adam-bot' ); ?></p>
			</div>

			<button
				type="button"
				class="adam-bot__close"
				data-adam-close
				aria-label="<?php esc_attr_e( 'Fechar conversa', 'adam-bot' ); ?>"
			>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M6 6l12 12M18 6 6 18"></path>
				</svg>
			</button>
		</header>

		<div
			class="adam-bot__conversation"
			data-adam-conversation
			role="log"
			aria-live="polite"
			aria-relevant="additions"
		>
			<div class="adam-bot__message adam-bot__message--bot">
				<div class="adam-bot__avatar" aria-hidden="true">A</div>
				<div class="adam-bot__message-content">
					<span class="adam-bot__sr-only"><?php esc_html_e( 'ADAM BOT:', 'adam-bot' ); ?></span>
					<div class="adam-bot__bubble adam-bot__welcome">
						<p><strong><?php esc_html_e( 'Olá!', 'adam-bot' ); ?></strong></p>
						<p><?php esc_html_e( 'Sou o ADAM BOT.', 'adam-bot' ); ?></p>
						<p><?php esc_html_e( 'Posso ajudar com:', 'adam-bot' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Associação', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Sócios', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Eventos', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Airsoft', 'adam-bot' ); ?></li>
							<li><?php esc_html_e( 'Website', 'adam-bot' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Escolha uma sugestão abaixo ou escreva uma pergunta.', 'adam-bot' ); ?></p>
					</div>
				</div>
			</div>

			<div class="adam-bot__suggestions" data-adam-suggestions aria-label="<?php esc_attr_e( 'Perguntas sugeridas', 'adam-bot' ); ?>">
				<p><?php esc_html_e( 'Sugestões rápidas', 'adam-bot' ); ?></p>
				<div class="adam-bot__chips">
					<?php foreach ( $suggestions as $suggestion ) : ?>
						<button type="button" class="adam-bot__chip" data-adam-message="<?php echo esc_attr( $suggestion ); ?>">
							<?php echo esc_html( $suggestion ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<form class="adam-bot__composer" data-adam-form novalidate>
			<label class="adam-bot__sr-only" for="adam-bot-input"><?php esc_html_e( 'Mensagem para o ADAM BOT', 'adam-bot' ); ?></label>
			<div class="adam-bot__input-wrap">
				<textarea
					id="adam-bot-input"
					class="adam-bot__input"
					data-adam-input
					rows="1"
					placeholder="<?php esc_attr_e( 'Pergunte ao ADAM BOT...', 'adam-bot' ); ?>"
					aria-label="<?php esc_attr_e( 'Mensagem para o ADAM BOT', 'adam-bot' ); ?>"
					spellcheck="true"
				></textarea>
				<button type="submit" class="adam-bot__send" data-adam-send disabled aria-label="<?php esc_attr_e( 'Enviar mensagem', 'adam-bot' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M4.5 11.2 19 4.7l-5.4 14.6-2.4-6.5-6.7-1.6Z"></path>
						<path d="m11.2 12.8 3.4-3.4"></path>
					</svg>
				</button>
			</div>
		</form>
	</section>
</div>
