import { useEffect, useState } from 'preact/hooks';
import { api } from '../api';
import { dayHref } from '../router';
import { today } from '../store';
import type { Card } from '../types';
import { EntryCard } from './EntryCard';
import { ReviewTabs } from './ReviewTabs';

const cache = new Map<string, Promise<Card[]>>();

function pastYears( date: string ): Promise<Card[]> {
	if ( ! cache.has( date ) ) {
		cache.set(
			date,
			api.onThisDay( date ).then( ( r ) => r.items ).catch( () => {
				cache.delete( date );
				return [];
			} )
		);
	}
	return cache.get( date ) as Promise<Card[]>;
}

function yearsAgo( date: string, from: string ): number {
	return +from.slice( 0, 4 ) - +date.slice( 0, 4 );
}

/** Small card on today's page, shown only when a past year has an entry. */
export function OnThisDayCard( { date }: { date: string } ) {
	const [ card, setCard ] = useState<Card | null>( null );

	useEffect( () => {
		let alive = true;
		// Wait until the editor is up; this is never needed for the first paint.
		const idle = window.requestIdleCallback ?? ( ( cb: () => void ) => window.setTimeout( cb, 300 ) );
		idle( () => pastYears( date ).then( ( items ) => alive && setCard( items[ 0 ] ?? null ) ) );
		return () => {
			alive = false;
		};
	}, [ date ] );

	if ( ! card ) {
		return null;
	}
	return (
		<a class="otd" href={ dayHref( card.date ) } data-nav>
			<b>{ yearsAgo( card.date, date ) }年前の今日</b>
			<span>{ card.excerpt.split( '\n' )[ 0 ] }</span>
		</a>
	);
}

export function OnThisDayView() {
	const [ items, setItems ] = useState<Card[] | null>( null );

	useEffect( () => {
		pastYears( today.value ).then( setItems );
	}, [] );

	return (
		<div class="review">
			<ReviewTabs />
			<h1 class="review-title">
				{ +today.value.slice( 5, 7 ) }月{ +today.value.slice( 8, 10 ) }日の記録
			</h1>
			{ items === null && <p class="day-loading" role="status">読み込み中…</p> }
			{ items && items.length === 0 && <p class="day-loading">過去の同じ日の日記はまだありません。</p> }
			<div class="cards">
				{ items?.map( ( card ) => (
					<EntryCard key={ card.date } card={ card } heading={ `${ yearsAgo( card.date, today.value ) }年前（${ card.date.slice( 0, 4 ) }年）` } />
				) ) }
			</div>
		</div>
	);
}
