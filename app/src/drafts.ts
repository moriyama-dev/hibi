// Unsent edits, kept in IndexedDB until the server confirms them. Every
// change lands here before any network request, so a closed tab, a crash
// or a dead connection never loses what was typed.

import type { EntryFields } from './types';

export interface Draft {
	date: string;
	fields: EntryFields;
	base_version: number;
	updated_at: number;
	conflict?: boolean;
}

const STORE = 'drafts';
let dbName = 'hibi';
let opening: Promise<IDBDatabase> | null = null;

/** One database per user, so two people on one device never mix drafts. */
export function configureDrafts( userId: number ): void {
	dbName = `hibi-${ userId }`;
}

function open(): Promise<IDBDatabase> {
	opening ??= new Promise( ( resolve, reject ) => {
		const req = indexedDB.open( dbName, 1 );
		req.onupgradeneeded = () => req.result.createObjectStore( STORE, { keyPath: 'date' } );
		req.onsuccess = () => resolve( req.result );
		req.onerror = () => {
			opening = null;
			reject( req.error );
		};
	} );
	return opening;
}

async function run<T>( mode: IDBTransactionMode, fn: ( store: IDBObjectStore ) => IDBRequest<T> | void ): Promise<T | undefined> {
	const db = await open();
	return new Promise( ( resolve, reject ) => {
		const tx = db.transaction( STORE, mode );
		const req = fn( tx.objectStore( STORE ) );
		tx.oncomplete = () => resolve( req ? req.result : undefined );
		tx.onerror = () => reject( tx.error );
		tx.onabort = () => reject( tx.error );
	} );
}

export const drafts = {
	get: ( date: string ) => run<Draft | undefined>( 'readonly', ( s ) => s.get( date ) ).catch( () => undefined ),
	all: () => run<Draft[]>( 'readonly', ( s ) => s.getAll() ).then( ( r ) => r ?? [] ).catch( () => [] as Draft[] ),
	put: ( draft: Draft ) => run( 'readwrite', ( s ) => void s.put( draft ) ),
	delete: ( date: string ) => run( 'readwrite', ( s ) => void s.delete( date ) ),
};
