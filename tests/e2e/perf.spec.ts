import { expect, test, type Page } from '@playwright/test';
import { clearEntries, resetSettings, today, writeOnServer } from './entries';

// Mid-range Android, as Lighthouse's mobile profile: 4x slower CPU, slow 4G.
async function throttle( page: Page ) {
	const cdp = await page.context().newCDPSession( page );
	await cdp.send( 'Emulation.setCPUThrottlingRate', { rate: 4 } );
	await cdp.send( 'Network.enable' );
	await cdp.send( 'Network.emulateNetworkConditions', {
		offline: false,
		latency: 150,
		downloadThroughput: ( 1.6 * 1024 * 1024 ) / 8,
		uploadThroughput: ( 750 * 1024 ) / 8,
	} );
}

test.beforeEach( () => {
	clearEntries();
	resetSettings();
} );

test( 'second start: ready to write in under a second', async ( { page }, info ) => {
	test.skip( info.project.name !== 'mobile', 'Measured on the phone profile.' );
	writeOnServer( today(), '朝の記録。'.repeat( 40 ) );

	// First start installs the service worker and caches the app.
	await page.goto( '/' );
	await page.evaluate( () => navigator.serviceWorker.ready );
	await expect.poll( () => page.evaluate( async () => !! ( await caches.match( '/__hibi_shell__' ) ) ) ).toBe( true );

	await throttle( page );
	const times: number[] = [];
	for ( let i = 0; i < 3; i++ ) {
		await page.reload();
		await page.getByLabel( '本文' ).waitFor();
		times.push( await page.evaluate( () => performance.getEntriesByName( 'hibi-editor-ready' )[ 0 ].startTime ) );
	}
	console.log( `editor ready after reload (ms): ${ times.map( Math.round ).join( ', ' ) }` );
	expect( Math.max( ...times ) ).toBeLessThan( 1000 );
} );

test( 'typing in a 10,000 character entry stays smooth', async ( { page }, info ) => {
	test.skip( info.project.name !== 'mobile', 'Measured on the phone profile.' );
	const text = 'あいうえおかきくけこ'.repeat( 1000 );
	writeOnServer( today(), text );

	await page.goto( '/' );
	const body = page.getByLabel( '本文' );
	await expect( body ).toHaveValue( text );
	await throttle( page );

	await page.evaluate( () => {
		( window as unknown as { hibiEvents: number[] } ).hibiEvents = [];
		new PerformanceObserver( ( list ) => {
			for ( const e of list.getEntries() ) {
				( window as unknown as { hibiEvents: number[] } ).hibiEvents.push( e.duration );
			}
		} ).observe( { type: 'event', durationThreshold: 16, buffered: true } as PerformanceObserverInit );
	} );

	await body.click();
	await body.press( 'End' );
	await body.pressSequentially( 'たいぷのてすとをしています', { delay: 60 } );
	await page.waitForTimeout( 2500 );

	const slow = await page.evaluate( () => ( window as unknown as { hibiEvents: number[] } ).hibiEvents );
	const worst = slow.length ? Math.max( ...slow ) : 0;
	console.log( `input events over 16 ms: ${ slow.length }, worst ${ Math.round( worst ) } ms` );
	// Under 100 ms is "good" responsiveness (INP).
	expect( worst ).toBeLessThan( 100 );
} );
