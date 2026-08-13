/**
 * ADAM BOT guided assistant frontend.
 */
( function () {
	'use strict';

	const STATE_KEY = 'adamBotGuidedNavigationV1';
	const MAX_HISTORY = 20;

	class GuidedApi {
		constructor( settings ) {
			this.settings = settings || {};
			this.endpoint = settings && typeof settings.guidedUrl === 'string' ? settings.guidedUrl : '';
		}

		async get( id = 0 ) {
			if ( ! this.endpoint ) throw new Error( 'Missing guided REST endpoint.' );
			const url = id > 0 ? `${ this.endpoint.replace( /\/$/, '' ) }/${ id }` : this.endpoint;
			const response = await window.fetch( url, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } } );
			let payload;
			try { payload = await response.json(); } catch ( error ) { throw new Error( 'Invalid guided REST response.' ); }
			if ( ! response.ok || ! payload || typeof payload !== 'object' ) throw new Error( 'Unsuccessful guided REST response.' );
			return payload;
		}

		track( event, data = {} ) {
			const endpoint = this.settings && typeof this.settings.guidedEventsUrl === 'string' ? this.settings.guidedEventsUrl : '';
			if ( ! endpoint || ! navigator.sendBeacon ) return;
			try { navigator.sendBeacon( endpoint, new Blob( [ JSON.stringify( { event, node_id: Number( data.nodeId || 0 ), target_id: Number( data.targetId || 0 ), action_type: String( data.actionType || '' ).slice( 0, 30 ) } ) ], { type: 'application/json' } ) ); } catch ( error ) {}
		}
	}

	class GuidedWidget {
		constructor( root, api, settings ) {
			this.root = root;
			this.api = api;
			this.settings = settings || {};
			this.strings = this.settings.strings || {};
			this.launcher = root.querySelector( '[data-adam-launcher]' );
			this.template = root.querySelector( '[data-adam-template]' );
			this.isHydrated = false;
			this.isOpen = false;
			this.loading = false;
			this.currentId = 0;
			this.history = [];
			this.currentNode = null;
		}

		init() {
			if ( ! this.launcher || ! this.template ) return;
			this.launcher.addEventListener( 'click', () => this.open() );
			document.addEventListener( 'keydown', ( event ) => this.handleDocumentKeydown( event ) );
			window.addEventListener( 'pagehide', () => this.persistState() );
			window.requestAnimationFrame( () => { this.root.classList.add( 'is-ready' ); } );
		}

		hydrate() {
			if ( this.isHydrated ) return;
			this.root.appendChild( this.template.content.cloneNode( true ) );
			this.panel = this.root.querySelector( '[data-adam-panel]' );
			this.closeButton = this.root.querySelector( '[data-adam-close]' );
			this.backdrop = this.root.querySelector( '[data-adam-backdrop]' );
			this.conversation = this.root.querySelector( '[data-adam-conversation]' );
			this.stage = this.root.querySelector( '[data-guided-stage]' );
			this.status = this.root.querySelector( '[data-guided-status]' );
			this.backButton = this.root.querySelector( '[data-guided-back]' );
			this.panel.setAttribute( 'inert', '' );
			this.closeButton.addEventListener( 'click', () => this.close() );
			this.backdrop.addEventListener( 'click', () => this.close() );
			this.root.addEventListener( 'click', ( event ) => this.handleAction( event ) );
			if ( window.visualViewport ) {
				window.visualViewport.addEventListener( 'resize', () => this.updateViewportHeight() );
				window.visualViewport.addEventListener( 'scroll', () => this.updateViewportHeight() );
			}
			this.readState();
			this.isHydrated = true;
			this.updateViewportHeight();
		}

		open() {
			if ( this.isOpen ) return;
			this.hydrate();
			this.isOpen = true;
			this.panel.removeAttribute( 'inert' );
			this.panel.setAttribute( 'aria-hidden', 'false' );
			this.launcher.setAttribute( 'aria-expanded', 'true' );
			this.root.classList.add( 'is-open', 'has-interacted' );
			document.documentElement.classList.add( 'adam-bot-dialog-open' );
			if ( this.currentNode ) this.render( this.currentNode ); else this.load( this.currentId );
			window.setTimeout( () => { const focusTarget = this.stage.querySelector( 'button, a[href]' ); if ( focusTarget && this.isOpen ) focusTarget.focus( { preventScroll: true } ); }, 180 );
		}

		close( restoreFocus = true ) {
			if ( ! this.isOpen ) return;
			this.isOpen = false;
			this.persistState();
			this.root.classList.remove( 'is-open' );
			document.documentElement.classList.remove( 'adam-bot-dialog-open' );
			this.panel.setAttribute( 'aria-hidden', 'true' );
			this.panel.setAttribute( 'inert', '' );
			this.launcher.setAttribute( 'aria-expanded', 'false' );
			if ( restoreFocus ) this.launcher.focus( { preventScroll: true } );
		}

		handleAction( event ) {
			const target = event.target.closest( '[data-guided-node], [data-guided-home], [data-guided-back], [data-guided-reset]' );
			if ( ! target || ! this.root.contains( target ) || this.loading ) return;
			if ( target.hasAttribute( 'data-guided-home' ) || target.hasAttribute( 'data-guided-reset' ) ) { this.api.track( 'home', { nodeId: this.currentId } ); this.goHome(); return; }
			if ( target.hasAttribute( 'data-guided-back' ) ) { this.api.track( 'back', { nodeId: this.currentId } ); this.goBack(); return; }
			const id = Number.parseInt( target.getAttribute( 'data-guided-node' ) || '0', 10 );
			if ( id > 0 ) this.load( id, true );
		}

		handleDocumentKeydown( event ) {
			if ( ! this.isOpen || ! this.isHydrated ) return;
			if ( event.key === 'Escape' ) { event.preventDefault(); this.close(); return; }
			if ( event.key === 'Tab' ) this.keepFocusInsidePanel( event );
		}

		keepFocusInsidePanel( event ) {
			const focusable = Array.from( this.panel.querySelectorAll( 'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])' ) ).filter( ( element ) => element.getClientRects().length > 0 );
			if ( ! focusable.length ) return;
			const first = focusable[ 0 ]; const last = focusable[ focusable.length - 1 ];
			if ( event.shiftKey && ( document.activeElement === first || ! this.panel.contains( document.activeElement ) ) ) { event.preventDefault(); last.focus(); }
			else if ( ! event.shiftKey && ( document.activeElement === last || ! this.panel.contains( document.activeElement ) ) ) { event.preventDefault(); first.focus(); }
		}

		async load( id = 0, push = false ) {
			if ( this.loading ) return;
			this.loading = true;
			this.stage.setAttribute( 'aria-busy', 'true' );
			this.renderLoading();
			try {
				const node = await this.api.get( id );
				if ( push && this.currentId !== id ) this.history = [ ...this.history, this.currentId ].slice( -MAX_HISTORY );
				this.currentId = id;
				this.currentNode = node;
				this.api.track( 'view', { nodeId: id } );
				this.persistState();
				this.render( node );
			} catch ( error ) {
				this.renderError();
			} finally {
				this.loading = false;
				this.stage.setAttribute( 'aria-busy', 'false' );
				this.updateNavigation();
			}
		}

		goBack() { const id = this.history.pop(); if ( typeof id === 'number' ) this.load( id, false ); }

		goHome() { this.history = []; this.currentId = 0; this.currentNode = null; this.load( 0, false ); }

		render( node ) {
			this.stage.textContent = '';
			const heading = document.createElement( 'div' ); heading.className = 'adam-bot__guided-heading';
			if ( node.icon ) { const icon = document.createElement( 'span' ); icon.className = 'adam-bot__guided-icon'; icon.setAttribute( 'aria-hidden', 'true' ); icon.textContent = node.icon; heading.appendChild( icon ); }
			const title = document.createElement( 'h3' ); title.textContent = node.id === 0 ? ( this.strings.guidedWelcome || 'Olá! 👋 Em que podemos ajudar?' ) : String( node.label || '' ); heading.appendChild( title ); this.stage.appendChild( heading );
			if ( node.intro ) { const intro = document.createElement( 'p' ); intro.className = 'adam-bot__guided-intro'; intro.textContent = node.intro; this.stage.appendChild( intro ); }
			if ( node.direct_answer ) { const answer = document.createElement( 'div' ); answer.className = 'adam-bot__guided-direct-answer'; answer.textContent = node.direct_answer; this.stage.appendChild( answer ); }
			if ( Array.isArray( node.blocks ) && node.blocks.length ) this.renderBlocks( node.blocks );
			else if ( node.content ) this.renderContent( node.content );
			if ( node.type === 'dynamic' && ! node.content ) { const note = document.createElement( 'p' ); note.className = 'adam-bot__guided-note'; note.textContent = this.strings.guidedPreparing || 'Esta informação está a ser preparada.'; this.stage.appendChild( note ); }
			if ( Array.isArray( node.children ) && node.children.length ) this.renderChoices( node.children );
			if ( Array.isArray( node.actions ) && node.actions.length ) this.renderActions( node.actions );
			if ( node.id === 0 && ( ! Array.isArray( node.children ) || ! node.children.length ) ) { const empty = document.createElement( 'p' ); empty.className = 'adam-bot__guided-note'; empty.textContent = this.strings.guidedEmpty || 'A estrutura guiada ainda está a ser preparada.'; this.stage.appendChild( empty ); }
			this.updateNavigation();
			window.requestAnimationFrame( () => this.conversation.scrollTo( { top: 0, behavior: 'auto' } ) );
		}

		renderChoices( choices ) {
			const region = document.createElement( 'div' ); region.className = 'adam-bot__guided-choices';
			const label = document.createElement( 'h4' ); label.textContent = this.strings.guidedChoices || 'Escolha uma opção'; region.appendChild( label );
			choices.forEach( ( choice ) => { const button = document.createElement( 'button' ); button.type = 'button'; button.className = 'adam-bot__guided-choice'; button.setAttribute( 'data-guided-node', String( choice.id ) ); if ( choice.icon ) { const icon = document.createElement( 'span' ); icon.className = 'adam-bot__guided-choice-icon'; icon.setAttribute( 'aria-hidden', 'true' ); icon.textContent = choice.icon; button.appendChild( icon ); } const text = document.createElement( 'span' ); text.textContent = choice.label; button.appendChild( text ); const arrow = document.createElement( 'span' ); arrow.className = 'adam-bot__guided-arrow'; arrow.setAttribute( 'aria-hidden', 'true' ); arrow.textContent = '→'; button.appendChild( arrow ); region.appendChild( button ); } );
			this.stage.appendChild( region );
		}

		renderActions( actions ) {
			const region = document.createElement( 'div' ); region.className = 'adam-bot__guided-actions';
			const label = document.createElement( 'h4' ); label.textContent = this.strings.guidedNext || 'Próximos passos'; region.appendChild( label );
			actions.forEach( ( action ) => { const destination = action.destination || {}; let element; if ( destination.type === 'node' && Number( destination.id ) > 0 ) { element = document.createElement( 'button' ); element.type = 'button'; element.setAttribute( 'data-guided-node', String( destination.id ) ); } else if ( destination.url ) { element = document.createElement( 'a' ); element.href = destination.url; element.target = destination.type === 'url' ? '_blank' : '_self'; element.rel = destination.type === 'url' ? 'noopener noreferrer' : ''; } else return; element.className = `adam-bot__guided-action${ action.primary ? ' adam-bot__guided-action--primary' : '' }`; element.textContent = `${ action.label } →`; element.addEventListener( 'click', () => this.api.track( 'action', { nodeId: this.currentId, targetId: destination.id || 0, actionType: destination.type } ) ); region.appendChild( element ); } );
			this.stage.appendChild( region );
		}

		renderContent( content ) { String( content ).split(/\n+/).map( ( line ) => line.trim() ).filter( Boolean ).forEach( ( line ) => { const paragraph = document.createElement( 'p' ); paragraph.textContent = line.replace( /^[-*+]\s+/, '• ' ); this.stage.appendChild( paragraph ); } ); }
		renderBlocks( blocks ) { blocks.forEach( ( block ) => { if ( ! block || typeof block !== 'object' || ! block.text ) return; const type = String( block.type || 'paragraph' ); let element; if ( type === 'heading' ) element = document.createElement( 'h4' ); else if ( type === 'bullet_list' || type === 'numbered_list' ) { element = document.createElement( type === 'numbered_list' ? 'ol' : 'ul' ); String( block.text ).split(/\n+/).map( ( item ) => item.replace( /^\s*[-*+\d.)]+\s*/, '' ).trim() ).filter( Boolean ).forEach( ( item ) => { const li = document.createElement( 'li' ); li.textContent = item; element.appendChild( li ); } ); } else { element = document.createElement( 'p' ); element.textContent = String( block.text ); } element.className = `adam-bot__guided-block adam-bot__guided-block--${ type.replace( /[^a-z0-9_-]/gi, '' ) }`; this.stage.appendChild( element ); } ); }
		renderLoading() { this.stage.textContent = ''; const loading = document.createElement( 'p' ); loading.className = 'adam-bot__guided-loading'; loading.textContent = this.strings.guidedLoading || 'A carregar…'; this.stage.appendChild( loading ); }
	renderError() { this.stage.textContent = ''; const error = document.createElement( 'p' ); error.className = 'adam-bot__guided-error'; error.textContent = this.strings.guidedError || 'Não foi possível carregar esta opção. Tente novamente.'; this.stage.appendChild( error ); }

		updateNavigation() { if ( this.backButton ) this.backButton.disabled = this.loading || this.history.length === 0; }
		readState() { try { const stored = JSON.parse( window.sessionStorage.getItem( STATE_KEY ) || 'null' ); this.currentId = Number.isInteger( stored && stored.currentId ) ? stored.currentId : 0; this.history = Array.isArray( stored && stored.history ) ? stored.history.filter( ( id ) => Number.isInteger( id ) && id >= 0 ).slice( -MAX_HISTORY ) : []; } catch ( error ) { this.currentId = 0; this.history = []; } }
		persistState() { try { window.sessionStorage.setItem( STATE_KEY, JSON.stringify( { currentId: this.currentId, history: this.history } ) ); } catch ( error ) {} }
		updateViewportHeight() { const height = window.visualViewport ? window.visualViewport.height : window.innerHeight; this.root.style.setProperty( '--adam-viewport-height', `${ Math.round( height ) }px` ); }
	}

	function start() { const root = document.querySelector( '[data-adam-guided]' ); if ( ! root ) return; const settings = window.adamBotSettings || {}; new GuidedWidget( root, new GuidedApi( settings ), settings ).init(); }
	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } ); else start();
}() );
