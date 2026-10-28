/* Run with node tests/guided-website.js. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
class Element {
	constructor( tag ) { this.tag = tag; this.children = []; this.listeners = {}; }
	appendChild( child ) { this.children.push( child ); }
	addEventListener( event, listener ) { this.listeners[ event ] = listener; }
}
const document = { readyState: 'loading', addEventListener() {}, createElement: ( tag ) => new Element( tag ) };
const window = { location: { origin: 'https://example.test' } };
let source = fs.readFileSync( require( 'node:path' ).join( __dirname, '../assets/js/adam-bot-guided.js' ), 'utf8' );
source = source.replace( 'function start()', 'window.TestWidget = GuidedWidget; function start()' );
vm.runInNewContext( source, { document, window, URL } );
const events = [];
const widget = new window.TestWidget( { querySelector: () => null }, { track: ( ...event ) => events.push( event ) }, {} );
widget.stage = new Element( 'div' );
widget.currentId = 2;
widget.renderWebsiteItems( [
	{ title: 'CAT <script>', details: 'Soure · A recrutar', description: 'Equipa focada em CQB.', url: 'https://example.test/equipas/cat/', button: 'Ver equipa' },
	{ title: 'Invalid', url: 'javascript:alert(1)', button: 'Invalid' },
	{ title: 'External', url: 'https://other.test/team/', button: 'External' },
] );
const cards = widget.stage.children[ 0 ].children;
assert.equal( cards[ 0 ].children[ 0 ].textContent, 'CAT <script>' );
assert.equal( cards[ 0 ].children[ 2 ].textContent, 'Equipa focada em CQB.' );
const links = cards.flatMap( ( card ) => card.children.filter( ( child ) => child.tag === 'a' ) );
assert.equal( links.length, 1 );
assert.equal( links[ 0 ].href, 'https://example.test/equipas/cat/' );
assert.equal( links[ 0 ].target, undefined );
assert.equal( events.length, 0 ); // Rendering never opens or activates a destination.
links[ 0 ].listeners.click();
assert.equal( events.length, 1 );
console.log( 'PASS: team descriptions, text rendering, canonical links and click handling.' );
