import { signal } from '@preact/signals';
import { isDate } from './date';
import { boot, today } from './store';

export type Route =
	| { name: 'day'; date: string; isToday: boolean }
	| { name: 'calendar' | 'timeline' | 'search' | 'on-this-day' | 'settings' }
	| { name: 'not-found' };

const home = boot.api.home_path.replace( /\/$/, '' );

export function parse( pathname: string ): Route {
	let path = pathname.startsWith( home ) ? pathname.slice( home.length ) : pathname;
	path = path.replace( /\/+$/, '' ) || '/';

	if ( path === '/' ) {
		return { name: 'day', date: today.value, isToday: true };
	}
	const day = /^\/d\/(\d{4}-\d{2}-\d{2})$/.exec( path );
	if ( day && isDate( day[ 1 ] ) ) {
		return { name: 'day', date: day[ 1 ], isToday: day[ 1 ] === today.value };
	}
	const named = /^\/(calendar|timeline|search|on-this-day|settings)$/.exec( path );
	if ( named ) {
		return { name: named[ 1 ] as 'calendar' };
	}
	return { name: 'not-found' };
}

/** App URLs end in "/", as WordPress canonical URLs do. */
export function href( path: string ): string {
	return home + ( path === '/' ? '/' : path.replace( /\/?$/, '/' ) );
}

export function dayHref( date: string ): string {
	return date === today.value ? href( '/' ) : href( `/d/${ date }` );
}

export const route = signal<Route>( parse( location.pathname ) );

type Guard = () => Promise<void> | void;
const guards = new Set<Guard>();

/** Runs before every in-app navigation, e.g. to flush the editor. */
export function beforeNavigate( guard: Guard ): () => void {
	guards.add( guard );
	return () => guards.delete( guard );
}

async function runGuards(): Promise<void> {
	await Promise.all( [ ...guards ].map( ( g ) => g() ) );
}

export async function navigate( to: string, replace = false ): Promise<void> {
	await runGuards();
	if ( replace ) {
		history.replaceState( null, '', to );
	} else {
		history.pushState( null, '', to );
	}
	route.value = parse( location.pathname );
	window.scrollTo( 0, 0 );
}

export function startRouter(): void {
	window.addEventListener( 'popstate', async () => {
		await runGuards();
		route.value = parse( location.pathname );
	} );

	// Same-origin links inside the app switch screens without a page load.
	document.addEventListener( 'click', ( event ) => {
		const a = ( event.target as Element ).closest?.( 'a[data-nav]' ) as HTMLAnchorElement | null;
		if ( ! a || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}
		event.preventDefault();
		navigate( a.pathname );
	} );
}
