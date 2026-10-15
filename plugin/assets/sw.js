/**
 * Hibi service worker. Served by WordPress at /hibi-sw.js, which prepends
 * `self.HIBI_SW = { version, home, precache }`.
 *
 * - App screens: network first, falling back to the last shell, so the app
 *   opens offline.
 * - Built assets and icons: cache first (file names change on every build).
 * - API GETs: network first, falling back to the last response.
 * - Photo thumbnails: cache first, at most 300.
 * - Writes (PUT/POST/DELETE) are never cached; the app queues them itself.
 */
/* eslint-env serviceworker */

const CFG = self.HIBI_SW;
const STATIC = 'hibi-static-' + CFG.version;
const SHELL = 'hibi-shell';
const API = 'hibi-api';
const PHOTOS = 'hibi-photos';
const SHELL_KEY = CFG.home + '__hibi_shell__';
const NAV_TIMEOUT_MS = 4000;
const LIMITS = { [ API ]: 300, [ PHOTOS ]: 300 };

self.addEventListener( 'install', ( event ) => {
	event.waitUntil(
		caches
			.open( STATIC )
			.then( ( cache ) => cache.addAll( CFG.precache ) )
			.then( () => self.skipWaiting() )
	);
} );

self.addEventListener( 'activate', ( event ) => {
	event.waitUntil(
		caches
			.keys()
			.then( ( keys ) => Promise.all( keys.filter( ( k ) => k.startsWith( 'hibi-static-' ) && k !== STATIC ).map( ( k ) => caches.delete( k ) ) ) )
			.then( () => self.clients.claim() )
			// The page that installed the worker never passed through it, so
			// fetch the shell now: the app must open offline from the next start.
			.then( () => warmShell() )
	);
} );

/** Stores a fresh app shell, if the user is logged in. */
async function warmShell() {
	try {
		const response = await fetch( CFG.home, { credentials: 'same-origin', cache: 'no-store' } );
		await keepShell( response );
	} catch ( err ) {
		// Offline: the next navigation will store it.
	}
}

/** Only the logged-in app page is stored, never a login form or an error page. */
async function keepShell( response ) {
	const type = response.headers.get( 'Content-Type' ) || '';
	if ( response.ok && ! response.redirected && type.includes( 'text/html' ) && response.headers.get( 'X-Hibi-Shell' ) === '1' ) {
		await put( SHELL, SHELL_KEY, response.clone() );
	}
}

self.addEventListener( 'message', ( event ) => {
	if ( event.data === 'clear-private-caches' ) {
		// Sent on logout so another person on this device sees nothing.
		event.waitUntil( Promise.all( [ SHELL, API, PHOTOS ].map( ( name ) => caches.delete( name ) ) ) );
	}
} );

/** Keeps a cache to its size limit, dropping the oldest entries. */
async function trim( name ) {
	const cache = await caches.open( name );
	const keys = await cache.keys();
	const excess = keys.length - LIMITS[ name ];
	for ( let i = 0; i < excess; i++ ) {
		await cache.delete( keys[ i ] );
	}
}

async function put( name, request, response ) {
	const cache = await caches.open( name );
	await cache.put( request, response );
	if ( LIMITS[ name ] ) {
		await trim( name );
	}
}

function isAppPath( url ) {
	const path = url.pathname;
	return path.startsWith( CFG.home ) && ! /\/wp-(admin|login\.php|json|content|includes)|xmlrpc\.php|admin-ajax\.php/.test( path );
}

function isApi( url ) {
	return url.pathname.includes( '/wp-json/hibi/v1/' ) || ( url.searchParams.get( 'rest_route' ) || '' ).startsWith( '/hibi/v1/' );
}

function timeout( ms ) {
	return new Promise( ( _, reject ) => setTimeout( () => reject( new Error( 'timeout' ) ), ms ) );
}

async function navigation( request ) {
	try {
		const response = await Promise.race( [ fetch( request ), timeout( NAV_TIMEOUT_MS ) ] );
		await keepShell( response );
		return response;
	} catch ( err ) {
		const cached = await caches.match( SHELL_KEY );
		if ( cached ) {
			return cached;
		}
		const text = String( CFG.offline ).replace( /[&<>]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;' } )[ c ] );
		return new Response( '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Hibi</title><p style="font-family:sans-serif;padding:24px">' + text + '</p>', {
			status: 503,
			headers: { 'Content-Type': 'text/html; charset=utf-8' },
		} );
	}
}

async function networkFirst( request, name ) {
	try {
		const response = await fetch( request );
		if ( response.ok ) {
			await put( name, request, response.clone() );
		}
		return response;
	} catch ( err ) {
		const cached = await caches.match( request, { cacheName: name } );
		if ( cached ) {
			return cached;
		}
		throw err;
	}
}

async function cacheFirst( request, name ) {
	const cached = await caches.match( request, { cacheName: name } );
	if ( cached ) {
		return cached;
	}
	const response = await fetch( request );
	if ( response.ok ) {
		await put( name, request, response.clone() );
	}
	return response;
}

self.addEventListener( 'fetch', ( event ) => {
	const request = event.request;
	if ( request.method !== 'GET' ) {
		return;
	}
	const url = new URL( request.url );
	if ( url.origin !== self.location.origin ) {
		return;
	}

	if ( request.mode === 'navigate' ) {
		if ( isAppPath( url ) && ! url.searchParams.has( 'hibi_photo' ) ) {
			event.respondWith( navigation( request ) );
		}
		return;
	}
	if ( CFG.precache.includes( url.pathname ) ) {
		event.respondWith( cacheFirst( request, STATIC ) );
		return;
	}
	if ( isApi( url ) ) {
		event.respondWith( networkFirst( request, API ) );
		return;
	}
	if ( url.searchParams.has( 'hibi_photo' ) ) {
		event.respondWith( url.searchParams.get( 'size' ) === 'thumb' ? cacheFirst( request, PHOTOS ) : networkFirst( request, PHOTOS ) );
	}
} );

self.addEventListener( 'push', ( event ) => {
	let data = {};
	try {
		data = event.data ? event.data.json() : {};
	} catch ( err ) {
		data = { body: event.data ? event.data.text() : '' };
	}
	event.waitUntil(
		self.registration.showNotification( data.title || 'Hibi', {
			body: data.body || '',
			tag: data.tag || 'hibi',
			icon: CFG.icon,
			badge: CFG.icon,
			data: { url: data.url || CFG.home },
		} )
	);
} );

self.addEventListener( 'notificationclick', ( event ) => {
	event.notification.close();
	const target = ( event.notification.data && event.notification.data.url ) || CFG.home;
	event.waitUntil(
		self.clients.matchAll( { type: 'window', includeUncontrolled: true } ).then( ( list ) => {
			for ( const client of list ) {
				if ( new URL( client.url ).pathname.startsWith( CFG.home ) && 'focus' in client ) {
					return client.navigate( target ).then( ( c ) => ( c || client ).focus() );
				}
			}
			return self.clients.openWindow( target );
		} )
	);
} );
