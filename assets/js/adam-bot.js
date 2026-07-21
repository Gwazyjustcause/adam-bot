/**
 * ADAM BOT intelligent public chat experience.
 */

( function () {
	'use strict';

	const INTERACTION_KEY = 'adamBotInteracted';
	const WELCOME_KEY = 'adamBotWelcomeSeenV1';
	const SESSION_KEY = 'adamBotConversationV2';
	const MAX_MESSAGES = 40;
	const MAX_CACHE_ENTRIES = 8;

	class ChatApi {
		constructor( settings ) {
			this.endpoint = settings && typeof settings.restUrl === 'string' ? settings.restUrl : '';
			this.nonce = settings && typeof settings.nonce === 'string' ? settings.nonce : '';
		}

		async send( message, options = {} ) {
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
				body: JSON.stringify( {
					message,
					context: options.context && typeof options.context === 'object' ? options.context : {},
					new_conversation: options.newConversation === true,
				} ),
			} );

			let payload;

			try {
				payload = await response.json();
			} catch ( error ) {
				throw new Error( 'Invalid REST response.' );
			}

			if ( ! payload || typeof payload.message !== 'string' || ! payload.message.trim() ) {
				throw new Error( 'Unsuccessful REST response.' );
			}

			return {
				success: payload.success !== false,
				message: payload.message.trim(),
				suggestions: Array.isArray( payload.suggestions ) ? payload.suggestions : [],
				links: Array.isArray( payload.links ) ? payload.links : [],
				cards: Array.isArray( payload.cards ) ? payload.cards : [],
				context: payload.context && typeof payload.context === 'object' ? payload.context : {},
			};
		}
	}

	/**
	 * A deliberately small Markdown subset rendered entirely through DOM APIs.
	 * HTML from the model is always treated as text.
	 */
	class SafeMarkdown {
		render( markdown ) {
			const fragment = document.createDocumentFragment();
			const lines = String( markdown || '' ).replace( /\r\n?/g, '\n' ).split( '\n' );
			let index = 0;

			while ( index < lines.length ) {
				const line = lines[ index ];

				if ( ! line.trim() ) {
					index++;
					continue;
				}

				const heading = line.match( /^(#{1,3})\s+(.+)$/ );
				const callout = line.match( /^\[!(warning|information|success)\]\s+(.+)$/i );
				if ( callout ) {
					const element = document.createElement( 'div' );
					const label = document.createElement( 'strong' );
					element.className = `adam-bot__callout adam-bot__callout--${ callout[ 1 ].toLowerCase() }`;
					label.textContent = `${ callout[ 1 ].charAt( 0 ).toUpperCase() + callout[ 1 ].slice( 1 ).toLowerCase() }: `;
					element.appendChild( label );
					this.appendInline( element, callout[ 2 ] );
					fragment.appendChild( element );
					index++;
					continue;
				}
				if ( heading ) {
					const element = document.createElement( `h${ heading[ 1 ].length + 2 }` );
					this.appendInline( element, heading[ 2 ] );
					fragment.appendChild( element );
					index++;
					continue;
				}

				if ( this.isTable( lines, index ) ) {
					const result = this.createTable( lines, index );
					fragment.appendChild( result.element );
					index = result.nextIndex;
					continue;
				}

				const unordered = line.match( /^\s*[-*+]\s+(.+)$/ );
				const ordered = line.match( /^\s*\d+[.)]\s+(.+)$/ );

				if ( unordered || ordered ) {
					const list = document.createElement( ordered ? 'ol' : 'ul' );
					const pattern = ordered ? /^\s*\d+[.)]\s+(.+)$/ : /^\s*[-*+]\s+(.+)$/;

					while ( index < lines.length ) {
						const itemMatch = lines[ index ].match( pattern );
						if ( ! itemMatch ) {
							break;
						}

						const item = document.createElement( 'li' );
						this.appendInline( item, itemMatch[ 1 ] );
						list.appendChild( item );
						index++;
					}

					fragment.appendChild( list );
					continue;
				}

				const paragraphLines = [ line.trim() ];
				index++;

				while ( index < lines.length && lines[ index ].trim() && ! this.startsBlock( lines, index ) ) {
					paragraphLines.push( lines[ index ].trim() );
					index++;
				}

				const paragraph = document.createElement( 'p' );
				this.appendInline( paragraph, paragraphLines.join( ' ' ) );
				fragment.appendChild( paragraph );
			}

			return fragment;
		}

		startsBlock( lines, index ) {
			return /^\[!(warning|information|success)\]\s+/i.test( lines[ index ] )
				|| /^(#{1,3})\s+/.test( lines[ index ] )
				|| /^\s*[-*+]\s+/.test( lines[ index ] )
				|| /^\s*\d+[.)]\s+/.test( lines[ index ] )
				|| this.isTable( lines, index );
		}

		isTable( lines, index ) {
			return index + 1 < lines.length
				&& lines[ index ].includes( '|' )
				&& /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/.test( lines[ index + 1 ] );
		}

		createTable( lines, startIndex ) {
			const wrapper = document.createElement( 'div' );
			const table = document.createElement( 'table' );
			const head = document.createElement( 'thead' );
			const body = document.createElement( 'tbody' );
			const headRow = document.createElement( 'tr' );
			const headers = this.splitTableRow( lines[ startIndex ] );
			let index = startIndex + 2;

			headers.forEach( ( value ) => {
				const cell = document.createElement( 'th' );
				cell.setAttribute( 'scope', 'col' );
				this.appendInline( cell, value );
				headRow.appendChild( cell );
			} );
			head.appendChild( headRow );

			while ( index < lines.length && lines[ index ].includes( '|' ) && lines[ index ].trim() ) {
				const row = document.createElement( 'tr' );
				this.splitTableRow( lines[ index ] ).slice( 0, headers.length ).forEach( ( value ) => {
					const cell = document.createElement( 'td' );
					this.appendInline( cell, value );
					row.appendChild( cell );
				} );
				body.appendChild( row );
				index++;
			}

			table.append( head, body );
			wrapper.className = 'adam-bot__table-wrap';
			wrapper.appendChild( table );

			return { element: wrapper, nextIndex: index };
		}

		splitTableRow( value ) {
			return value.trim().replace( /^\||\|$/g, '' ).split( '|' ).slice( 0, 8 ).map( ( cell ) => cell.trim() );
		}

		appendInline( parent, value ) {
			const tokenPattern = /(\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|\*\*([^*]+)\*\*|__([^_]+)__|`([^`]+)`|(https?:\/\/[^\s<]+))/gi;
			let cursor = 0;
			let match;

			while ( ( match = tokenPattern.exec( value ) ) !== null ) {
				if ( match.index > cursor ) {
					parent.appendChild( document.createTextNode( value.slice( cursor, match.index ) ) );
				}

				if ( match[ 2 ] && match[ 3 ] ) {
					this.appendSafeLink( parent, match[ 2 ], match[ 3 ] );
				} else if ( match[ 4 ] || match[ 5 ] ) {
					const strong = document.createElement( 'strong' );
					strong.textContent = match[ 4 ] || match[ 5 ];
					parent.appendChild( strong );
				} else if ( match[ 6 ] ) {
					const code = document.createElement( 'code' );
					code.textContent = match[ 6 ];
					parent.appendChild( code );
				} else if ( match[ 7 ] ) {
					this.appendSafeLink( parent, 'Saber mais →', match[ 7 ].replace( /[.,;:!?]+$/, '' ) );
				}

				cursor = tokenPattern.lastIndex;
			}

			if ( cursor < value.length ) {
				parent.appendChild( document.createTextNode( value.slice( cursor ) ) );
			}
		}

		appendSafeLink( parent, label, url ) {
			const safeUrl = getSafeUrl( url );

			if ( ! safeUrl ) {
				parent.appendChild( document.createTextNode( label ) );
				return;
			}

			const link = document.createElement( 'a' );
			link.href = safeUrl.href;
			link.textContent = label;
			link.rel = 'noopener noreferrer';
			parent.appendChild( link );
		}
	}

	class ChatWidget {
		constructor( root, api, settings ) {
			this.root = root;
			this.api = api;
			this.settings = settings || {};
			this.strings = this.settings.strings || {};
			this.markdown = new SafeMarkdown();
			this.launcher = root.querySelector( '[data-adam-launcher]' );
			this.template = root.querySelector( '[data-adam-template]' );
			this.isHydrated = false;
			this.isOpen = false;
			this.isBusy = false;
			this.typingMessage = null;
			this.lastSubmission = { message: '', time: 0 };
			this.state = this.readState();
		}

		init() {
			if ( ! this.launcher || ! this.template ) {
				return;
			}

			this.launcher.addEventListener( 'click', () => this.open() );
			document.addEventListener( 'keydown', ( event ) => this.handleDocumentKeydown( event ) );
			window.addEventListener( 'pagehide', () => this.persistState() );
			this.launcher.addEventListener( 'animationend', ( event ) => {
				if ( event.animationName === 'adam-bot-greeting' ) {
					this.root.classList.remove( 'is-greeting' );
				}
			} );

			if ( this.wasPreviouslyInteractedWith() ) {
				this.root.classList.add( 'has-interacted' );
			}

			window.requestAnimationFrame( () => {
				this.root.classList.add( 'is-ready' );
				if ( ! this.root.classList.contains( 'has-interacted' ) ) {
					this.root.classList.add( 'is-greeting' );
				}
			} );
		}

		hydrate() {
			if ( this.isHydrated ) {
				return;
			}

			this.root.appendChild( this.template.content.cloneNode( true ) );
			this.panel = this.root.querySelector( '[data-adam-panel]' );
			this.closeButton = this.root.querySelector( '[data-adam-close]' );
			this.backdrop = this.root.querySelector( '[data-adam-backdrop]' );
			this.conversation = this.root.querySelector( '[data-adam-conversation]' );
			this.messages = this.root.querySelector( '[data-adam-messages]' );
			this.welcome = this.root.querySelector( '[data-adam-welcome]' );
			this.quickActions = this.root.querySelector( '[data-adam-quick-actions]' );
			this.form = this.root.querySelector( '[data-adam-form]' );
			this.input = this.root.querySelector( '[data-adam-input]' );
			this.sendButton = this.root.querySelector( '[data-adam-send]' );
			this.status = this.root.querySelector( '[data-adam-status]' );
			this.panel.setAttribute( 'inert', '' );

			this.closeButton.addEventListener( 'click', () => this.close() );
			this.backdrop.addEventListener( 'click', () => this.close() );
			this.form.addEventListener( 'submit', ( event ) => this.handleSubmit( event ) );
			this.input.addEventListener( 'input', () => this.handleInput() );
			this.input.addEventListener( 'keydown', ( event ) => this.handleInputKeydown( event ) );
			this.root.addEventListener( 'click', ( event ) => this.handleAction( event ) );

			if ( window.visualViewport ) {
				window.visualViewport.addEventListener( 'resize', () => this.updateViewportHeight() );
				window.visualViewport.addEventListener( 'scroll', () => this.updateViewportHeight() );
			}

			this.isHydrated = true;
			this.restoreConversation();
			this.resizeInput();
			this.updateComposer();
			this.updateViewportHeight();
		}

		open() {
			if ( this.isOpen ) {
				return;
			}

			this.hydrate();
			this.isOpen = true;
			this.markInteracted();
			this.markWelcomeSeen();
			this.panel.removeAttribute( 'inert' );
			this.panel.setAttribute( 'aria-hidden', 'false' );
			this.launcher.setAttribute( 'aria-expanded', 'true' );
			this.root.classList.add( 'is-open' );
			this.updateViewportHeight();

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
			this.persistState();
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

		handleAction( event ) {
			const target = event.target.closest( '[data-adam-message], [data-adam-action]' );

			if ( ! target || ! this.root.contains( target ) || this.isBusy ) {
				return;
			}

			this.submitMessage( target.getAttribute( 'data-adam-message' ) || '' );
		}

		handleDocumentKeydown( event ) {
			if ( ! this.isOpen || ! this.isHydrated ) {
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
			const focusable = Array.from( this.panel.querySelectorAll(
				'button:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
			) ).filter( ( element ) => element.getClientRects().length > 0 );

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
			const message = String( rawMessage || '' ).trim();
			const now = Date.now();

			if ( ! message || this.isBusy ) {
				return;
			}

			if ( message === this.lastSubmission.message && now - this.lastSubmission.time < 900 ) {
				return;
			}

			this.lastSubmission = { message, time: now };
			const context = this.getKnowledgeContext();
			const newConversation = ! this.state.conversationStarted;
			const cacheKey = this.createCacheKey( message, context );
			const cached = this.getCachedResponse( cacheKey );

			this.hideStarters();
			this.appendMessage( message, 'user' );
			this.state.conversationStarted = true;
			this.input.value = '';
			this.resizeInput();
			this.setBusy( true );
			this.showTyping();

			try {
				const reply = cached || await this.api.send( message, { context, newConversation } );
				if ( ! cached ) {
					this.cacheResponse( cacheKey, reply );
				}
				this.finishResponse( reply );
			} catch ( error ) {
				this.finishResponse( { message: this.strings.error || 'Não foi possível responder neste momento.' } );
			}
		}

		finishResponse( reply ) {
			const response = reply && typeof reply === 'object' ? reply : {};
			const message = typeof response.message === 'string' && response.message.trim()
				? response.message.trim()
				: ( this.strings.error || 'Não foi possível responder neste momento.' );

			this.removeTyping();
			if ( response.context && typeof response.context === 'object' ) {
				this.state.context = this.normalizeContext( response.context );
			}
			this.appendMessage( message, 'bot', {
				links: response.links,
				cards: response.cards,
				suggestions: response.suggestions,
			} );
			this.setBusy( false );

			if ( this.isOpen ) {
				this.input.focus( { preventScroll: true } );
			}
		}

		appendMessage( message, author, options = {}, persist = true ) {
			const item = document.createElement( 'div' );
			const content = document.createElement( 'div' );
			const label = document.createElement( 'span' );
			const bubble = document.createElement( 'div' );

			item.className = `adam-bot__message adam-bot__message--${ author }`;
			content.className = 'adam-bot__message-content';
			label.className = 'adam-bot__sr-only';
			label.textContent = author === 'user'
				? ( this.strings.userLabel || 'Você:' )
				: ( this.strings.assistantLabel || 'ADAM BOT:' );
			bubble.className = 'adam-bot__bubble';

			if ( author === 'bot' ) {
				const avatar = document.createElement( 'div' );
				avatar.className = 'adam-bot__avatar';
				avatar.setAttribute( 'aria-hidden', 'true' );
				avatar.textContent = 'A';
				item.appendChild( avatar );
				bubble.appendChild( this.markdown.render( message ) );
			} else {
				bubble.textContent = message;
			}

			content.append( label, bubble );
			this.appendCards( content, options.cards );
			this.appendLinks( content, options.links );
			this.appendSuggestions( content, options.suggestions );
			item.appendChild( content );
			this.messages.appendChild( item );

			if ( persist ) {
				this.state.messages.push( {
					author,
					message,
					links: this.normalizeLinks( options.links ),
					cards: this.normalizeCards( options.cards ),
					suggestions: this.normalizeSuggestions( options.suggestions ),
				} );
				this.state.messages = this.state.messages.slice( -MAX_MESSAGES );
				this.persistState();
			}

			this.scrollToLatest();
		}

		appendLinks( content, links ) {
			const safeLinks = this.normalizeLinks( links );
			if ( ! safeLinks.length ) {
				return;
			}

			const region = document.createElement( 'div' );
			const title = document.createElement( 'p' );
			region.className = 'adam-bot__related-pages';
			title.className = 'adam-bot__meta-title';
			title.textContent = this.strings.relatedPages || 'Páginas relacionadas';
			region.appendChild( title );

			safeLinks.forEach( ( linkData ) => {
				if ( linkData.kind === 'link' ) {
					const link = document.createElement( 'a' );
					link.className = 'adam-bot__resource-link';
					link.href = linkData.url;
					link.rel = 'noopener noreferrer';
					link.textContent = `${ linkData.label } →`;
					region.appendChild( link );
					return;
				}
				const card = document.createElement( 'div' );
				const text = document.createElement( 'span' );
				const link = document.createElement( 'a' );
				card.className = 'adam-bot__page-card';
				text.textContent = linkData.title;
				link.className = 'adam-bot__page-link';
				link.href = linkData.url;
				link.rel = 'noopener noreferrer';
				link.textContent = `${ linkData.label } →`;
				card.append( text, link );
				region.appendChild( card );
			} );

			content.appendChild( region );
		}

		appendCards( content, cards ) {
			const clean = this.normalizeCards( cards );
			if ( ! clean.length ) {
				return;
			}

			const region = document.createElement( 'div' );
			const title = document.createElement( 'p' );
			region.className = 'adam-bot__knowledge-cards';
			title.className = 'adam-bot__meta-title';
			title.textContent = clean[ 0 ].groupLabel || this.strings.results || 'Resultados';
			region.appendChild( title );

			clean.forEach( ( cardData ) => {
				const card = document.createElement( 'article' );
				const heading = document.createElement( 'h4' );
				card.className = `adam-bot__knowledge-card adam-bot__knowledge-card--${ cardData.type }`;
				if ( cardData.image ) {
					const image = document.createElement( 'img' );
					image.className = 'adam-bot__knowledge-card-image';
					image.src = cardData.image;
					image.alt = '';
					image.loading = 'lazy';
					card.appendChild( image );
				}
				heading.textContent = cardData.title;
				card.appendChild( heading );

				if ( cardData.description ) {
					const description = document.createElement( 'p' );
					description.textContent = cardData.description;
					card.appendChild( description );
				}

				if ( cardData.meta.length ) {
					const meta = document.createElement( 'ul' );
					meta.className = 'adam-bot__knowledge-card-meta';
					cardData.meta.forEach( ( value ) => {
						const item = document.createElement( 'li' );
						item.textContent = value;
						meta.appendChild( item );
					} );
					card.appendChild( meta );
				}

				if ( cardData.url ) {
					const link = document.createElement( 'a' );
					link.className = 'adam-bot__page-link';
					link.href = cardData.url;
					link.rel = 'noopener noreferrer';
					if ( cardData.download ) link.setAttribute( 'download', '' );
					link.textContent = `${ cardData.actionLabel } →`;
					card.appendChild( link );
				}

				cardData.actions.forEach( ( action ) => {
					const link = document.createElement( 'a' );
					link.className = 'adam-bot__page-link adam-bot__page-link--secondary';
					link.href = action.url;
					link.rel = 'noopener noreferrer';
					link.textContent = `${ action.label } →`;
					card.appendChild( link );
				} );

				region.appendChild( card );
			} );

			content.appendChild( region );
		}

		appendSuggestions( content, suggestions ) {
			const clean = this.normalizeSuggestions( suggestions );
			if ( ! clean.length ) {
				return;
			}

			const region = document.createElement( 'div' );
			const title = document.createElement( 'p' );
			const chips = document.createElement( 'div' );
			region.className = 'adam-bot__follow-ups';
			title.className = 'adam-bot__meta-title';
			title.textContent = this.strings.followUps || 'Também pode perguntar';
			chips.className = 'adam-bot__chips';

			clean.forEach( ( suggestion ) => {
				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'adam-bot__chip';
				button.textContent = `→ ${ suggestion.label }`;
				button.setAttribute( 'data-adam-message', suggestion.prompt );
				button.setAttribute( 'data-adam-action', suggestion.action );
				chips.appendChild( button );
			} );

			region.append( title, chips );
			content.appendChild( region );
		}

		normalizeLinks( links ) {
			if ( ! Array.isArray( links ) ) {
				return [];
			}

			return links.slice( 0, 4 ).reduce( ( clean, link ) => {
				const safeUrl = getSafeUrl( link && link.url );
				if ( ! safeUrl ) {
					return clean;
				}
				clean.push( {
					title: String( link.title || 'ADAM' ).slice( 0, 100 ),
					label: String( link.label || 'Saber mais' ).slice( 0, 50 ),
					url: safeUrl.href,
					kind: link && link.kind === 'link' ? 'link' : 'button',
				} );
				return clean;
			}, [] );
		}

		normalizeCards( cards ) {
			if ( ! Array.isArray( cards ) ) {
				return [];
			}

			return cards.slice( 0, 12 ).reduce( ( clean, card ) => {
				const title = String( card && card.title || '' ).trim().slice( 0, 100 );
				if ( ! title ) {
					return clean;
				}

				const safeUrl = getSafeUrl( card.url );
				const safeImage = getSafeUrl( card.image );
				const actions = Array.isArray( card.actions ) ? card.actions.slice( 0, 3 ).reduce( ( values, action ) => {
					const actionUrl = getSafeUrl( action && action.url );
					const actionLabel = String( action && action.label || '' ).trim().slice( 0, 50 );
					if ( actionUrl && actionLabel ) values.push( { label: actionLabel, url: actionUrl.href } );
					return values;
				}, [] ) : [];
				clean.push( {
					type: String( card.type || 'result' ).replace( /[^a-z0-9_-]/gi, '' ).slice( 0, 30 ) || 'result',
					groupLabel: String( card.groupLabel || this.strings.results || 'Resultados' ).slice( 0, 80 ),
					image: safeImage ? safeImage.href : '',
					title,
					description: String( card.description || '' ).trim().slice( 0, 220 ),
					meta: Array.isArray( card.meta ) ? card.meta.slice( 0, 8 ).map( ( value ) => String( value ).slice( 0, 100 ) ) : [],
					url: safeUrl ? safeUrl.href : '',
					actionLabel: String( card.actionLabel || this.strings.view || 'Ver' ).slice( 0, 50 ),
					actions,
					download: card.download === true,
				} );
				return clean;
			}, [] );
		}

		normalizeSuggestions( suggestions ) {
			if ( ! Array.isArray( suggestions ) ) {
				return [];
			}

			return suggestions.slice( 0, 4 ).reduce( ( clean, suggestion ) => {
				const label = String( suggestion && suggestion.label || '' ).trim().slice( 0, 100 );
				const prompt = String( suggestion && suggestion.prompt || '' ).trim().slice( 0, 4000 );
				if ( label && prompt ) {
					clean.push( { label, prompt, action: 'message' } );
				}
				return clean;
			}, [] );
		}

		showTyping() {
			const item = document.createElement( 'div' );
			const avatar = document.createElement( 'div' );
			const content = document.createElement( 'div' );
			const bubble = document.createElement( 'div' );
			const dots = document.createElement( 'span' );
			const typingText = this.strings.typing || 'ADAM BOT está a escrever';

			item.className = 'adam-bot__message adam-bot__message--bot adam-bot__typing';
			item.setAttribute( 'aria-hidden', 'true' );
			avatar.className = 'adam-bot__avatar';
			avatar.textContent = 'A';
			content.className = 'adam-bot__message-content';
			bubble.className = 'adam-bot__bubble adam-bot__typing-bubble';
			dots.className = 'adam-bot__typing-dots';
			dots.append( document.createElement( 'span' ), document.createElement( 'span' ), document.createElement( 'span' ) );
			bubble.appendChild( dots );
			content.appendChild( bubble );
			item.append( avatar, content );
			this.messages.appendChild( item );
			this.typingMessage = item;
			this.status.textContent = typingText;
			this.scrollToLatest();
		}

		removeTyping() {
			if ( this.typingMessage ) {
				this.typingMessage.remove();
				this.typingMessage = null;
			}
			this.status.textContent = '';
		}

		setBusy( busy ) {
			this.isBusy = busy;
			this.input.disabled = busy;
			this.messages.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
			this.updateComposer();
		}

		updateComposer() {
			this.sendButton.disabled = this.isBusy || ! this.input.value.trim();
		}

		resizeInput() {
			this.input.style.height = 'auto';
			const maxHeight = Number.parseFloat( window.getComputedStyle( this.input ).maxHeight ) || 132;
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

		hideStarters() {
			this.root.classList.add( 'has-messages' );
			if ( this.welcome ) {
				this.welcome.hidden = true;
			}
			if ( this.quickActions ) {
				this.quickActions.hidden = true;
			}
		}

		restoreConversation() {
			const hasMessages = this.state.messages.length > 0;
			const showWelcome = ! hasMessages && ! this.hasSeenWelcome();

			this.welcome.hidden = ! showWelcome;
			this.quickActions.hidden = hasMessages;

			if ( hasMessages ) {
				this.root.classList.add( 'has-messages' );
				this.state.messages.forEach( ( stored ) => {
					this.appendMessage( stored.message, stored.author, stored, false );
				} );
				window.setTimeout( () => {
					const restored = this.strings.restored || 'Conversa desta sessão restaurada.';
					this.status.textContent = restored;
					window.setTimeout( () => {
						if ( this.status.textContent === restored ) {
							this.status.textContent = '';
						}
					}, 1800 );
				}, 250 );
			}
		}

		getKnowledgeContext() {
			return this.normalizeContext( this.state.context );
		}

		normalizeContext( context ) {
			const allowedTopics = [ 'membership', 'events', 'rules', 'contact', 'airsoft', 'about' ];
			const topic = context && allowedTopics.includes( context.topic ) ? context.topic : '';
			const recentResultIds = context && Array.isArray( context.recentResultIds )
				? context.recentResultIds.slice( -5 ).filter( ( id ) => /^[a-f0-9]{32}$/i.test( String( id ) ) )
				: [];

			return { topic, recentResultIds };
		}

		readState() {
			const fallback = {
				conversationStarted: false,
				messages: [],
				cache: [],
				context: { topic: '', recentResultIds: [] },
			};
			try {
				const parsed = JSON.parse( window.sessionStorage.getItem( SESSION_KEY ) || 'null' );
				if ( ! parsed || ! Array.isArray( parsed.messages ) ) {
					return fallback;
				}
				return {
					conversationStarted: parsed.conversationStarted === true,
					messages: parsed.messages.slice( -MAX_MESSAGES ).filter( ( message ) => {
						return message && [ 'user', 'bot' ].includes( message.author ) && typeof message.message === 'string';
					} ),
					cache: Array.isArray( parsed.cache ) ? parsed.cache.slice( -MAX_CACHE_ENTRIES ) : [],
					context: this.normalizeContext( parsed.context ),
				};
			} catch ( error ) {
				return fallback;
			}
		}

		persistState() {
			try {
				window.sessionStorage.setItem( SESSION_KEY, JSON.stringify( this.state ) );
			} catch ( error ) {
				// Session storage can be unavailable or full in restricted contexts.
			}
		}

		createCacheKey( message, context ) {
			return JSON.stringify( {
				message: message.toLocaleLowerCase(),
				context: this.normalizeContext( context ),
			} );
		}

		getCachedResponse( key ) {
			const entry = this.state.cache.find( ( item ) => item && item.key === key );
			return entry && entry.payload ? entry.payload : null;
		}

		cacheResponse( key, payload ) {
			this.state.cache = this.state.cache.filter( ( item ) => item && item.key !== key );
			this.state.cache.push( { key, payload } );
			this.state.cache = this.state.cache.slice( -MAX_CACHE_ENTRIES );
			this.persistState();
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

		hasSeenWelcome() {
			try {
				return window.localStorage.getItem( WELCOME_KEY ) === '1';
			} catch ( error ) {
				return false;
			}
		}

		markWelcomeSeen() {
			try {
				window.localStorage.setItem( WELCOME_KEY, '1' );
			} catch ( error ) {
				// The introduction remains harmless if persistent storage is blocked.
			}
		}

		updateViewportHeight() {
			const height = window.visualViewport ? window.visualViewport.height : window.innerHeight;
			this.root.style.setProperty( '--adam-viewport-height', `${ Math.round( height ) }px` );
		}
	}

	function getSafeUrl( value ) {
		if ( typeof value !== 'string' || ! value.trim() ) {
			return null;
		}

		try {
			const url = new URL( value, window.location.href );
			return [ 'http:', 'https:' ].includes( url.protocol ) ? url : null;
		} catch ( error ) {
			return null;
		}
	}

	function start() {
		const root = document.querySelector( '[data-adam-bot]' );
		if ( ! root ) {
			return;
		}

		const settings = window.adamBotSettings || {};
		new ChatWidget( root, new ChatApi( settings ), settings ).init();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
}() );
