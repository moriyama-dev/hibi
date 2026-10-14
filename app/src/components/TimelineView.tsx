import { useEffect, useRef, useState } from 'preact/hooks';
import { api } from '../api';
import type { Card } from '../types';
import { EntryCard } from './EntryCard';
import { ReviewTabs } from './ReviewTabs';

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
			<h1 class="visually-hidden">タイムライン</h1>
			<div class="filters">
				<select aria-label="タグで絞り込む" value={ filters.tag } onChange={ ( e ) => setFilters( { ...filters, tag: ( e.currentTarget as HTMLSelectElement ).value } ) }>
					<option value="">すべてのタグ</option>
					{ tags.map( ( t ) => (
						<option key={ t } value={ t }>
							#{ t }
						</option>
					) ) }
				</select>
				<select aria-label="気分で絞り込む" value={ filters.mood } onChange={ ( e ) => setFilters( { ...filters, mood: +( e.currentTarget as HTMLSelectElement ).value } ) }>
					<option value={ 0 }>すべての気分</option>
					<option value={ 5 }>最高</option>
					<option value={ 4 }>良い</option>
					<option value={ 3 }>ふつう</option>
					<option value={ 2 }>悪い</option>
					<option value={ 1 }>とても悪い</option>
				</select>
				<label class="check">
					<input type="checkbox" checked={ filters.has_photo } onChange={ ( e ) => setFilters( { ...filters, has_photo: ( e.currentTarget as HTMLInputElement ).checked } ) } />
					写真あり
				</label>
			</div>

			<div class="cards" aria-busy={ loading }>
				{ items.map( ( card ) => (
					<EntryCard key={ card.date } card={ card } />
				) ) }
			</div>
			{ ! loading && items.length === 0 && <p class="day-loading">該当する日記はありません。</p> }
			{ loading && <p class="day-loading" role="status">読み込み中…</p> }
			<div ref={ sentinel } />
		</div>
	);
}
