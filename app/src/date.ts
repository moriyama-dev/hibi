// Date rules shared with the server (Hibi_Day). The server decides "today";
// these run only when the app has to work it out itself, e.g. offline or
// after the page stayed open past the day boundary.

import { locale } from './i18n';

const DATE_RE = /^(\d{4})-(\d{2})-(\d{2})$/;

export function isDate( value: string ): boolean {
	const m = DATE_RE.exec( value );
	if ( ! m ) {
		return false;
	}
	const d = new Date( Date.UTC( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ] ) );
	return d.getUTCFullYear() === +m[ 1 ] && d.getUTCMonth() === +m[ 2 ] - 1 && d.getUTCDate() === +m[ 3 ];
}

interface WallClock {
	date: string;
	hour: number;
	minute: number;
}

export function wallClock( timeZone: string, now: Date = new Date() ): WallClock {
	const parts: Record<string, string> = {};
	for ( const p of new Intl.DateTimeFormat( 'en-CA', {
		timeZone,
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
		hourCycle: 'h23',
	} ).formatToParts( now ) ) {
		parts[ p.type ] = p.value;
	}
	return {
		date: `${ parts.year }-${ parts.month }-${ parts.day }`,
		hour: +parts.hour,
		minute: +parts.minute,
	};
}

/** Journal date: before the day start hour, it is still the previous day. */
export function journalDate( timeZone: string, dayStartHour: number, now: Date = new Date() ): string {
	const clock = wallClock( timeZone, now );
	return clock.hour < dayStartHour ? shiftDate( clock.date, -1 ) : clock.date;
}

export function shiftDate( date: string, days: number ): string {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	return new Date( Date.UTC( y, m - 1, d + days ) ).toISOString().slice( 0, 10 );
}

/** Noon UTC of a calendar date: formatting it in UTC never shifts the day. */
function utc( date: string ): Date {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	return new Date( Date.UTC( y, m - 1, d, 12 ) );
}

function format( date: string, options: Intl.DateTimeFormatOptions ): string {
	return new Intl.DateTimeFormat( locale, { timeZone: 'UTC', ...options } ).format( utc( date ) );
}

/** e.g. "Wed, October 14", in the user's language. */
export function dayLabel( date: string ): string {
	return format( date, { month: 'long', day: 'numeric', weekday: 'short' } );
}

/** e.g. "October 14". */
export function monthDayLabel( date: string ): string {
	return format( date, { month: 'long', day: 'numeric' } );
}

/** e.g. "2025". */
export function yearOnly( date: string ): string {
	return format( date, { year: 'numeric' } );
}

/** The year when the date is not in the given year, otherwise "". */
export function yearLabel( date: string, today: string ): string {
	return date.slice( 0, 4 ) === today.slice( 0, 4 ) ? '' : yearOnly( date );
}

/** e.g. "October 2026". */
export function monthLabel( year: number, month: number ): string {
	return format( `${ year }-${ String( month ).padStart( 2, '0' ) }-01`, { year: 'numeric', month: 'long' } );
}

/** Short weekday names, Sunday first. */
export function weekdayNames(): string[] {
	// 2026-10-04 is a Sunday.
	return Array.from( { length: 7 }, ( _, i ) => format( shiftDate( '2026-10-04', i ), { weekday: 'narrow' } ) );
}

/** "14:32" in the site time zone. */
export function timeStamp( timeZone: string, now: Date = new Date() ): string {
	const clock = wallClock( timeZone, now );
	return `${ String( clock.hour ).padStart( 2, '0' ) }:${ String( clock.minute ).padStart( 2, '0' ) }`;
}
