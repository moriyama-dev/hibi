import { signal } from '@preact/signals';
import { api, ApiError } from './api';
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
 * Nothing is ever dropped. Unsent fields stay in `pending` until the server
 * confirms them; a version conflict stops saving and keeps both copies
 * until the writer chooses.
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

	constructor(
		readonly date: string,
		entry: Entry,
		private readonly onSaved: ( entry: Entry ) => void
	) {
		this.version = entry.version;
	}

	get hasUnsaved(): boolean {
		return Object.keys( this.pending ).length > 0;
	}

	change( fields: EntryFields ): void {
		Object.assign( this.pending, fields );
		if ( this.state.value !== 'conflict' ) {
			this.state.value = 'dirty';
		}
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
				this.conflict.value = { server: err.data.entry as Entry, mine: { ...this.pending } };
				this.state.value = 'conflict';
				return;
			}

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
			this.flush();
		} else {
			this.pending = {};
			this.state.value = 'saved';
			this.onSaved( conflict.server );
		}
	}

	dispose(): void {
		this.disposed = true;
		this.clearTimers();
	}
}
