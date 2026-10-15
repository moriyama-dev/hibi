import { useEffect, useRef, useState } from 'preact/hooks';
import { api } from '../api';
import type { Card } from '../types';
import { EntryCard } from './EntryCard';
import { ReviewTabs } from './ReviewTabs';
import { t } from '../i18n';

interface Filters {
	tag: string;
	mood: number;
	has_photo: boolean;
}

export function TimelineView() {
	const [ filters, setFilters ] = useState<Filters>( { tag: '', mood: 0, has_photo: false } );
	const [ items, setItems ] = useState<Card[]>( [] );
	const [ cursor, setCursor ] = useState<string | null>( null );
	const [ done, setDone ] = useState( false );
	const [ loading, setLoading ] = useState( false );
	const [ tags, setTags ] = useState<string[]>( [] );
	const sentinel = useRef<HTMLDivElement>( null );
	const generation = useRef( 0 );

	useEffect( () => {
		api.tags().then( ( t ) => setTags( t.map( ( x ) => x.name ) ) ).catch( () => undefined );
	}, [] );

	const load = async ( reset: boolean ) => {
		const gen = reset ? ++generation.current : generation.current;
		setLoading( true );
		try {
			const page = await api.listEntries( { ...filters, cursor: reset ? undefined : cursor ?? undefined } );
			if ( gen !== generation.current ) {
				return;
			}
			setItems( ( prev ) => ( reset ? page.items : [ ...prev, ...page.items ] ) );
			setCursor( page.next_cursor );
			setDone( ! page.next_cursor );
		} catch {
			if ( gen === generation.current ) {
				setDone( true );
			}
		} finally {
			if ( gen === generation.current ) {
				setLoading( false );
			}
		}
	};

	useEffect( () => {
		setItems( [] );
		setDone( false );
		load( true );
	}, [ filters.tag, filters.mood, filters.has_photo ] );

	useEffect( () => {
		const el = sentinel.current;
		if ( ! el || done ) {
			return;
		}
		const observer = new IntersectionObserver(
			( entries ) => {
				if ( entries[ 0 ].isIntersecting && ! loading && cursor ) {
					load( false );
				}
			},
			{ rootMargin: '600px' }
		);
		observer.observe( el );
		return () => observer.disconnect();
	}, [ cursor, loading, done ] );

	return (
		<div class="review">
			<ReviewTabs />
			<h1 class="visually-hidden">{ t( 'timeline.title' ) }</h1>
			<div class="filters">
				<select aria-label={ t( 'timeline.by_tag' ) } value={ filters.tag } onChange={ ( e ) => setFilters( { ...filters, tag: ( e.currentTarget as HTMLSelectElement ).value } ) }>
					<option value="">{ t( 'timeline.all_tags' ) }</option>
					{ tags.map( ( t ) => (
						<option key={ t } value={ t }>
							#{ t }
						</option>
					) ) }
				</select>
				<select aria-label={ t( 'timeline.by_mood' ) } value={ filters.mood } onChange={ ( e ) => setFilters( { ...filters, mood: +( e.currentTarget as HTMLSelectElement ).value } ) }>
					<option value={ 0 }>{ t( 'timeline.all_moods' ) }</option>
					{ [ 5, 4, 3, 2, 1 ].map( ( m ) => (
						<option key={ m } value={ m }>
							{ t( `mood.${ m }` ) }
						</option>
					) ) }
				</select>
				<label class="check">
					<input type="checkbox" checked={ filters.has_photo } onChange={ ( e ) => setFilters( { ...filters, has_photo: ( e.currentTarget as HTMLInputElement ).checked } ) } />
					{ t( 'timeline.with_photos' ) }
				</label>
			</div>

			<div class="cards" aria-busy={ loading }>
				{ items.map( ( card ) => (
					<EntryCard key={ card.date } card={ card } />
				) ) }
			</div>
			{ ! loading && items.length === 0 && <p class="day-loading">{ t( 'timeline.none' ) }</p> }
			{ loading && <p class="day-loading" role="status">{ t( 'common.loading' ) }</p> }
			<div ref={ sentinel } />
		</div>
	);
}
