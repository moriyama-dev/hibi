import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { clearEntries, serverEntry, shift, today, writeOnServer } from './entries';
import { php } from './wp';

const BASE = process.env.HIBI_BASE_URL ?? 'http://localhost';

test.beforeEach( () => clearEntries() );

test( 'a past day opens formatted for reading, with raw HTML shown as text', async ( { page } ) => {
	const day = shift( today(), -3 );
	writeOnServer( day, '# 見出し\n**太字**と<b>タグ</b>\n<img src=x onerror=alert(1)>\n- [x] 済み\n\nA & B &lt;', { mood: 5, tags: [ '家族' ] } );

	await page.goto( `/d/${ day }/` );
	const body = page.locator( '.read-body' );
	await expect( body.locator( 'h1' ) ).toHaveText( '見出し' );
	await expect( body.locator( 'strong' ) ).toHaveText( '太字' );
	await expect( body ).toContainText( '<b>タグ</b>' );
	await expect( body.locator( 'img' ) ).toHaveCount( 0 );
	await expect( body ).toContainText( '<img src=x onerror=alert(1)>' );
	await expect( body.locator( 'input[type=checkbox]' ) ).toBeChecked();
	await expect( body ).toContainText( 'A & B &lt;' );
	await expect( page.locator( '.read-mood' ) ).toHaveText( '気分：最高' );
	await expect( page.locator( '.read-tags' ) ).toHaveText( '#家族' );
	await expect( page.getByLabel( '本文' ) ).toHaveCount( 0 );

	await page.getByRole( 'button', { name: '編集する' } ).click();
	await expect( page.getByLabel( '本文' ) ).toHaveValue( /^# 見出し/ );
} );

test( 'calendar shows written days and opens them', async ( { page } ) => {
	const t = today();
	const day = `${ t.slice( 0, 8 ) }01` === t ? t : `${ t.slice( 0, 8 ) }01`;
	writeOnServer( day, 'あ'.repeat( 900 ), { mood: 4 } );

	await page.goto( '/calendar/' );
	const cell = page.getByRole( 'gridcell', { name: /1日、書いた日、気分 良い/ } );
	await expect( cell ).toBeVisible();
	await expect( cell.locator( '.cal-dot' ) ).toHaveClass( /level-3/ );
	await expect( page.locator( '.month-stats' ) ).toContainText( '900' );
	await expect( page.locator( '.heatmap i.h3' ) ).toHaveCount( 1 );

	await cell.click();
	await expect( page ).toHaveURL( day === t ? /\/$/ : new RegExp( `/d/${ day }/$` ) );
} );

test( 'timeline lists newest first and filters by tag', async ( { page } ) => {
	const t = today();
	writeOnServer( shift( t, -1 ), '昨日の話', { tags: [ '仕事' ] } );
	writeOnServer( shift( t, -2 ), 'おとといの話', { tags: [ '子供' ] } );
	writeOnServer( shift( t, -3 ), '三日前の話', { tags: [ '子供' ] } );

	await page.goto( '/timeline/' );
	await expect( page.locator( '.card-text' ) ).toHaveText( [ '昨日の話', 'おとといの話', '三日前の話' ] );

	await page.getByLabel( 'タグで絞り込む' ).selectOption( '子供' );
	await expect( page.locator( '.card-text' ) ).toHaveText( [ 'おとといの話', '三日前の話' ] );
} );

test( 'search highlights matches and finds katakana from hiragana', async ( { page } ) => {
	writeOnServer( shift( today(), -5 ), '駅前のカフェで本を読んだ。' );
	writeOnServer( shift( today(), -6 ), '家で本を読んだ。' );

	await page.goto( '/' );
	await page.getByLabel( '本文' ).waitFor();
	await page.keyboard.press( 'ControlOrMeta+k' );
	await expect( page ).toHaveURL( /\/search\/$/ );

	await page.getByLabel( '日記を検索' ).fill( 'かふぇ 本' );
	await expect( page.locator( '.result' ) ).toHaveCount( 1 );
	await expect( page.locator( '.result mark' ) ).toHaveText( [ 'カフェ', '本' ] );
	await expect( page ).toHaveURL( /q=/ );
} );

test( 'on this day card links to last year', async ( { page } ) => {
	const t = today();
	const lastYear = `${ +t.slice( 0, 4 ) - 1 }${ t.slice( 4 ) }`;
	writeOnServer( lastYear, '去年の今日は雨だった。' );

	await page.goto( '/' );
	const card = page.locator( '.otd' );
	await expect( card ).toContainText( '1年前の今日' );
	await expect( card ).toContainText( '去年の今日は雨だった。' );
	await card.click();
	await expect( page ).toHaveURL( new RegExp( `/d/${ lastYear }/$` ) );
} );

test( 'photo upload: thumbnail shows, GPS is gone, the file is private', async ( { page, request } ) => {
	await page.goto( '/' );
	await page.getByLabel( '本文' ).waitFor();

	await page.locator( 'input[type=file]' ).setInputFiles( resolve( import.meta.dirname, 'fixtures/gps.jpg' ) );
	const thumb = page.locator( '.photo-thumb img' );
	await expect( thumb ).toHaveCount( 1, { timeout: 10000 } );
	await expect.poll( () => thumb.evaluate( ( img: HTMLImageElement ) => img.naturalWidth ) ).toBeGreaterThan( 0 );

	const photo = serverEntry( today() ).photos[ 0 ];
	const info = php<{ path: string; gps: boolean; w: number; h: number }>(
		`$m = get_post_meta( ${ photo.id }, '_hibi_photo', true ); $p = Hibi_Photos::base_dir() . '/' . $m['path'] . '.jpg'; $x = @exif_read_data( $p ); $s = getimagesize( $p ); echo wp_json_encode( array( 'path' => $p, 'gps' => is_array( $x ) && isset( $x['GPSLatitude'] ), 'w' => $s[0], 'h' => $s[1] ) );`
	);
	expect( info.gps ).toBe( false );
	expect( info.w ).toBeLessThan( info.h ); // Rotated upright from 400x200.

	// Logged in through the app: served. Logged out: refused.
	expect( ( await request.get( photo.thumb ) ).status() ).toBe( 200 );
	const anon = execFileSync( 'curl', [ '-s', '-o', '/dev/null', '-w', '%{http_code}', photo.thumb ], { encoding: 'utf8' } );
	expect( anon ).toBe( '401' );

	// The file itself cannot be fetched by URL, even with the login cookie.
	const fileUrl = BASE + info.path.replace( /^.*\/wp-content\//, '/wp-content/' );
	expect( ( await request.get( fileUrl ) ).status() ).toBe( 403 );

	// Delete through the viewer.
	await page.locator( '.photo-thumb' ).click();
	page.once( 'dialog', ( d ) => d.accept() );
	await page.getByRole( 'button', { name: '削除' } ).click();
	await expect( thumb ).toHaveCount( 0 );
	expect( serverEntry( today() ).photos ).toHaveLength( 0 );
} );

test( 'tags are added from the toolbar and saved', async ( { page } ) => {
	await page.goto( '/' );
	await page.getByLabel( '本文' ).waitFor();
	await page.getByRole( 'button', { name: 'タグ' } ).click();
	const input = page.getByLabel( 'タグを追加' );
	await input.fill( '#散歩' );
	await input.press( 'Enter' );
	await expect( page.locator( '.tag' ) ).toContainText( '#散歩' );
	await expect.poll( () => serverEntry( today() ).tags, { timeout: 5000 } ).toEqual( [ '散歩' ] );
} );
