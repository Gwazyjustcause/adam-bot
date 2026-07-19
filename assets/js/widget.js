( function () {
	'use strict';

	const config = window.AdamBotConfig || {};

	/**
	 * Simulates the future provider boundary without making a network request.
	 */
	class FakeChatService {
		constructor( serviceConfig ) {
			this.message = serviceConfig.message || '';
			this.delay = Math.max( 0, Number( serviceConfig.delay ) || 1000 );
		}

		reply() {
			return new Promise( ( resolve ) => {
				window.setTimeout( () => resolve( this.message ), this.delay );
			} );
		}
	}

	/**
	 * Controls one ADAM BOT widget instance.
	 */
	class AdamBotWidget {
		constructor( root, widgetConfig ) {
			this.root = root;
			this.panel = root.querySelector( '[data-adam-bot-panel]' );
			this.launcher = root.querySelector( '[data-adam-bot-launcher]' );
			this.closeButton = root.querySelector( '[data-adam-bot-close]' );
			this.backdrop = root.querySelector( '[data-adam-bot-backdrop]' );
			this.form = root.querySelector( '[data-adam-bot-form]' );
			this.input = root.querySelector( '[data-adam-bot-input]' );
			this.sendButton = root.querySelector( '[data-adam-bot-send]' );
			this.messages = root.querySelector( '[data-adam-bot-messages]' );
			this.conversation = root.querySelector( '[data-adam-bot-conversation]' );
			this.emptyState = root.querySelector( '[data-adam-bot-empty-state]' );
			this.suggestions = root.querySelectorAll( '[data-adam-bot-suggestion]' );
			this.strings = widgetConfig.strings || {};
			this.service = new FakeChatService( widgetConfig.fakeResponse || {} );
			this.isOpen = false;
			this.closeTimer = null;
		}

		mount() {
			if ( ! this.panel || ! this.launcher || ! this.form || ! this.input ) {
				return;
			}

			this.launcher.addEventListener( 'click', () => this.open() );
			this.closeButton.addEventListener( 'click', () => this.close() );
			this.backdrop.addEventListener( 'click', () => this.close() );
			this.form.addEventListener( 'submit', ( event ) => this.handleSubmit( event ) );
			this.input.addEventListener( 'input', () => this.handleInput() );
			this.input.addEventListener( 'keydown', ( event ) => this.handleInputKeydown( event ) );

			this.suggestions.forEach( ( suggestion ) => {
				suggestion.addEventListener( 'click', () => this.sendMessage( suggestion.textContent.trim() ) );
			} );

			document.addEventListener( 'keydown', ( event ) => {
				if ( 'Escape' === event.key && this.isOpen ) {
					event.preventDefault();
					this.close();
				}
			} );
		}

		open() {
			if ( this.isOpen ) {
				return;
			}

			window.clearTimeout( this.closeTimer );
			this.isOpen = true;
			this.panel.hidden = false;
			this.panel.inert = false;
			this.panel.setAttribute( 'aria-hidden', 'false' );
			this.launcher.setAttribute( 'aria-expanded', 'true' );

			window.requestAnimationFrame( () => {
				this.root.classList.add( 'adam-bot--open' );
				this.input.focus( { preventScroll: true } );
			} );
		}

		close() {
			if ( ! this.isOpen ) {
				return;
			}

			this.isOpen = false;
			this.root.classList.remove( 'adam-bot--open' );
			this.panel.inert = true;
			this.panel.setAttribute( 'aria-hidden', 'true' );
			this.launcher.setAttribute( 'aria-expanded', 'false' );
			this.launcher.focus( { preventScroll: true } );

			this.closeTimer = window.setTimeout( () => {
				if ( ! this.isOpen ) {
					this.panel.hidden = true;
				}
			}, 260 );
		}

		handleSubmit( event ) {
			event.preventDefault();
			this.sendMessage( this.input.value );
		}

		handleInput() {
			this.updateSendButton();
			this.resizeInput();
		}

		handleInputKeydown( event ) {
			if ( 'Enter' !== event.key || event.shiftKey || event.isComposing ) {
				return;
			}

			event.preventDefault();
			this.sendMessage( this.input.value );
		}

		async sendMessage( rawMessage ) {
			const message = rawMessage.trim();

			if ( ! message ) {
				return;
			}

			if ( this.emptyState ) {
				this.emptyState.remove();
				this.emptyState = null;
			}

			this.appendMessage( message, 'user', this.strings.userLabel );
			this.input.value = '';
			this.updateSendButton();
			this.resizeInput();

			const typingIndicator = this.appendTypingIndicator();
			const response = await this.service.reply( message );

			typingIndicator.remove();
			this.appendMessage( response, 'assistant', this.strings.assistantLabel );
		}

		appendMessage( text, author, accessibleLabel ) {
			const message = document.createElement( 'div' );
			const label = document.createElement( 'span' );
			const bubble = document.createElement( 'p' );

			message.className = `adam-bot__message adam-bot__message--${ author }`;
			label.className = 'adam-bot__screen-reader-text';
			label.textContent = accessibleLabel || '';
			bubble.className = 'adam-bot__bubble';
			bubble.textContent = text;

			message.append( label, bubble );
			this.messages.append( message );
			this.scrollToLatest();

			return message;
		}

		appendTypingIndicator() {
			const indicator = document.createElement( 'div' );
			const label = document.createElement( 'span' );
			const dots = document.createElement( 'span' );

			indicator.className = 'adam-bot__message adam-bot__message--assistant adam-bot__typing';
			indicator.setAttribute( 'role', 'status' );
			label.className = 'adam-bot__screen-reader-text';
			label.textContent = this.strings.typingLabel || '';
			dots.className = 'adam-bot__typing-dots';
			dots.setAttribute( 'aria-hidden', 'true' );

			for ( let index = 0; index < 3; index += 1 ) {
				dots.append( document.createElement( 'span' ) );
			}

			indicator.append( label, dots );
			this.messages.append( indicator );
			this.scrollToLatest();

			return indicator;
		}

		updateSendButton() {
			this.sendButton.disabled = ! this.input.value.trim();
		}

		resizeInput() {
			this.input.style.height = 'auto';
			this.input.style.height = `${ Math.min( this.input.scrollHeight, 120 ) }px`;
		}

		scrollToLatest() {
			window.requestAnimationFrame( () => {
				this.conversation.scrollTo( {
					top: this.conversation.scrollHeight,
					behavior: 'smooth',
				} );
			} );
		}
	}

	function initialize() {
		document.querySelectorAll( '[data-adam-bot-root]' ).forEach( ( root ) => {
			new AdamBotWidget( root, config ).mount();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialize, { once: true } );
	} else {
		initialize();
	}
}() );
