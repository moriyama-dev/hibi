import { useEffect, useRef, useState } from 'preact/hooks';
import { api } from '../api';
import { dayLabel, yearLabel } from '../date';
import { dayHref } from '../router';
import { today } from '../store';
import type { SearchItem } from '../types';
import { MoodDot } from './EntryCard';

const DEBOUNCE_MS = 300;

export function SearchView() {
	const input = useRef<HTMLInputElement>( null );
	const [ query, setQuery ] = useState( () => new URLSearchParams( location.search ).get( 'q' ) ?? '' );
	const [ items, setItems ] = useState<SearchItem[]>( [] );
	const [ cursor, setCursor ] = useState<string | null>( null );
	const [ state, setState ] = useState<'idle' | 'loading' | 'done' | 'error'>( 'idle' );
	const controller = useRef<AbortController | null>( null );

	useEffect( () => {
		input.current?.focus();
	}, [] );

	const run = async ( q: string, after: string | null ) => {
		controller.current?.abort();
		const ctrl = new AbortController();
		controller.current = ctrl;
		setState( 'loading' );
		try {
			const page = await api.search( q, after, ctrl.signal );
			setItems( ( prev ) => ( after ? [ ...prev, ...page.items ] : page.items ) );
			setCursor( page.next_cursor );
			setState( 'done' );
		} catch ( err ) {
			if ( ( err as Error ).name !== 'AbortError' ) {
				setState( 'error' );
			}
		}
	};

	useEffect( () => {
		const q = query.trim();
		// Keep the query in the URL so "back" from a result returns here.
		const url = new URL( location.href );
		if ( q ) {
			url.searchParams.set( 'q', q );
		} else {
			url.searchParams.delete( 'q' );
		}
		history.replaceState( null, '', url );

		if ( ! q ) {
			controller.current?.abort();
			setItems( [] );
			setCursor( null );
			setState( 'idle' );
			return;
		}
		const timer = window.setTimeout( () => run( q, null ), DEBOUNCE_MS );
		return () => clearTimeout( timer );
	}, [ query ] );

	return (
		<div class="search">
			<h1 class="visually-hidden">検索</h1>
			<input
				ref={ input }
				type="search"
				class="search-input"
				placeholder="日記を検索"
				aria-label="日記を検索"
				value={ query }
				enterKeyHint="search"
				onInput={ ( e ) => setQuery( ( e.currentTarget as HTMLInputElement ).value ) }
			/>
			<p class="search-state" role="status" aria-live="polite">
				{ state === 'loading' && '検索中…' }
				{ state === 'error' && '検索できませんでした。' }
				{ state === 'done' && ( items.length ? `${ items.length }${ cursor ? '件以上' : '件' }` : '見つかりませんでした。' ) }
			</p>
			<ol class="results">
				{ items.map( ( item ) => (
					<li key={ item.date }>
						<a href={ dayHref( item.date ) } data-nav class="result">
							<span class="card-date">
								<MoodDot mood={ item.mood } />
								{ yearLabel( item.date, today.value ) || `${ item.date.slice( 0, 4 ) }年` }
								{ dayLabel( item.date ) }
							</span>
							<span class="result-text">
								{ item.segments.map( ( [ text, hit ], i ) => ( hit ? <mark key={ i }>{ text }</mark> : text ) ) }
							</span>
						</a>
					</li>
				) ) }
			</ol>
			{ cursor && state === 'done' && (
				<button type="button" class="more" onClick={ () => run( query.trim(), cursor ) }>
					もっと見る
				</button>
			) }
		</div>
	);
}
