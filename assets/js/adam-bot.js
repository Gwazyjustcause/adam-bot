/**
 * ADAM BOT public chat interface.
 */

( function () {
	'use strict';

	const INTERACTION_KEY = 'adamBotInteracted';
	const ERROR_MESSAGE = 'Desculpe.\n\nNeste momento não consegui responder.\n\nTente novamente daqui a alguns instantes.';

	/**
	 * Small REST client. The interface only knows the stable chat endpoint and
	 * its message response; backend providers remain an implementation detail.
	 */
	class ChatApi {
		constructor( settings ) {
			this.endpoint = settings && typeof settings.restUrl === 'string' ? settings.restUrl : '';
			this.nonce = settings && typeof settings.nonce === 'string' ? settings.nonce : '';
		}

		async send( message ) {
			if ( ! this.endpoint ) {
				throw new Error( 'Missing REST endpoint.' );
			}

			const headers = {
				Accept: 'application/json',
				'Content-Type': 'application/json',
			};

			if ( this.nonce ) {
				headers[ 'X-WP-Nonce' ] = this.nonce;
			}

			const response = await window.fetch( this.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers,
				body: JSON.stringify( { message } ),
			} );

			let payload;

			try {
				payload = await response.json();
			} catch ( error ) {
				throw new Error( 'Invalid REST response.' );
			}

			if ( ! response.ok || ! payload || typeof payload.message !== 'string' || ! payload.message.trim() ) {
				throw new Error( 'Unsuccessful REST response.' );
			}

			return payload.message;
		}
	}

	/**
	 * Coordinates the widget view and its accessible interaction state.
	 */
	class ChatWidget {
		constructor( root, api ) {
			this.root = root;
			this.api = api;
			this.launcher = root.querySelector( '[data-adam-launcher]' );
			this.panel = root.querySelector( '[data-adam-panel]' );
			this.closeButton = root.querySelector( '[data-adam-close]' );
			this.backdrop = root.querySelector( '[data-adam-backdrop]' );
			this.conversation = root.querySelector( '[data-adam-conversation]' );
			this.form = root.querySelector( '[data-adam-form]' );
			this.input = root.querySelector( '[data-adam-input]' );
			this.sendButton = root.querySelector( '[data-adam-send]' );
			this.isOpen = false;
			this.isBusy = false;
			this.typingMessage = null;
		}

		init() {
			if ( ! this.launcher || ! this.panel || ! this.form || ! this.input || ! this.sendButton || ! this.conversation ) {
				return;
			}

			this.launcher.addEventListener( 'click', () => this.open() );
			this.closeButton.addEventListener( 'click', () => this.close() );
			this.backdrop.addEventListener( 'click', () => this.close() );
			this.form.addEventListener( 'submit', ( event ) => this.handleSubmit( event ) );
			this.input.addEventListener( 'input', () => this.handleInput() );
			this.input.addEventListener( 'keydown', ( event ) => this.handleInputKeydown( event ) );
			this.root.addEventListener( 'click', ( event ) => this.handleSuggestion( event ) );
			document.addEventListener( 'keydown', ( event ) => this.handleDocumentKeydown( event ) );
			this.launcher.addEventListener( 'animationend', ( event ) => {
				if ( event.animationName === 'adam-bot-greeting' ) {
					this.root.classList.remove( 'is-greeting' );
				}
			} );

			if ( this.wasPreviouslyInteractedWith() ) {
				this.root.classList.add( 'has-interacted' );
			}

			this.resizeInput();
			this.updateComposer();

			window.requestAnimationFrame( () => {
				this.root.classList.add( 'is-ready' );

				if ( ! this.root.classList.contains( 'has-interacted' ) ) {
					this.root.classList.add( 'is-greeting' );
				}
			} );
		}

		open() {
			if ( this.isOpen ) {
				return;
			}

			this.isOpen = true;
			this.markInteracted();
			this.panel.removeAttribute( 'inert' );
			this.panel.setAttribute( 'aria-hidden', 'false' );
			this.launcher.setAttribute( 'aria-expanded', 'true' );
			this.root.classList.add( 'is-open' );

			window.setTimeout( () => {
				if ( this.isOpen && ! this.isBusy ) {
					this.input.focus( { preventScroll: true } );
				}
			}, 180 );
		}

		close( restoreFocus = true ) {
			if ( ! this.isOpen ) {
				return;
			}

			this.isOpen = false;
			this.root.classList.remove( 'is-open' );
			this.panel.setAttribute( 'aria-hidden', 'true' );
			this.panel.setAttribute( 'inert', '' );
			this.launcher.setAttribute( 'aria-expanded', 'false' );

			if ( restoreFocus ) {
				this.launcher.focus( { preventScroll: true } );
			}
		}

		handleInput() {
			this.resizeInput();
			this.updateComposer();
		}

		handleInputKeydown( event ) {
			if ( event.key !== 'Enter' || event.shiftKey || event.isComposing ) {
				return;
			}

			event.preventDefault();
			this.form.requestSubmit();
		}

		handleSubmit( event ) {
			event.preventDefault();
			this.submitMessage( this.input.value );
		}

		handleSuggestion( event ) {
			const target = event.target.closest( '[data-adam-message]' );

			if ( ! target || ! this.root.contains( target ) || this.isBusy ) {
				return;
			}

			this.submitMessage( target.getAttribute( 'data-adam-message' ) || '' );
		}

		handleDocumentKeydown( event ) {
			if ( ! this.isOpen ) {
				return;
			}

			if ( event.key === 'Escape' ) {
				event.preventDefault();
				this.close();
				return;
			}

			if ( event.key === 'Tab' ) {
				this.keepFocusInsidePanel( event );
			}
		}

		keepFocusInsidePanel( event ) {
			const focusable = Array.from(
				this.panel.querySelectorAll( 'button:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])' )
			).filter( ( element ) => element.getClientRects().length > 0 );

			if ( ! focusable.length ) {
				return;
			}

			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];

			if ( event.shiftKey && ( document.activeElement === first || ! this.panel.contains( document.activeElement ) ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && ( document.activeElement === last || ! this.panel.contains( document.activeElement ) ) ) {
				event.preventDefault();
				first.focus();
			}
		}

		async submitMessage( rawMessage ) {
			const message = rawMessage.trim();

			if ( ! message || this.isBusy ) {
				return;
			}

			this.root.classList.add( 'has-messages' );
			this.appendMessage( message, 'user' );
			this.input.value = '';
			this.resizeInput();
			this.setBusy( true );
			this.showTyping();

			try {
				const reply = await this.api.send( message );
				this.removeTyping();
				this.appendMessage( reply, 'bot' );
			} catch ( error ) {
				this.removeTyping();
				this.appendMessage( ERROR_MESSAGE, 'bot' );
			} finally {
				this.setBusy( false );

				if ( this.isOpen ) {
					this.input.focus( { preventScroll: true } );
				}
			}
		}

		appendMessage( message, author ) {
			const item = document.createElement( 'div' );
			const content = document.createElement( 'div' );
			const label = document.createElement( 'span' );
			const bubble = document.createElement( 'div' );

			item.className = `adam-bot__message adam-bot__message--${ author }`;
			content.className = 'adam-bot__message-content';
			label.className = 'adam-bot__sr-only';
			label.textContent = author === 'user' ? 'Você: ' : 'ADAM BOT: ';
			bubble.className = 'adam-bot__bubble';
			bubble.textContent = message;

			if ( author === 'bot' ) {
				const avatar = document.createElement( 'div' );
				avatar.className = 'adam-bot__avatar';
				avatar.setAttribute( 'aria-hidden', 'true' );
				avatar.textContent = 'A';
				item.appendChild( avatar );
			}

			content.append( label, bubble );
			item.appendChild( content );
			this.conversation.appendChild( item );
			this.scrollToLatest();
		}

		showTyping() {
			const item = document.createElement( 'div' );
			const avatar = document.createElement( 'div' );
			const content = document.createElement( 'div' );
			const bubble = document.createElement( 'div' );
			const text = document.createElement( 'span' );
			const dots = document.createElement( 'span' );

			item.className = 'adam-bot__message adam-bot__message--bot adam-bot__typing';
			item.setAttribute( 'role', 'status' );
			avatar.className = 'adam-bot__avatar';
			avatar.setAttribute( 'aria-hidden', 'true' );
			avatar.textContent = 'A';
			content.className = 'adam-bot__message-content';
			bubble.className = 'adam-bot__bubble adam-bot__typing-bubble';
			text.className = 'adam-bot__typing-text';
			text.textContent = 'ADAM BOT está a escrever';
			dots.className = 'adam-bot__typing-dots';
			dots.setAttribute( 'aria-hidden', 'true' );
			dots.append( document.createElement( 'span' ), document.createElement( 'span' ), document.createElement( 'span' ) );

			bubble.append( text, dots );
			content.appendChild( bubble );
			item.append( avatar, content );
			this.conversation.appendChild( item );
			this.typingMessage = item;
			this.scrollToLatest();
		}

		removeTyping() {
			if ( this.typingMessage ) {
				this.typingMessage.remove();
				this.typingMessage = null;
			}
		}

		setBusy( busy ) {
			this.isBusy = busy;
			this.input.disabled = busy;
			this.conversation.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
			this.updateComposer();
		}

		updateComposer() {
			this.sendButton.disabled = this.isBusy || ! this.input.value.trim();
		}

		resizeInput() {
			this.input.style.height = 'auto';
			const maxHeight = Number.parseFloat( window.getComputedStyle( this.input ).maxHeight ) || 131;
			const nextHeight = Math.min( this.input.scrollHeight, maxHeight );
			this.input.style.height = `${ nextHeight }px`;
			this.input.style.overflowY = this.input.scrollHeight > maxHeight ? 'auto' : 'hidden';
		}

		scrollToLatest() {
			window.requestAnimationFrame( () => {
				this.conversation.scrollTo( {
					top: this.conversation.scrollHeight,
					behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth',
				} );
			} );
		}

		wasPreviouslyInteractedWith() {
			try {
				return window.sessionStorage.getItem( INTERACTION_KEY ) === '1';
			} catch ( error ) {
				return false;
			}
		}

		markInteracted() {
			this.root.classList.add( 'has-interacted' );
			this.root.classList.remove( 'is-greeting' );

			try {
				window.sessionStorage.setItem( INTERACTION_KEY, '1' );
			} catch ( error ) {
				// Storage can be unavailable in privacy-restricted contexts.
			}
		}
	}

	function start() {
		const root = document.querySelector( '[data-adam-bot]' );

		if ( ! root ) {
			return;
		}

		const settings = window.adamBotSettings || {};
		const widget = new ChatWidget( root, new ChatApi( settings ) );
		widget.init();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
}() );
