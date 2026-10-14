import type { Bootstrap, Card, Entry, EntryFields, MonthDay, Page, Photo, Place, SearchItem, Settings, Stats, Weather } from './types';

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
	signal?: AbortSignal;
}

async function request<T>( method: string, path: string, body?: unknown, options: RequestOptions = {} ): Promise<T> {
	const res = await fetch( url( path ), {
		method,
		credentials: 'same-origin',
		cache: 'no-store',
		keepalive: options.keepalive,
		signal: options.signal,
		headers: {
			'X-WP-Nonce': nonce,
			...( body === undefined || body instanceof FormData ? {} : { 'Content-Type': 'application/json' } ),
		},
		body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify( body ),
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

	listEntries( filters: { cursor?: string; tag?: string; mood?: number; has_photo?: boolean; limit?: number } ) {
		const q = new URLSearchParams();
		for ( const [ k, v ] of Object.entries( filters ) ) {
			if ( v !== undefined && v !== '' && v !== false && v !== 0 ) {
				q.set( k, String( v ) );
			}
		}
		return request<Page<Card>>( 'GET', `entries?${ q }` );
	},

	calendar: ( year: number, month: number ) =>
		request<{ year: number; month: number; days: Record<string, MonthDay> }>( 'GET', `calendar?year=${ year }&month=${ month }` ),

	year: ( year: number ) => request<{ year: number; days: Record<string, [ number, number | null ]> }>( 'GET', `year?year=${ year }` ),

	onThisDay: ( date: string ) => request<{ date: string; items: Card[] }>( 'GET', `on-this-day?date=${ date }` ),

	search: ( q: string, cursor: string | null, signal?: AbortSignal ) =>
		request<Page<SearchItem> & { terms: string[] }>(
			'GET',
			`search?q=${ encodeURIComponent( q ) }${ cursor ? `&cursor=${ cursor }` : '' }`,
			undefined,
			{ signal }
		),

	tags: () => request<{ name: string; count: number }[]>( 'GET', 'tags' ),

	uploadPhoto( date: string, file: Blob, name: string ) {
		const form = new FormData();
		form.append( 'file', file, name );
		return request<Photo>( 'POST', `entries/${ date }/photos`, form );
	},

	deletePhoto: ( id: number ) => request<{ deleted: boolean }>( 'DELETE', `photos/${ id }` ),

	putSettings: ( changes: Partial<Settings> ) => request<Settings>( 'PUT', 'settings', changes ),

	weather: ( lat: number, lng: number, date: string ) => request<Weather>( 'GET', `weather?lat=${ lat }&lng=${ lng }&date=${ date }` ),

	places: ( q: string ) => request<Place[]>( 'GET', `places?q=${ encodeURIComponent( q ) }` ),

	pushSubscribe: ( sub: PushSubscriptionJSON ) => request<{ subscribed: boolean }>( 'POST', 'push/subscribe', sub ),

	pushUnsubscribe: ( endpoint: string ) => request<{ subscribed: boolean }>( 'DELETE', 'push/subscribe', { endpoint } ),

	pushTest: () => request<{ sent: number; failed: number; removed: number }>( 'POST', 'push/test' ),
};
