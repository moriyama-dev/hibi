import { signal } from '@preact/signals';
import { boot } from './boot';
import { journalDate } from './date';
import type { Entry, Settings, Stats } from './types';

export { boot };

export const settings = signal<Settings>( boot.settings );
export const stats = signal<Stats>( boot.stats );

/** Entries already loaded in this session, by date. */
export const entryCache = new Map<string, Entry>( [ [ boot.entry.date, boot.entry ] ] );

/**
 * Today as the server decided at page load, moved forward on the client if
 * the page stays open past the day boundary.
 */
export const today = signal<string>( boot.today );

export function refreshToday(): void {
	const local = journalDate( boot.timezone, settings.value.day_start_hour );
	if ( local > today.value ) {
		today.value = local;
	}
}
