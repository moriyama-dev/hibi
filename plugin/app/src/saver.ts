import { signal } from '@preact/signals';
import { api, ApiError } from './api';
import { drafts, type Draft } from './drafts';
import { alreadyOnServer } from './same';
import type { Entry, EntryFields } from './types';

export type SaveState = 'saved' | 'dirty' | 'saving' | 'offline' | 'error' | 'conflict';

export interface Conflict {
	server: Entry;
	mine: EntryFields;
}

const IDLE_MS = 1500;
const MAX_WAIT_MS = 10000;
const RETRY_MS = [ 3000, 10000, 30000, 60000 ];

/**
 * Saves one day's entry: after typing pauses for 1.5 s, at least every 10 s
 * while typing goes on, and at once when the page is hidden.
 *
 * Nothing is ever dropped. Unsent fields stay in `pending`, mirrored to
 * IndexedDB, until the server confirms them; a version conflict stops
 * saving and keeps both copies until the writer chooses.
 */
export class EntrySaver {
	readonly state = signal<SaveState>( 'saved' );
	readonly conflict = signal<Conflict | null>( null );

	private pending: EntryFields = {};
	private version: number;
	private composing = false;
	private idleTimer = 0;
	private maxTimer = 0;
	private retryTimer = 0;
	private retries = 0;
	private inflight: Promise<void> | null = null;
	private disposed = false;
	private persisting: Promise<unknown> = Promise.resolve();

	constructor(
		readonly date: string,
		entry: Entry,
		private readonly onSaved: ( entry: Entry ) => void,
		draft?: Draft
	) {
		this.version = entry.version;
		if ( draft ) {
			// Unsent edits from an earlier session take precedence.
			this.pending = { ...draft.fields };
			this.version = draft.base_version;
			this.state.value = 'dirty';
			if ( draft.conflict ) {
				// Sending again brings back the server copy for the banner.
				window.setTimeout( () => this.flush(), 0 );
			} else {
				this.schedule();
			}
		}
	}

	/**
	 * Mirrors the unsent fields to IndexedDB, in order. Clears the draft
	 * once nothing is waiting.
	 */
	private persist(): Promise<unknown> {
		const snapshot: Draft = {
			date: this.date,
			fields: { ...this.pending },
			base_version: this.version,
			updated_at: Date.now(),
			conflict: this.conflict.value !== null,
		};
		const empty = Object.keys( snapshot.fields ).length === 0 && ! snapshot.conflict;
		this.persisting = this.persisting
			.then( () => ( empty ? drafts.delete( this.date ) : drafts.put( snapshot ) ) )
			.catch( () => undefined );
		return this.persisting;
	}

	get hasUnsaved(): boolean {
		return Object.keys( this.pending ).length > 0;
	}

	change( fields: EntryFields ): void {
		Object.assign( this.pending, fields );
		if ( this.state.value !== 'conflict' ) {
			this.state.value = 'dirty';
		}
		this.persist();
		this.schedule();
	}

	/** While an IME composition is open, autosave waits. */
	setComposing( composing: boolean ): void {
		this.composing = composing;
		if ( ! composing && this.hasUnsaved ) {
			this.schedule();
		}
	}

	private schedule(): void {
		if ( this.composing || this.disposed ) {
			return;
		}
		clearTimeout( this.idleTimer );
		this.idleTimer = window.setTimeout( () => this.flush(), IDLE_MS );
		if ( ! this.maxTimer ) {
			this.maxTimer = window.setTimeout( () => this.flush(), MAX_WAIT_MS );
		}
	}

	private clearTimers(): void {
		clearTimeout( this.idleTimer );
		clearTimeout( this.maxTimer );
		clearTimeout( this.retryTimer );
		this.idleTimer = this.maxTimer = this.retryTimer = 0;
	}

	/**
	 * Sends pending fields. `final` marks a save made because the page is
	 * being left: it uses keepalive so it survives the page closing, and it
	 * keeps a revision on the server. It runs even mid-composition, because
	 * the text would otherwise be lost.
	 */
	async flush( final = false ): Promise<void> {
		if ( this.composing && ! final ) {
			return;
		}
		this.clearTimers();
		if ( this.conflict.value ) {
			return;
		}
		if ( this.inflight ) {
			await this.inflight;
			if ( ! this.hasUnsaved ) {
				return;
			}
		}
		if ( ! this.hasUnsaved ) {
			return;
		}

		const sent = this.pending;
		this.pending = {};
		this.state.value = 'saving';

		this.inflight = this.send( sent, final ).finally( () => {
			this.inflight = null;
		} );
		return this.inflight;
	}

	private async send( sent: EntryFields, final: boolean ): Promise<void> {
		try {
			const entry = await api.putEntry( this.date, { ...sent, base_version: this.version, final }, final );
			this.version = entry.version;
			this.retries = 0;
			this.persist();
			this.onSaved( entry );
			if ( this.hasUnsaved ) {
				this.state.value = 'dirty';
				this.schedule();
			} else {
				this.state.value = 'saved';
			}
		} catch ( err ) {
			// Put the unsent fields back under anything typed since.
			this.pending = { ...sent, ...this.pending };

			if ( err instanceof ApiError && err.status === 409 ) {
				const server = err.data.entry as Entry;
				if ( alreadyOnServer( sent, server ) ) {
					// Nothing to choose: take the server's version and carry on.
					this.version = server.version;
					this.pending = Object.fromEntries( Object.entries( this.pending ).filter( ( [ k, v ] ) => ( sent as Record<string, unknown> )[ k ] !== v ) );
					this.retries = 0;
					this.persist();
					this.onSaved( server );
					if ( this.hasUnsaved ) {
						this.state.value = 'dirty';
						this.schedule();
					} else {
						this.state.value = 'saved';
					}
					return;
				}
				this.conflict.value = { server, mine: { ...this.pending } };
				this.state.value = 'conflict';
				this.persist();
				return;
			}

			this.persist();
			this.state.value = navigator.onLine ? 'error' : 'offline';
			if ( ! this.disposed ) {
				const delay = RETRY_MS[ Math.min( this.retries, RETRY_MS.length - 1 ) ];
				this.retries++;
				this.retryTimer = window.setTimeout( () => this.flush(), delay );
			}
		}
	}

	/** Retry at once, e.g. when the browser comes back online. */
	retryNow(): void {
		if ( this.hasUnsaved && ! this.conflict.value ) {
			this.flush();
		}
	}

	/**
	 * Settles a conflict. The caller has already put the chosen text in the
	 * editor; `fields` is what to write over the server's copy, or null to
	 * accept the server's copy as it is.
	 */
	resolve( fields: EntryFields | null ): void {
		const conflict = this.conflict.value;
		if ( ! conflict ) {
			return;
		}
		this.version = conflict.server.version;
		this.conflict.value = null;
		if ( fields ) {
			this.pending = { ...fields };
			this.state.value = 'dirty';
			this.persist();
			this.flush();
		} else {
			this.pending = {};
			this.state.value = 'saved';
			this.persist();
			this.onSaved( conflict.server );
		}
	}

	dispose(): void {
		this.disposed = true;
		this.clearTimers();
	}
}
