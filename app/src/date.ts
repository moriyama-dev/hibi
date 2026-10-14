// Date rules shared with the server (Hibi_Day). The server decides "today";
// these run only when the app has to work it out itself, e.g. offline or
// after the page stayed open past the day boundary.

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

const WEEKDAYS = [ '日', '月', '火', '水', '木', '金', '土' ];

/** "10月14日（水）" */
export function dayLabel( date: string ): string {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	const weekday = WEEKDAYS[ new Date( Date.UTC( y, m - 1, d ) ).getUTCDay() ];
	return `${ m }月${ d }日（${ weekday }）`;
}

/** "2025年" when the date is not in the given year, otherwise "". */
export function yearLabel( date: string, today: string ): string {
	return date.slice( 0, 4 ) === today.slice( 0, 4 ) ? '' : `${ +date.slice( 0, 4 ) }年`;
}

/** "14:32" in the site time zone. */
export function timeStamp( timeZone: string, now: Date = new Date() ): string {
	const clock = wallClock( timeZone, now );
	return `${ String( clock.hour ).padStart( 2, '0' ) }:${ String( clock.minute ).padStart( 2, '0' ) }`;
}
