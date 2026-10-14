import type { Bootstrap, Entry, EntryFields, Settings, Stats } from './types';

export class ApiError extends Error {
	constructor(
		readonly status: number,
		readonly code: string,
		message: string,
		readonly data: Record<string, unknown> = {}
	) {
		super( message );
	}
}

let root = '';
let nonce = '';
let nonceUrl = '';
let loginUrl = '';

export function configureApi( boot: Bootstrap ): void {
	root = boot.api.root;
	nonce = boot.nonce;
	nonceUrl = boot.api.nonce_url;
	loginUrl = boot.api.login_url;
}

/** Works for both /wp-json/hibi/v1/ and ?rest_route=/hibi/v1/ roots. */
function url( path: string ): string {
	const q = path.indexOf( '?' );
	if ( q === -1 || root.indexOf( '?' ) === -1 ) {
		return root + path;
	}
	return root + path.slice( 0, q ) + '&' + path.slice( q + 1 );
}

async function refreshNonce(): Promise<void> {
	const res = await fetch( nonceUrl, { credentials: 'same-origin', cache: 'no-store' } );
	if ( ! res.ok ) {
		goToLogin();
		throw new ApiError( res.status, 'hibi_logged_out', 'Logged out' );
	}
	const json = await res.json();
	nonce = json.data.nonce;
}

function goToLogin(): void {
	const target = new URL( loginUrl, location.href );
	target.searchParams.set( 'redirect_to', location.href );
	location.assign( target.toString() );
}

interface RequestOptions {
	keepalive?: boolean;
	retried?: boolean;
}

async function request<T>( method: string, path: string, body?: unknown, options: RequestOptions = {} ): Promise<T> {
	const res = await fetch( url( path ), {
		method,
		credentials: 'same-origin',
		cache: 'no-store',
		keepalive: options.keepalive,
		headers: {
			'X-WP-Nonce': nonce,
			...( body === undefined ? {} : { 'Content-Type': 'application/json' } ),
		},
		body: body === undefined ? undefined : JSON.stringify( body ),
	} );

	if ( res.ok ) {
		return res.json() as Promise<T>;
	}

	const json = await res.json().catch( () => null );
	const code: string = json?.code ?? 'hibi_http_' + res.status;

	if ( code === 'rest_cookie_invalid_nonce' && ! options.retried ) {
		await refreshNonce();
		return request<T>( method, path, body, { ...options, retried: true } );
	}
	if ( res.status === 401 ) {
		goToLogin();
	}
	throw new ApiError( res.status, code, json?.message ?? res.statusText, json?.data ?? {} );
}

// keepalive bodies are capped at 64 KiB by browsers.
const KEEPALIVE_LIMIT = 60000;

export const api = {
	getEntry: ( date: string ) => request<Entry>( 'GET', `entries/${ date }` ),

	putEntry( date: string, fields: EntryFields & { base_version: number; final?: boolean }, keepalive = false ) {
		const size = new Blob( [ JSON.stringify( fields ) ] ).size;
		return request<Entry>( 'PUT', `entries/${ date }`, fields, { keepalive: keepalive && size < KEEPALIVE_LIMIT } );
	},

	getStats: () => request<Stats>( 'GET', 'stats' ),

	putSettings: ( changes: Partial<Settings> ) => request<Settings>( 'PUT', 'settings', changes ),
};
