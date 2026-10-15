// Sends drafts of days that are not open in the editor: at start-up and
// whenever the connection comes back. The open day's editor sends its own.

import { signal } from '@preact/signals';
import { api, ApiError } from './api';
import { drafts, type Draft } from './drafts';
import { alreadyOnServer } from './same';
import type { Entry } from './types';
import { entryCache } from './store';

export const openDates = new Set<string>();

/** Days whose draft collides with a newer copy from another device. */
export const conflictDates = signal<string[]>( [] );
/** Days still waiting to be sent. */
export const pendingDates = signal<string[]>( [] );

let running = false;

export async function refreshDraftState(): Promise<Draft[]> {
	const all = await drafts.all();
	conflictDates.value = all.filter( ( d ) => d.conflict ).map( ( d ) => d.date ).sort();
	pendingDates.value = all.filter( ( d ) => ! d.conflict ).map( ( d ) => d.date ).sort();
	return all;
}

export async function syncDrafts(): Promise<void> {
	if ( running ) {
		return;
	}
	running = true;
	try {
		for ( const draft of await refreshDraftState() ) {
			if ( draft.conflict || openDates.has( draft.date ) ) {
				continue;
			}
			try {
				const entry = await api.putEntry( draft.date, { ...draft.fields, base_version: draft.base_version } );
				// Only drop the draft if nobody touched it while it was in flight.
				const latest = await drafts.get( draft.date );
				if ( latest && latest.updated_at === draft.updated_at ) {
					await drafts.delete( draft.date );
				} else if ( latest ) {
					await drafts.put( { ...latest, base_version: entry.version } );
				}
				entryCache.set( entry.date, entry );
			} catch ( err ) {
				if ( err instanceof ApiError && err.status === 409 ) {
					const server = err.data.entry as Entry;
					if ( alreadyOnServer( draft.fields, server ) ) {
						await drafts.delete( draft.date );
						entryCache.set( server.date, server );
						continue;
					}
					await drafts.put( { ...draft, conflict: true } );
				} else {
					break; // Offline or server trouble: try again later.
				}
			}
		}
	} finally {
		running = false;
		await refreshDraftState();
	}
}

export function startSync(): void {
	window.addEventListener( 'online', () => syncDrafts() );
	document.addEventListener( 'visibilitychange', () => document.visibilityState === 'visible' && syncDrafts() );
	syncDrafts();
}
