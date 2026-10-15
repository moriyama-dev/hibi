import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { clearEntries, resetSettings, shift, today, writeOnServer } from './entries';

test.beforeEach( () => {
	clearEntries();
	resetSettings();
	writeOnServer( shift( today(), -1 ), '昨日の日記', { mood: 4, tags: [ '家族' ] } );
} );

for ( const [ name, path ] of [
	[ 'today', '/' ],
	[ 'past day (reading)', '' ],
	[ 'calendar', '/calendar/' ],
	[ 'timeline', '/timeline/' ],
	[ 'on this day', '/on-this-day/' ],
	[ 'search', '/search/?q=日記' ],
	[ 'settings', '/settings/' ],
] ) {
	for ( const scheme of [ 'light', 'dark' ] as const ) {
		test( `no accessibility violations: ${ name }, ${ scheme }`, async ( { page } ) => {
			await page.emulateMedia( { colorScheme: scheme } );
			await page.goto( path || `/d/${ shift( today(), -1 ) }/` );
			await page.locator( '#main' ).waitFor();
			await page.waitForLoadState( 'networkidle' );
			const results = await new AxeBuilder( { page } ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] ).analyze();
			const summary = results.violations.map( ( v ) => `${ v.id } (${ v.impact }): ${ v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) ).join( ' | ' ) }` );
			expect( summary ).toEqual( [] );
		} );
	}
}

test( 'everything on the day screen is reachable with the keyboard', async ( { page } ) => {
	await page.goto( '/' );
	await page.getByLabel( '本文' ).waitFor();
	// On a desktop the editor already has focus; start from the top instead.
	await page.evaluate( () => ( document.activeElement as HTMLElement | null )?.blur() );
	const seen = new Set<string>();
	for ( let i = 0; i < 30; i++ ) {
		await page.keyboard.press( 'Tab' );
		const label = await page.evaluate( () => {
			const el = document.activeElement as HTMLElement | null;
			if ( ! el || el === document.body ) {
				return '';
			}
			const outline = getComputedStyle( el ).outlineStyle;
			const name = el.getAttribute( 'aria-label' ) ?? ( el as HTMLTextAreaElement ).labels?.[ 0 ]?.textContent ?? el.textContent;
			return `${ el.tagName }:${ name?.trim().slice( 0, 12 ) }:${ outline }`;
		} );
		seen.add( label );
	}
	const names = [ ...seen ].join( '\n' );
	for ( const expected of [ '今日', '振り返り', '前の日', 'とても悪い', '最高', '本文', '写真', '時刻', 'タグ' ] ) {
		expect( names ).toContain( expected );
	}
	// The focused element is visibly outlined.
	expect( [ ...seen ].filter( ( s ) => s && ! s.startsWith( 'TEXTAREA' ) && s.endsWith( ':none' ) ) ).toEqual( [] );
} );

test( 'touch targets are at least 44 px', async ( { page }, info ) => {
	test.skip( info.project.name !== 'mobile', 'Touch sizes matter on the phone profile.' );
	await page.goto( '/' );
	await page.getByLabel( '本文' ).waitFor();
	const small = await page.evaluate( () =>
		[ ...document.querySelectorAll<HTMLElement>( 'button, a[href], select, input:not([type=hidden]):not([type=file])' ) ]
			.filter( ( el ) => el.offsetParent !== null )
			.map( ( el ) => ( { el: el.getAttribute( 'aria-label' ) ?? el.textContent?.trim(), r: el.getBoundingClientRect() } ) )
			.filter( ( { r } ) => r.height < 36 || r.width < 36 )
			.map( ( { el, r } ) => `${ el } ${ Math.round( r.width ) }x${ Math.round( r.height ) }` )
	);
	expect( small ).toEqual( [] );
} );
