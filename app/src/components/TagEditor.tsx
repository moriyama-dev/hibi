import { useEffect, useState } from 'preact/hooks';
import { api } from '../api';

let suggestions: Promise<string[]> | null = null;

/** Tag names the writer has used, fetched once per page load. */
function knownTags(): Promise<string[]> {
	suggestions ??= api
		.tags()
		.then( ( list ) => list.map( ( t ) => t.name ) )
		.catch( () => {
			suggestions = null;
			return [];
		} );
	return suggestions;
}

interface Props {
	tags: string[];
	onChange: ( tags: string[] ) => void;
}

export function TagEditor( { tags, onChange }: Props ) {
	const [ text, setText ] = useState( '' );
	const [ known, setKnown ] = useState<string[]>( [] );

	useEffect( () => {
		knownTags().then( setKnown );
	}, [] );

	const add = ( raw: string ) => {
		const name = raw.replace( /^[#＃]/, '' ).trim();
		if ( name && ! tags.some( ( t ) => t.toLowerCase() === name.toLowerCase() ) ) {
			onChange( [ ...tags, name ] );
		}
		setText( '' );
	};

	return (
		<div class="tag-editor">
			<ul class="tags" aria-label="タグ">
				{ tags.map( ( tag ) => (
					<li key={ tag } class="tag">
						#{ tag }
						<button type="button" aria-label={ `${ tag } を外す` } onClick={ () => onChange( tags.filter( ( t ) => t !== tag ) ) }>
							×
						</button>
					</li>
				) ) }
			</ul>
			<input
				type="text"
				class="tag-input"
				list="hibi-tag-list"
				placeholder="タグを追加"
				aria-label="タグを追加"
				value={ text }
				enterKeyHint="done"
				onInput={ ( e ) => setText( ( e.currentTarget as HTMLInputElement ).value ) }
				onKeyDown={ ( e ) => {
					if ( ( e.key === 'Enter' || e.key === ',' || e.key === '、' ) && ! e.isComposing ) {
						e.preventDefault();
						add( text );
					}
				} }
				onBlur={ () => text && add( text ) }
			/>
			<datalist id="hibi-tag-list">
				{ known.filter( ( k ) => ! tags.includes( k ) ).map( ( k ) => (
					<option key={ k } value={ k } />
				) ) }
			</datalist>
		</div>
	);
}
