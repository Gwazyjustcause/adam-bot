<?php
/**
 * Lazy-hydrated public chat widget markup.
 *
 * @package AdamBot
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="adam-bot-root" class="adam-bot" data-adam-bot>
	<button
		type="button"
		class="adam-bot__launcher"
		data-adam-launcher
		aria-label="<?php esc_attr_e( 'Abrir conversa com o ADAM BOT', 'adam-bot' ); ?>"
		aria-controls="adam-bot-panel"
		aria-haspopup="dialog"
		aria-expanded="false"
	>
		<span class="adam-bot__brand adam-bot__brand--launcher" aria-hidden="true">
			<span class="adam-bot__brand-mark">A</span>
			<span class="adam-bot__brand-spark"></span>
		</span>
		<span class="adam-bot__notification" aria-hidden="true"></span>
	</button>

	<template data-adam-template>
		<div class="adam-bot__backdrop" data-adam-backdrop aria-hidden="true"></div>

		<section
			id="adam-bot-panel"
			class="adam-bot__panel"
			data-adam-panel
			role="dialog"
			aria-modal="true"
			aria-labelledby="adam-bot-title"
			aria-describedby="adam-bot-subtitle"
			aria-hidden="true"
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
					<p id="adam-bot-subtitle"><?php esc_html_e( 'Assistente virtual da ADAM', 'adam-bot' ); ?></p>
				</div>

				<div class="adam-bot__header-actions">
					<button type="button" class="adam-bot__header-home" data-adam-home aria-label="<?php esc_attr_e( 'Voltar ao início', 'adam-bot' ); ?>" title="<?php esc_attr_e( 'Início', 'adam-bot' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m4 11 8-7 8 7"></path><path d="M6.5 9.5V20h11V9.5M10 20v-6h4v6"></path></svg>
					</button>
					<button type="button" class="adam-bot__close" data-adam-close aria-label="<?php esc_attr_e( 'Fechar conversa', 'adam-bot' ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path d="M6 6l12 12M18 6 6 18"></path>
						</svg>
					</button>
				</div>
			</header>

			<nav class="adam-bot__conversation-toolbar" data-adam-toolbar aria-label="<?php esc_attr_e( 'Controlos da conversa', 'adam-bot' ); ?>">
				<button type="button" data-adam-home><span aria-hidden="true">🏠</span> <span data-adam-home-label><?php esc_html_e( 'Início', 'adam-bot' ); ?></span></button>
				<button type="button" data-adam-new-conversation><span aria-hidden="true">🗑️</span> <span data-adam-new-label><?php esc_html_e( 'Nova conversa', 'adam-bot' ); ?></span></button>
			</nav>

			<div class="adam-bot__conversation" data-adam-conversation>
				<section class="adam-bot__welcome" data-adam-welcome aria-labelledby="adam-bot-welcome-title">
					<div class="adam-bot__welcome-icon" aria-hidden="true">👋</div>
					<h3 id="adam-bot-welcome-title"><?php esc_html_e( 'Bem-vindo!', 'adam-bot' ); ?></h3>
					<p><?php esc_html_e( 'Sou o ADAM BOT, o assistente virtual da ADAM. Posso ajudá-lo a explorar a associação, serviços e atividades.', 'adam-bot' ); ?></p>
					<p><?php esc_html_e( 'Posso ajudar com:', 'adam-bot' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Sócios', 'adam-bot' ); ?></li>
						<li><?php esc_html_e( 'Eventos', 'adam-bot' ); ?></li>
						<li><?php esc_html_e( 'Airsoft', 'adam-bot' ); ?></li>
						<li><?php esc_html_e( 'Informação do website', 'adam-bot' ); ?></li>
					</ul>
					<p id="adam-bot-search-prompt" class="adam-bot__search-prompt"><?php esc_html_e( 'Escolha um tema, uma pergunta sugerida ou pesquise abaixo.', 'adam-bot' ); ?></p>
				</section>

				<nav class="adam-bot__quick-actions" data-adam-quick-actions aria-labelledby="adam-bot-quick-actions-title">
					<h3 id="adam-bot-quick-actions-title"><?php esc_html_e( 'Perguntas sugeridas', 'adam-bot' ); ?></h3>
					<div class="adam-bot__action-grid">
						<?php foreach ( $quick_actions as $action ) : ?>
							<button type="button" class="adam-bot__action-card" data-adam-message="<?php echo esc_attr( (string) $action['prompt'] ); ?>">
								<span class="adam-bot__action-icon" aria-hidden="true"><?php echo esc_html( (string) $action['icon'] ); ?></span>
								<span><?php echo esc_html( (string) $action['label'] ); ?></span>
								<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="m7.5 4.5 5 5-5 5"></path></svg>
							</button>
						<?php endforeach; ?>
					</div>
				</nav>

				<div class="adam-bot__messages" data-adam-messages role="log" aria-live="polite" aria-relevant="additions text"></div>

				<nav class="adam-bot__topics" data-adam-topics aria-labelledby="adam-bot-topics-title">
					<h3 id="adam-bot-topics-title" data-adam-topics-title><?php esc_html_e( 'Explorar temas', 'adam-bot' ); ?></h3>
					<div class="adam-bot__topic-buttons">
						<?php
						$topics = array(
							array( 'association', 'Associação', 'Association', 'O que é a ADAM?', 'What is ADAM?' ),
							array( 'membership', 'Sócios', 'Membership', 'Como me posso tornar sócio?', 'How can I become a member?' ),
							array( 'events', 'Eventos', 'Events', 'Quais são os próximos eventos?', 'What are the upcoming events?' ),
							array( 'teams', 'Equipas', 'Teams', 'Que equipas existem?', 'What teams are available?' ),
							array( 'fields', 'Campos', 'Fields', 'Onde ficam os campos associados?', 'Where are the associated fields?' ),
							array( 'partners', 'Parceiros', 'Partners', 'Que parceiros e benefícios existem?', 'Which partners and benefits are available?' ),
							array( 'contact', 'Contactos', 'Contacts', 'Como posso contactar a ADAM?', 'How can I contact ADAM?' ),
						);
						foreach ( $topics as $topic ) :
							?>
							<button type="button" data-adam-topic="<?php echo esc_attr( $topic[0] ); ?>" data-adam-label-pt="<?php echo esc_attr( $topic[1] ); ?>" data-adam-label-en="<?php echo esc_attr( $topic[2] ); ?>" data-adam-prompt-pt="<?php echo esc_attr( $topic[3] ); ?>" data-adam-prompt-en="<?php echo esc_attr( $topic[4] ); ?>" data-adam-message="<?php echo esc_attr( $topic[3] ); ?>"><?php echo esc_html( $topic[1] ); ?></button>
						<?php endforeach; ?>
					</div>
				</nav>
			</div>

			<div class="adam-bot__sr-only" data-adam-status role="status" aria-live="polite" aria-atomic="true"></div>

			<form class="adam-bot__composer" data-adam-form novalidate>
				<label class="adam-bot__sr-only" for="adam-bot-input"><?php esc_html_e( 'Mensagem para o ADAM BOT', 'adam-bot' ); ?></label>
				<div class="adam-bot__input-wrap">
					<textarea
						id="adam-bot-input"
						class="adam-bot__input"
						data-adam-input
						rows="1"
						maxlength="4000"
						placeholder="<?php esc_attr_e( 'Pergunte ao ADAM BOT…', 'adam-bot' ); ?>"
						aria-label="<?php esc_attr_e( 'Mensagem para o ADAM BOT', 'adam-bot' ); ?>"
						aria-describedby="adam-bot-search-prompt"
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
	</template>
</div>
