import { expect, test } from '@playwright/test';
import { clearEntries, resetSettings, serverEntry, today } from './entries';
import { wp } from './wp';

// The plugin on its own, with a theme that knows nothing about Hibi: the
// app lives at /journal/ and the front page stays the theme's.
test.describe.serial( 'plugin without the Hibi theme', () => {
	test.beforeAll( () => {
		wp( 'theme', 'activate', 'twentytwentyfive' );
	} );
	test.afterAll( () => {
		wp( 'theme', 'activate', 'hibi' );
	} );
	test.beforeEach( () => {
		clearEntries();
		resetSettings();
	} );

	test( 'the app opens at /journal/ and saves', async ( { page } ) => {
		await page.goto( '/journal/' );
		const body = page.getByLabel( '本文' );
		await body.click();
		await body.pressSequentially( 'テーマなしで書いた' );
		await expect.poll( () => serverEntry( today() ).content, { timeout: 8000 } ).toBe( 'テーマなしで書いた' );

		// Screens live below /journal/.
		await page.getByRole( 'link', { name: '振り返り' } ).click();
		await expect( page ).toHaveURL( /\/journal\/calendar\/$/ );
		await page.reload();
		await expect( page.locator( '.calendar' ) ).toBeVisible();
	} );

	test( 'the theme keeps the front page and stays out of the app', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page.getByLabel( '本文' ) ).toHaveCount( 0 );

		await page.goto( '/journal/' );
		await page.getByLabel( '本文' ).waitFor();
		// The shell is the plugin's own page: no theme stylesheet, no admin bar.
		const sheets = await page.evaluate( () => [ ...document.querySelectorAll( 'link[rel=stylesheet]' ) ].map( ( l ) => ( l as HTMLLinkElement ).href ) );
		expect( sheets.every( ( href ) => href.includes( '/plugins/hibi-journal/' ) ) ).toBe( true );
		await expect( page.locator( '#wpadminbar' ) ).toHaveCount( 0 );
	} );

	test( 'service worker and manifest are scoped to /journal/', async ( { page, request } ) => {
		await page.goto( '/journal/' );
		const scope = await page.evaluate( async () => ( await navigator.serviceWorker.ready ).scope );
		expect( new URL( scope ).pathname ).toBe( '/journal/' );
		const manifest = await ( await request.get( '/hibi-manifest.webmanifest' ) ).json();
		expect( manifest ).toMatchObject( { start_url: '/journal/', scope: '/journal/' } );
	} );
} );
