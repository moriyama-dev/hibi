// Loaded on demand: only the reading view needs it.
import DOMPurify from 'dompurify';
import { Marked, type Tokens } from 'marked';

const PHOTO_TOKEN = /^hibi-photo:(\d+)$/;

function escapeHtml( text: string ): string {
	return text.replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] as string );
}

let photoUrl: ( id: number ) => string = () => '';

const marked = new Marked( {
	gfm: true,
	breaks: true,
	renderer: {
		// Raw HTML typed in an entry is shown as text, never run.
		html( token: Tokens.HTML | Tokens.Tag ) {
			return escapeHtml( token.text );
		},
		// Only the entry's own photos are shown. Remote images would tell
		// another server when the journal is read.
		image( token: Tokens.Image ) {
			const m = PHOTO_TOKEN.exec( token.href );
			if ( ! m ) {
				return escapeHtml( token.text );
			}
			return `<img src="${ escapeHtml( photoUrl( +m[ 1 ] ) ) }" alt="${ escapeHtml( token.text ) }" loading="lazy">`;
		},
		link( token: Tokens.Link ) {
			const text = this.parser.parseInline( token.tokens );
			if ( ! /^https?:\/\//i.test( token.href ) ) {
				return text;
			}
			return `<a href="${ escapeHtml( token.href ) }" target="_blank" rel="noopener noreferrer">${ text }</a>`;
		},
		checkbox( token: Tokens.Checkbox ) {
			return `<input type="checkbox" disabled${ token.checked ? ' checked' : '' }> `;
		},
	},
} );

DOMPurify.addHook( 'afterSanitizeAttributes', ( node ) => {
	if ( node.tagName === 'A' ) {
		node.setAttribute( 'rel', 'noopener noreferrer' );
		node.setAttribute( 'target', '_blank' );
	}
} );

export function renderMarkdown( text: string, photoSrc: ( id: number ) => string ): string {
	photoUrl = photoSrc;
	// Neutralise HTML before parsing: a line starting with "<" would
	// otherwise open an HTML block that swallows the lines after it.
	const source = text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' );
	const html = marked.parse( source, { async: false } ) as string;
	return DOMPurify.sanitize( html, {
		ALLOWED_TAGS: [ 'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'del', 'ul', 'ol', 'li', 'blockquote', 'hr', 'a', 'img', 'input', 'code', 'pre' ],
		ALLOWED_ATTR: [ 'href', 'src', 'alt', 'loading', 'type', 'checked', 'disabled', 'target', 'rel' ],
		ALLOWED_URI_REGEXP: /^(?:https?:|\/|\?|\.\/)/i,
		// Not URLs: without this the URI pattern above would drop them.
		ADD_URI_SAFE_ATTR: [ 'type', 'loading', 'target', 'rel' ],
	} );
}
