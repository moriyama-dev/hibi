import type { Entry, EntryFields } from './types';

/**
 * Whether the server already holds every field of an unsent change. This
 * happens when a save made while the page was closing reached the server
 * but the page was gone before it could clear its draft.
 */
export function alreadyOnServer( fields: EntryFields, server: Entry ): boolean {
	if ( 'content' in fields && ( fields.content ?? '' ).replace( /\r\n?/g, '\n' ) !== server.content ) {
		return false;
	}
	if ( 'mood' in fields && ( fields.mood ?? null ) !== server.mood ) {
		return false;
	}
	if ( 'tags' in fields ) {
		const a = [ ...( fields.tags ?? [] ) ].map( ( t ) => t.replace( /^[#＃]/, '' ).trim().toLowerCase() ).sort();
		const b = [ ...server.tags ].map( ( t ) => t.toLowerCase() ).sort();
		if ( a.join( '\n' ) !== b.join( '\n' ) ) {
			return false;
		}
	}
	if ( 'weather' in fields && !! fields.weather !== !! server.weather ) {
		return false;
	}
	if ( 'location' in fields && !! fields.location !== !! server.location ) {
		return false;
	}
	return true;
}
