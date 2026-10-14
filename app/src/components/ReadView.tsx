import { useEffect, useState } from 'preact/hooks';
import { boot } from '../store';
import type { Entry } from '../types';

const home = boot.api.home_path;
const photoSrc = ( id: number ) => `${ home }?hibi_photo=${ id }&size=medium`;

let renderer: Promise<typeof import( '../markdown' )> | null = null;

/** Formatted, read-only view of an entry. */
export function ReadView( { entry }: { entry: Entry } ) {
	const [ html, setHtml ] = useState<string | null>( null );

	useEffect( () => {
		let alive = true;
		renderer ??= import( '../markdown' );
		renderer.then( ( m ) => alive && setHtml( m.renderMarkdown( entry.content, photoSrc ) ) );
		return () => {
			alive = false;
		};
	}, [ entry.content ] );

	if ( ! entry.content.trim() ) {
		return <p class="read-empty">この日はまだ何も書かれていません。</p>;
	}
	if ( html === null ) {
		// Plain text until the formatter has loaded; nothing jumps.
		return <div class="read-body is-plain">{ entry.content }</div>;
	}
	// The HTML is produced by renderMarkdown, which escapes raw HTML and runs DOMPurify.
	return <div class="read-body" dangerouslySetInnerHTML={ { __html: html } } />;
}
