import { expect, test, type Page } from '@playwright/test';
import { clearEntries, resetSettings, serverEntry, shift, today, writeOnServer } from './entries';
import { php } from './wp';

test.beforeEach( () => {
	clearEntries();
	resetSettings();
} );

/** Opens the app and waits until the service worker controls the page. */
async function openControlled( page: Page, path = '/' ) {
	await page.goto( path );
	await page.evaluate( () => navigator.serviceWorker.ready );
	if ( ! ( await page.evaluate( () => !! navigator.serviceWorker.controller ) ) ) {
		await page.reload();
	}
	await expect.poll( () => page.evaluate( () => !! navigator.serviceWorker.controller ) ).toBe( true );
	// The worker stores the shell itself when it activates.
	await expect.poll( () => page.evaluate( async () => !! ( await caches.match( '/__hibi_shell__' ) ) ) ).toBe( true );
	await page.getByLabel( '本文' ).waitFor();
}

test( 'installable: manifest, icons and service worker', async ( { page, request } ) => {
	await page.goto( '/' );
	const href = await page.locator( 'link[rel=manifest]' ).getAttribute( 'href' );
	expect( await page.locator( 'link[rel=manifest]' ).getAttribute( 'crossorigin' ) ).toBe( 'use-credentials' );
	const manifest = await ( await request.get( href as string ) ).json();
	expect( manifest ).toMatchObject( { name: 'Hibi', display: 'standalone', start_url: '/' } );
	for ( const icon of manifest.icons ) {
		expect( ( await request.get( icon.src ) ).status() ).toBe( 200 );
	}
	expect( manifest.icons.some( ( i: { purpose: string } ) => i.purpose === 'maskable' ) ).toBe( true );

	const scope = await page.evaluate( async () => ( await navigator.serviceWorker.ready ).scope );
	expect( new URL( scope ).pathname ).toBe( '/' );
} );

test( 'written offline, kept across a reload, sent when back online', async ( { page, context } ) => {
	await openControlled( page );
	await context.setOffline( true );

	const body = page.getByLabel( '本文' );
	await body.click();
	await body.pressSequentially( '機内モードで書いた' );
	await expect( page.locator( '.save-status' ) ).toHaveText( /オフライン|保存できません/, { timeout: 8000 } );

	// Reload while still offline: the cached shell opens and the draft is back.
	await page.reload();
	await expect( page.getByLabel( '本文' ) ).toHaveValue( '機内モードで書いた' );
	// No server check here: Chromium's offline emulation does not hold back
	// the keepalive save sent while the page unloads, so it may already be there.

	await context.setOffline( false );
	await expect.poll( () => serverEntry( today() ).content, { timeout: 15000 } ).toBe( '機内モードで書いた' );
	// Same text on both sides is not a conflict, even if the versions differ.
	await expect( page.locator( '.save-status' ) ).toHaveText( '保存済み', { timeout: 15000 } );
	await expect( page.getByRole( 'alert' ) ).toHaveCount( 0 );

	// The draft is gone once the server has it.
	await expect.poll( () => page.evaluate( () => new Promise( ( r ) => {
		const req = indexedDB.open( Object.keys( {} ).length ? '' : `hibi-${ ( JSON.parse( document.getElementById( 'hibi-bootstrap' )!.textContent! ) ).user.id }` );
		req.onsuccess = () => {
			const all = req.result.transaction( 'drafts' ).objectStore( 'drafts' ).getAll();
			all.onsuccess = () => r( all.result.length );
		};
	} ) ) ).toBe( 0 );
} );

test( 'a day edited offline is sent in the background after leaving it', async ( { page, context } ) => {
	const past = shift( today(), -2 );
	await openControlled( page );
	await context.setOffline( true );

	await page.goto( `/d/${ past }/` ).catch( () => undefined );
	// Offline and never loaded: the app offers to write anyway.
	await page.getByRole( 'button', { name: /このまま書く/ } ).click();
	const body = page.getByLabel( '本文' );
	await body.click();
	await body.pressSequentially( 'おととい分' );
	await page.getByRole( 'link', { name: '今日へ' } ).click();
	await expect( page.locator( '.sync-notice' ) ).toContainText( '未送信の日記が 1 日分' );

	await context.setOffline( false );
	await expect.poll( () => serverEntry( past ).content, { timeout: 15000 } ).toBe( 'おととい分' );
	await expect( page.locator( '.sync-notice' ) ).toHaveCount( 0 );
} );

test( 'an offline edit that collides with another device asks before saving', async ( { page, context } ) => {
	writeOnServer( today(), '最初' );
	await openControlled( page );
	await context.setOffline( true );

	const body = page.getByLabel( '本文' );
	await body.click();
	await body.press( 'End' );
	await body.pressSequentially( '＋スマホ' );
	await expect( page.locator( '.save-status' ) ).toHaveText( /オフライン|保存できません/, { timeout: 8000 } );

	writeOnServer( today(), '最初＋PC' );
	await context.setOffline( false );

	await expect( page.getByRole( 'alert' ) ).toContainText( '別の端末で更新されています', { timeout: 15000 } );
	expect( serverEntry( today() ).content ).toBe( '最初＋PC' );

	await page.getByRole( 'button', { name: '両方をつなげて残す' } ).click();
	await expect.poll( () => serverEntry( today() ).content, { timeout: 8000 } ).toBe( '最初＋PC\n\n---\n\n最初＋スマホ' );
} );

test( 'weather place from the settings fills in today', async ( { page } ) => {
	await page.goto( '/settings/' );
	await page.getByLabel( '都市名' ).fill( 'トロント' );
	await page.getByRole( 'button', { name: '探す' } ).click();
	const saved = page.waitForResponse( ( r ) => r.request().method() === 'PUT' && r.url().includes( 'settings' ) );
	await page.locator( '.places button' ).first().click();
	await expect( page.locator( '.settings' ) ).toContainText( '場所：トロント、オンタリオ州' );
	expect( ( await saved ).status() ).toBe( 200 );

	await page.goto( '/' );
	// Open-Meteo answers from the network; allow for a slow first response.
	await expect( page.locator( '.chip.weather' ) ).toBeVisible( { timeout: 20000 } );
	await expect.poll( () => php<{ weather: { source: string } | null }>( `echo wp_json_encode( Hibi_Entries::get( (int) get_user_by( 'login', 'hibi-e2e' )->ID, '${ today() }' ) );` ).weather?.source, { timeout: 8000 } ).toBe( 'open-meteo' );
} );
