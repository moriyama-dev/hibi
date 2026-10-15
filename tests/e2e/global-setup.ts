import { writeFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { loginCookie, wp } from './wp';

export const STATE = resolve( import.meta.dirname, '../../test-results/auth.json' );

export default async function globalSetup() {
	const base = new URL( process.env.HIBI_BASE_URL ?? 'http://localhost' );
	const home = new URL( wp( 'option', 'get', 'home' ) );
	if ( base.host !== home.host ) {
		throw new Error( `HIBI_BASE_URL (${ base.origin }) is not the site (${ home.origin }). Run with HIBI_BASE_URL=${ home.origin }.` );
	}
	const theme = wp( 'theme', 'list', '--status=active', '--field=name' );
	if ( theme !== 'hibi' ) {
		throw new Error( `The active theme is "${ theme }"; the e2e tests need "hibi".` );
	}
	const cookie = loginCookie();
	const host = new URL( process.env.HIBI_BASE_URL ?? 'http://localhost' ).hostname;
	mkdirSync( resolve( STATE, '..' ), { recursive: true } );
	writeFileSync(
		STATE,
		JSON.stringify( {
			cookies: [ { name: cookie.name, value: cookie.value, domain: host, path: '/', expires: -1, httpOnly: true, secure: false, sameSite: 'Lax' } ],
			origins: [],
		} )
	);
}
