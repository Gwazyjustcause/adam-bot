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
					const labels = { warning: 'Aviso', information: 'Informação', success: 'Sucesso' };
					const type = callout[ 1 ].toLowerCase();
					element.className = `adam-bot__callout adam-bot__callout--${ type }`;
					label.textContent = `${ labels[ type ] || 'Informação' }: `;
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
			this.lastUserMessage = '';
			this.conversationGeneration = 0;
			this.currentLanguage = this.getDefaultLanguage();
			this.state = this.readState();
			this.currentLanguage = this.state.context.language || this.currentLanguage;
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
			this.homeButtons = this.root.querySelectorAll( '[data-adam-home]' );
			this.newConversationButton = this.root.querySelector( '[data-adam-new-conversation]' );
			this.backdrop = this.root.querySelector( '[data-adam-backdrop]' );
			this.conversation = this.root.querySelector( '[data-adam-conversation]' );
			this.messages = this.root.querySelector( '[data-adam-messages]' );
			this.welcome = this.root.querySelector( '[data-adam-welcome]' );
			this.quickActions = this.root.querySelector( '[data-adam-quick-actions]' );
			this.form = this.root.querySelector( '[data-adam-form]' );
			this.input = this.root.querySelector( '[data-adam-input]' );
			this.sendButton = this.root.querySelector( '[data-adam-send]' );
			this.status = this.root.querySelector( '[data-adam-status]' );
			this.topics = this.root.querySelector( '[data-adam-topics]' );
			this.toolbar = this.root.querySelector( '[data-adam-toolbar]' );
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
			document.documentElement.classList.add( 'adam-bot-dialog-open' );
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
			document.documentElement.classList.remove( 'adam-bot-dialog-open' );
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
			const home = event.target.closest( '[data-adam-home]' );
			if ( home && this.root.contains( home ) ) {
				this.resetConversation( 'home' );
				return;
			}

			const newConversation = event.target.closest( '[data-adam-new-conversation]' );
			if ( newConversation && this.root.contains( newConversation ) ) {
				this.resetConversation( 'input' );
				return;
			}

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
			this.lastUserMessage = message;
			const context = this.getKnowledgeContext();
			const generation = this.conversationGeneration;
			this.currentLanguage = this.detectLanguage( message );
			this.updateLanguage( this.currentLanguage );
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
					const cacheableReply = reply && typeof reply === 'object' ? { ...reply } : reply;
					if ( cacheableReply && typeof cacheableReply === 'object' ) delete cacheableReply.debug;
					this.cacheResponse( cacheKey, cacheableReply );
				}
				if ( generation === this.conversationGeneration ) {
					this.finishResponse( reply );
				}
			} catch ( error ) {
				if ( generation === this.conversationGeneration ) {
					this.finishResponse( { message: this.strings.error || 'Não foi possível responder neste momento.' } );
				}
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
			} else {
				this.state.context = { ...this.getKnowledgeContext(), language: this.currentLanguage };
			}
			this.currentLanguage = this.state.context.language || this.currentLanguage;
			this.updateLanguage( this.currentLanguage );
			const suggestions = this.buildResponseSuggestions( response.suggestions );
			this.appendMessage( message, 'bot', {
				links: response.links,
				cards: response.cards,
				suggestions,
				language: this.currentLanguage,
				debug: response.debug,
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
			this.appendSuggestions( content, options.suggestions, options.language || this.currentLanguage );
			this.appendDebug( content, options.debug );
			item.appendChild( content );
			this.messages.appendChild( item );

			if ( persist ) {
				this.state.messages.push( {
					author,
					message,
					links: this.normalizeLinks( options.links ),
					cards: this.normalizeCards( options.cards ),
					suggestions: this.normalizeSuggestions( options.suggestions ),
					language: options.language === 'en' ? 'en' : 'pt',
				} );
				this.state.messages = this.state.messages.slice( -MAX_MESSAGES );
				this.persistState();
			}

			this.scrollToLatest();
		}

		appendDebug( content, debug ) {
			if ( ! debug || typeof debug !== 'object' ) return;
			const region = document.createElement( 'details' );
			const summary = document.createElement( 'summary' );
			const list = document.createElement( 'dl' );
			const rows = [
				[ this.strings.debugProvider || 'Fornecedor', debug.provider ],
				[ this.strings.debugIntent || 'Intenção', debug.intent ],
				[ this.strings.debugScore || 'Pontuação', debug.score ],
				[ this.strings.debugKeywords || 'Palavras correspondentes', Array.isArray( debug.matchedKeywords ) ? debug.matchedKeywords.join( ', ' ) : '' ],
				[ this.strings.debugConfidence || 'Confiança', debug.confidence ],
				[ this.strings.debugTime || 'Tempo do fornecedor', debug.providerTimeMs ],
				[ this.strings.debugFallback || 'Fornecedor alternativo', debug.fallbackProvider ],
			];
			region.className = 'adam-bot__debug';
			summary.textContent = this.strings.debugSummary || 'Diagnóstico da pesquisa';
			rows.forEach( ( [ label, value ] ) => {
				if ( value === '' || value === null || typeof value === 'undefined' ) return;
				const term = document.createElement( 'dt' );
				const description = document.createElement( 'dd' );
				term.textContent = label;
				description.textContent = String( value );
				list.append( term, description );
			} );
			region.append( summary, list );
			content.appendChild( region );
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
			region.setAttribute( 'role', 'group' );
			region.setAttribute( 'aria-label', title.textContent );
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
					image.decoding = 'async';
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

		appendSuggestions( content, suggestions, language = 'pt' ) {
			const clean = this.normalizeSuggestions( suggestions );
			if ( ! clean.length ) {
				return;
			}

			const region = document.createElement( 'div' );
			const title = document.createElement( 'p' );
			const chips = document.createElement( 'div' );
			region.className = 'adam-bot__follow-ups';
			title.className = 'adam-bot__meta-title';
			title.textContent = language === 'en'
				? ( this.strings.followUpsEn || 'You may also be looking for:' )
				: ( this.strings.followUps || 'Também poderá estar à procura de:' );
			chips.className = 'adam-bot__chips';
			region.setAttribute( 'role', 'group' );
			region.setAttribute( 'aria-label', title.textContent );

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

			return suggestions.slice( 0, 6 ).reduce( ( clean, suggestion ) => {
				const label = String( suggestion && suggestion.label || '' ).trim().slice( 0, 100 );
				const prompt = String( suggestion && suggestion.prompt || '' ).trim().slice( 0, 4000 );
				if ( label && prompt ) {
					clean.push( { label, prompt, action: 'message' } );
				}
				return clean;
			}, [] );
		}

		buildResponseSuggestions( suggestions ) {
			const combined = this.normalizeSuggestions( suggestions );
			const seen = new Set( combined.map( ( item ) => item.prompt.toLocaleLowerCase() ) );
			if ( this.lastUserMessage ) seen.add( this.lastUserMessage.toLocaleLowerCase() );
			const add = ( label, prompt ) => {
				const cleanLabel = String( label || '' ).trim();
				const cleanPrompt = String( prompt || '' ).trim();
				const key = cleanPrompt.toLocaleLowerCase();
				if ( cleanLabel && cleanPrompt && ! seen.has( key ) && combined.length < 6 ) {
					seen.add( key );
					combined.push( { label: cleanLabel, prompt: cleanPrompt, action: 'message' } );
				}
			};

			const addTopics = () => {
				if ( ! this.topics ) return;
				this.topics.querySelectorAll( '[data-adam-topic]' ).forEach( ( topic ) => {
					const language = this.currentLanguage === 'en' ? 'en' : 'pt';
					add( topic.getAttribute( `data-adam-label-${ language }` ), topic.getAttribute( `data-adam-prompt-${ language }` ) );
				} );
			};
			const quickActions = Array.isArray( this.settings.quickActions ) ? this.settings.quickActions : [];
			if ( this.currentLanguage === 'en' ) {
				addTopics();
			} else {
				quickActions.forEach( ( action ) => add( action && action.label, action && action.prompt ) );
				if ( combined.length < 3 ) addTopics();
			}

			return combined.slice( 0, 6 );
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

		resetConversation( focusTarget = 'home' ) {
			this.conversationGeneration += 1;
			this.removeTyping();
			this.isBusy = false;
			this.messages.textContent = '';
			this.input.value = '';
			this.lastSubmission = { message: '', time: 0 };
			this.lastUserMessage = '';
			this.currentLanguage = this.getDefaultLanguage();
			this.state = {
				conversationStarted: false,
				messages: [],
				cache: Array.isArray( this.state.cache ) ? this.state.cache : [],
				context: { topic: '', recentResultIds: [], language: this.currentLanguage },
			};
			this.root.classList.remove( 'has-messages' );
			this.welcome.hidden = false;
			this.quickActions.hidden = false;
			this.updateLanguage( this.currentLanguage );
			this.resizeInput();
			this.setBusy( false );
			this.persistState();
			this.conversation.scrollTo( { top: 0, behavior: 'auto' } );
			this.status.textContent = this.currentLanguage === 'en'
				? ( this.strings.homeRestoredEn || 'Home screen restored.' )
				: ( this.strings.homeRestored || 'Ecrã inicial reposto.' );
			window.setTimeout( () => {
				if ( ! this.isOpen ) return;
				if ( focusTarget === 'input' ) {
					this.input.focus( { preventScroll: true } );
				} else {
					const firstAction = this.quickActions.querySelector( 'button' );
					( firstAction || this.input ).focus( { preventScroll: true } );
				}
			}, 0 );
		}

		restoreConversation() {
			const hasMessages = this.state.messages.length > 0;
			const showWelcome = ! hasMessages;

			this.welcome.hidden = ! showWelcome;
			this.quickActions.hidden = hasMessages;
			this.updateLanguage( this.currentLanguage );

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
			const candidateTopic = String( context && context.topic || '' ).slice( 0, 64 );
			const topic = /^[a-z0-9_-]+$/i.test( candidateTopic ) ? candidateTopic.toLowerCase() : '';
			const language = context && context.language === 'en' ? 'en' : ( context && context.language === 'pt' ? 'pt' : this.currentLanguage );
			const recentResultIds = context && Array.isArray( context.recentResultIds )
				? context.recentResultIds.slice( -5 ).filter( ( id ) => /^[a-f0-9]{32}$/i.test( String( id ) ) )
				: [];

			return { topic, recentResultIds, language };
		}

		updateLanguage( language ) {
			const isEnglish = language === 'en';
			this.currentLanguage = isEnglish ? 'en' : 'pt';
			if ( this.topics ) {
				const title = this.topics.querySelector( '[data-adam-topics-title]' );
				if ( title ) title.textContent = isEnglish ? ( this.strings.browseTopicsEn || 'Browse Topics' ) : ( this.strings.browseTopics || 'Explorar temas' );
				this.topics.querySelectorAll( '[data-adam-topic]' ).forEach( ( topic ) => {
					const suffix = isEnglish ? 'en' : 'pt';
					topic.textContent = topic.getAttribute( `data-adam-label-${ suffix }` ) || topic.textContent;
					topic.setAttribute( 'data-adam-message', topic.getAttribute( `data-adam-prompt-${ suffix }` ) || '' );
				} );
			}
			this.root.querySelectorAll( '[data-adam-home-label]' ).forEach( ( label ) => { label.textContent = isEnglish ? 'Home' : 'Início'; } );
			this.root.querySelectorAll( '[data-adam-new-label]' ).forEach( ( label ) => { label.textContent = isEnglish ? 'New Conversation' : 'Nova conversa'; } );
			this.homeButtons.forEach( ( button ) => { button.setAttribute( 'aria-label', isEnglish ? 'Return to home' : 'Voltar ao início' ); } );
			const headerHome = this.root.querySelector( '.adam-bot__header-home' );
			if ( headerHome ) headerHome.title = isEnglish ? 'Home' : 'Início';
			if ( this.newConversationButton ) this.newConversationButton.setAttribute( 'aria-label', isEnglish ? 'Start a new conversation' : 'Iniciar uma nova conversa' );
			if ( this.toolbar ) this.toolbar.setAttribute( 'aria-label', isEnglish ? 'Conversation controls' : 'Controlos da conversa' );
			if ( this.closeButton ) this.closeButton.setAttribute( 'aria-label', isEnglish ? 'Close conversation' : 'Fechar conversa' );
			if ( this.input ) {
				this.input.placeholder = isEnglish ? ( this.strings.inputPlaceholderEn || 'Ask ADAM BOT…' ) : ( this.strings.inputPlaceholder || 'Pergunte ao ADAM BOT…' );
				this.input.setAttribute( 'aria-label', isEnglish ? ( this.strings.inputLabelEn || 'Message for ADAM BOT' ) : ( this.strings.inputLabel || 'Mensagem para o ADAM BOT' ) );
			}
			if ( this.sendButton ) this.sendButton.setAttribute( 'aria-label', isEnglish ? ( this.strings.sendLabelEn || 'Send message' ) : ( this.strings.sendLabel || 'Enviar mensagem' ) );
		}

		getDefaultLanguage() {
			const language = String( document.documentElement.lang || '' ).toLowerCase();
			return language.startsWith( 'en' ) ? 'en' : 'pt';
		}

		detectLanguage( message ) {
			const normalized = String( message || '' ).toLocaleLowerCase();
			const portuguese = /[ãõçáàâéêíóôú]|\b(como|onde|quando|quanto|quais|sócio|socios|sócios|quota|equipa|campo|parceiro|contactar)\b/u.test( normalized );
			const english = /\b(what|where|when|how|which|member|membership|team|field|partner|contact|event)\b/u.test( normalized );
			if ( portuguese ) return 'pt';
			if ( english ) return 'en';
			return this.currentLanguage || this.getDefaultLanguage();
		}

		readState() {
			const fallback = {
				conversationStarted: false,
				messages: [],
				cache: [],
				context: { topic: '', recentResultIds: [], language: this.currentLanguage },
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
