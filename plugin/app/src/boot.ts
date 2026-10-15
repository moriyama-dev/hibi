// The data embedded in the shell page. Kept free of imports so any module
// can read it without import cycles.

import type { Bootstrap } from './types';

function readBootstrap(): Bootstrap {
	const el = document.getElementById( 'hibi-bootstrap' );
	if ( ! el || ! el.textContent ) {
		throw new Error( 'Missing bootstrap data' );
	}
	return JSON.parse( el.textContent ) as Bootstrap;
}

export const boot = readBootstrap();
