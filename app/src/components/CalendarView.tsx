import { useEffect, useMemo, useState } from 'preact/hooks';
import { api } from '../api';
import { dayHref } from '../router';
import { today } from '../store';
import type { MonthDay } from '../types';
import { ReviewTabs } from './ReviewTabs';
import { monthDayLabel, monthLabel, weekdayNames, yearOnly } from '../date';
import { formatNumber, t } from '../i18n';

const DOW = weekdayNames();

function pad( n: number ): string {
	return String( n ).padStart( 2, '0' );
}

function daysIn( year: number, month: number ): number {
	return new Date( Date.UTC( year, month, 0 ) ).getUTCDate();
}

function longestRun( dates: string[] ): number {
	let best = 0;
	let run = 0;
	let prev = '';
	for ( const d of [ ...dates ].sort() ) {
		const expected = prev ? new Date( Date.parse( prev + 'T00:00:00Z' ) + 86400000 ).toISOString().slice( 0, 10 ) : '';
		run = d === expected ? run + 1 : 1;
		best = Math.max( best, run );
		prev = d;
	}
	return best;
}

export function CalendarView() {
	const [ year, setYear ] = useState( +today.value.slice( 0, 4 ) );
	const [ month, setMonth ] = useState( +today.value.slice( 5, 7 ) );
	const [ days, setDays ] = useState<Record<string, MonthDay> | null>( null );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		let alive = true;
		setDays( null );
		setFailed( false );
		api.calendar( year, month )
			.then( ( r ) => alive && setDays( r.days ) )
			.catch( () => alive && setFailed( true ) );
		return () => {
			alive = false;
		};
	}, [ year, month ] );

	const move = ( offset: number ) => {
		const m = month + offset;
		setYear( year + Math.floor( ( m - 1 ) / 12 ) );
		setMonth( ( ( ( m - 1 ) % 12 ) + 12 ) % 12 + 1 );
	};

	const isCurrentMonth = `${ year }-${ pad( month ) }` >= today.value.slice( 0, 7 );
	const first = new Date( Date.UTC( year, month - 1, 1 ) ).getUTCDay();
	const total = daysIn( year, month );

	const written = days ? Object.keys( days ) : [];
	const chars = days ? Object.values( days ).reduce( ( sum, d ) => sum + d.chars, 0 ) : 0;

	return (
		<div class="review">
			<ReviewTabs />
			<header class="month-head">
				<button type="button" class="day-nav" onClick={ () => move( -1 ) } aria-label={ t( 'cal.prev_month' ) }>
					‹
				</button>
				<h1>
					{ monthLabel( year, month ) }
				</h1>
				<button type="button" class="day-nav" onClick={ () => move( 1 ) } aria-label={ t( 'cal.next_month' ) } disabled={ isCurrentMonth }>
					›
				</button>
			</header>

			{ failed && <p class="day-loading">{ t( 'common.load_failed' ) }</p> }

			<div class="calendar" role="grid" aria-label={ monthLabel( year, month ) } aria-busy={ ! days && ! failed }>
				{ DOW.map( ( d, i ) => (
					<span key={ d } class={ `cal-dow${ i === 0 ? ' is-sun' : '' }` } role="columnheader">
						{ d }
					</span>
				) ) }
				{ Array.from( { length: first } ).map( ( _, i ) => (
					<span key={ `b${ i }` } aria-hidden="true" />
				) ) }
				{ Array.from( { length: total } ).map( ( _, i ) => {
					const date = `${ year }-${ pad( month ) }-${ pad( i + 1 ) }`;
					const info = days?.[ date ];
					const future = date > today.value;
					const name = monthDayLabel( date );
					const label = ! info ? name : info.mood ? t( 'cal.written_mood', name, t( `mood.${ info.mood }` ) ) : t( 'cal.written', name );
					const cls = `cal-day${ date === today.value ? ' is-today' : '' }${ info ? ' has-entry' : '' }`;
					const dot = <span class={ `cal-dot level-${ info ? Math.max( 1, info.level ) : 0 }${ info?.mood ? ` mood-bg-${ info.mood }` : '' }` } />;
					return future ? (
						<span key={ date } class={ `${ cls } is-future` } role="gridcell">
							{ i + 1 }
						</span>
					) : (
						<a key={ date } href={ dayHref( date ) } data-nav class={ cls } role="gridcell" aria-label={ label }>
							{ i + 1 }
							{ dot }
						</a>
					);
				} ) }
			</div>

			<dl class="month-stats">
				<div>
					<dt>{ t( 'stats.days' ) }</dt>
					<dd>{ written.length }</dd>
				</div>
				<div>
					<dt>{ t( 'stats.chars' ) }</dt>
					<dd>{ formatNumber( chars ) }</dd>
				</div>
				<div>
					<dt>{ t( 'stats.longest' ) }</dt>
					<dd>{ longestRun( written ) }</dd>
				</div>
			</dl>

			<YearHeatmap year={ year } />
		</div>
	);
}

function YearHeatmap( { year }: { year: number } ) {
	const [ days, setDays ] = useState<Record<string, [ number, number | null ]> | null>( null );

	useEffect( () => {
		let alive = true;
		api.year( year )
			.then( ( r ) => alive && setDays( r.days ) )
			.catch( () => undefined );
		return () => {
			alive = false;
		};
	}, [ year ] );

	// Columns are weeks (Sunday first), rows are weekdays.
	const cells = useMemo( () => {
		const out: { date: string; level: number }[] = [];
		const start = new Date( Date.UTC( year, 0, 1 ) );
		const lead = start.getUTCDay();
		for ( let i = 0; i < lead; i++ ) {
			out.push( { date: '', level: -1 } );
		}
		for ( let t = start.getTime(); new Date( t ).getUTCFullYear() === year; t += 86400000 ) {
			const date = new Date( t ).toISOString().slice( 0, 10 );
			out.push( { date, level: days?.[ date ] ? Math.max( 1, days[ date ][ 0 ] ) : 0 } );
		}
		return out;
	}, [ year, days ] );

	const count = days ? Object.keys( days ).length : 0;

	return (
		<section class="heatmap-wrap" aria-label={ t( 'heat.label', yearOnly( `${ year }-01-01` ) ) }>
			<h2>
				{ yearOnly( `${ year }-01-01` ) } <span>{ t( 'heat.days', count ) }</span>
			</h2>
			<div class="heatmap" aria-hidden="true">
				{ cells.map( ( c, i ) => (
					<i key={ i } class={ c.level < 0 ? 'is-blank' : `h${ c.level }` } title={ c.date || undefined } />
				) ) }
			</div>
		</section>
	);
}
