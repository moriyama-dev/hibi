import { expect, test } from '@playwright/test';
import { clearEntries, resetSettings, today, userId } from './entries';
import { php } from './wp';

interface Place {
	lat: number;
	lng: number;
	label: string;
}

const serverPlace = () => php<{ location: Place | null }>( `echo wp_json_encode( Hibi_Entries::get( ${ userId() }, '${ today() }' ) );` ).location;
const isEntryPut = ( url: string, method: string ) => method === 'PUT' && /hibi\/v1\/entries\//.test( decodeURIComponent( url ) );

test.use( { permissions: [ 'geolocation' ], geolocation: { latitude: 43.6532, longitude: -79.3832 } } );

test.beforeEach( () => {
	clearEntries();
	resetSettings();
} );

test( 'no place is recorded until it is turned on', async ( { page } ) => {
	await page.goto( '/' );
	await page.getByLabel( '本文' ).pressSequentially( '場所なし' );
	await expect( page.getByRole( 'status' ).filter( { hasText: '保存済み' } ) ).toBeVisible( { timeout: 8000 } );
	await expect( page.locator( '.chip.location' ) ).toHaveCount( 0 );
	expect( serverPlace() ).toBeNull();
} );

test( 'turning it on adds today’s place, named after the nearby weather place', async ( { page } ) => {
	php( `Hibi_Settings::update( ${ userId() }, array( 'weather_enabled' => false, 'weather_lat' => 43.65, 'weather_lng' => -79.38, 'weather_place' => 'Toronto, Ontario' ) ); echo '1';` );
	await page.goto( '/settings/' );
	const saved = page.waitForResponse( ( r ) => r.request().method() === 'PUT' && r.url().includes( 'settings' ) );
	await page.getByLabel( '今日の日記に現在地を添える' ).check();
	expect( ( await saved ).status() ).toBe( 200 );
	await expect( page.getByLabel( '今日の日記に現在地を添える' ) ).toBeChecked();

	await page.goto( '/' );
	const chip = page.locator( '.chip.location' );
	await expect( chip ).toContainText( 'Toronto, Ontario' );
	await expect( chip.getByRole( 'link' ) ).toHaveAttribute( 'href', /openstreetmap\.org\/\?mlat=43\.6532&mlon=-79\.3832/ );
	await expect.poll( serverPlace, { timeout: 8000 } ).toEqual( { lat: 43.6532, lng: -79.3832, label: 'Toronto, Ontario' } );

	// Far from the weather place, the coordinates are shown instead of a name.
	clearEntries();
	await page.context().setGeolocation( { latitude: 35.6812, longitude: 139.7671 } );
	await page.reload();
	await expect( chip ).toContainText( '35.681, 139.767' );
	await expect.poll( () => serverPlace()?.label, { timeout: 8000 } ).toBe( '' );
} );

test( 'a removed place stays removed after a reload', async ( { page } ) => {
	php( `Hibi_Settings::update( ${ userId() }, array( 'location_enabled' => true ) ); echo '1';` );
	await page.goto( '/' );
	const chip = page.locator( '.chip.location' );
	await expect( chip ).toBeVisible();
	await expect.poll( serverPlace, { timeout: 8000 } ).not.toBeNull();

	const put = page.waitForRequest( ( r ) => isEntryPut( r.url(), r.method() ) );
	await page.getByRole( 'button', { name: '場所を外す' } ).click();
	await put;
	await expect( chip ).toHaveCount( 0 );
	await expect.poll( serverPlace, { timeout: 8000 } ).toBeNull();

	await page.reload();
	await expect( page.getByLabel( '本文' ) ).toBeVisible();
	await page.waitForTimeout( 1500 );
	await expect( chip ).toHaveCount( 0 );
	expect( serverPlace() ).toBeNull();
} );

test( 'a refused position leaves the setting off', async ( { page, context } ) => {
	await context.clearPermissions();
	await page.goto( '/settings/' );
	await page.getByLabel( '今日の日記に現在地を添える' ).check();
	await expect( page.locator( '.settings' ) ).toContainText( '現在地を取得できませんでした' );
	await expect( page.getByLabel( '今日の日記に現在地を添える' ) ).not.toBeChecked();
	expect( php<{ location_enabled: boolean }>( `echo wp_json_encode( Hibi_Settings::get( ${ userId() } ) );` ).location_enabled ).toBe( false );
} );
