// UI text comes translated from the server (theme/inc/strings.php).

import { boot } from './boot';

const strings: Record<string, string> = boot.i18n ?? {};

/**
 * Translated text for a key, with sprintf-style placeholders filled in:
 * %s and %d in order, or %1$s / %2$d by position.
 */
export function t( key: string, ...args: ( string | number )[] ): string {
	const text = strings[ key ] ?? key;
	let next = 0;
	return text.replace( /%(?:(\d+)\$)?([sd%])/g, ( match, position: string | undefined, type: string ) => {
		if ( type === '%' ) {
			return '%';
		}
		const value = position ? args[ +position - 1 ] : args[ next++ ];
		return value === undefined ? match : String( value );
	} );
}

/** The user's language for Intl, e.g. "ja" or "en-US". */
export const locale = boot.locale || 'en-US';

export function formatNumber( n: number ): string {
	return n.toLocaleString( locale );
}
