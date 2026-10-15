import { execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { expect, test } from '@playwright/test';
import { clearEntries, resetSettings, serverEntry, shift, today, userId, writeOnServer } from './entries';
import { loginCookie, php } from './wp';

test.beforeEach( () => {
	clearEntries();
	resetSettings();
} );

test( 'the interface follows the user’s language', async ( { page } ) => {
	php( `update_user_meta( ${ userId() }, 'locale', 'en_US' ); echo '1';` );
	try {
		await page.goto( '/' );
		await expect( page.getByRole( 'navigation', { name: 'Main' } ) ).toContainText( 'Look back' );
		await expect( page.getByLabel( 'Entry' ) ).toHaveAttribute( 'placeholder', 'How was your day?' );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', 'en-US' );
	} finally {
		php( `update_user_meta( ${ userId() }, 'locale', 'ja' ); echo '1';` );
	}
	await page.goto( '/' );
	await expect( page.getByRole( 'navigation', { name: 'メイン' } ) ).toContainText( '振り返り' );
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', 'ja' );
} );

test( 'display settings apply at once and stay after a reload', async ( { page } ) => {
	await page.goto( '/settings/' );
	const saved = () => page.waitForResponse( ( r ) => r.request().method() === 'PUT' && r.url().includes( 'settings' ) );

	let done = saved();
	await page.getByRole( 'radio', { name: '大' } ).check();
	await done;
	done = saved();
	await page.getByRole( 'radio', { name: 'ダーク' } ).check();
	await done;
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-font-size', 'L' );
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );

	await page.reload();
	await expect( page.locator( 'html' ) ).toHaveAttribute( 'data-theme', 'dark' );
	await expect( page.getByRole( 'radio', { name: '大' } ) ).toBeChecked();
} );

test( 'a question is offered on an empty day and can be turned off', async ( { page } ) => {
	await page.goto( '/' );
	const prompt = page.locator( '.prompt' );
	await expect( prompt ).toBeVisible();
	const question = await prompt.innerText();
	await prompt.click();
	await expect( page.getByLabel( '本文' ) ).toHaveValue( `> ${ question }\n\n` );
	await expect( prompt ).toHaveCount( 0 );

	php( `Hibi_Settings::update( ${ userId() }, array( 'prompts_enabled' => false ) ); echo '1';` );
	clearEntries();
	await page.goto( '/' );
	await page.getByLabel( '本文' ).waitFor();
	await expect( prompt ).toHaveCount( 0 );
} );

test( 'export downloads every entry as JSON, Markdown and photos', async ( { page } ) => {
	writeOnServer( shift( today(), -1 ), '昨日の日記 "引用"', { mood: 4, tags: [ '家族' ] } );
	php( `Hibi_Photos::store( ${ userId() }, '${ shift( today(), -1 ) }', '${ resolve( import.meta.dirname, 'fixtures/gps.jpg' ) }' ) ? print '1' : print '0';` );

	await page.goto( '/settings/' );
	const download = page.waitForEvent( 'download' );
	await page.getByRole( 'button', { name: 'すべて書き出す（ZIP）' } ).click();
	const file = join( mkdtempSync( join( tmpdir(), 'hibi-export-' ) ), 'export.zip' );
	await ( await download ).saveAs( file );

	const list = execFileSync( 'unzip', [ '-Z1', file ], { encoding: 'utf8' } );
	const day = shift( today(), -1 );
	expect( list ).toContain( 'entries.json' );
	expect( list ).toContain( `markdown/${ day.slice( 0, 4 ) }/${ day }.md` );
	expect( list ).toMatch( new RegExp( `photos/${ day }-\\d+\\.jpg` ) );

	const json = JSON.parse( execFileSync( 'unzip', [ '-p', file, 'entries.json' ], { encoding: 'utf8' } ) );
	expect( json.schema_version ).toBe( 1 );
	expect( json.entries[ 0 ] ).toMatchObject( { date: day, content: '昨日の日記 "引用"', mood: 4, tags: [ '家族' ] } );

	const md = execFileSync( 'unzip', [ '-p', file, `markdown/${ day.slice( 0, 4 ) }/${ day }.md` ], { encoding: 'utf8' } );
	expect( md ).toContain( `date: ${ day }` );
	expect( md ).toContain( 'tags: ["家族"]' );
	expect( md ).toContain( '昨日の日記 "引用"' );
} );

test( 'import adds new days and skips days that already have an entry', async ( { page } ) => {
	const a = shift( today(), -10 );
	const b = shift( today(), -11 );
	writeOnServer( a, 'もとからある' );
	const file = join( mkdtempSync( join( tmpdir(), 'hibi-import-' ) ), 'entries.json' );
	writeFileSync(
		file,
		JSON.stringify( {
			schema_version: 1,
			entries: [
				{ date: a, content: '上書きしない', mood: null, tags: [] },
				{ date: b, content: '取り込んだ', mood: 2, tags: [ '旅行' ] },
			],
		} )
	);

	await page.goto( '/settings/' );
	await page.getByLabel( 'Hibi の ZIP または JSON を取り込む' ).setInputFiles( file );
	await expect( page.locator( '.settings' ) ).toContainText( '1日分を取り込み、1日分を飛ばしました。' );
	expect( serverEntry( a ).content ).toBe( 'もとからある' );
	expect( serverEntry( b ) ).toMatchObject( { content: '取り込んだ', mood: 2, tags: [ '旅行' ] } );
} );

test( 'log out', async ( { browser, baseURL } ) => {
	// A session of its own: logging out ends the session, and the other
	// tests share theirs.
	const cookie = loginCookie();
	const context = await browser.newContext( { storageState: { cookies: [ { name: cookie.name, value: cookie.value, domain: new URL( baseURL as string ).hostname, path: '/', expires: -1, httpOnly: true, secure: false, sameSite: 'Lax' } ], origins: [] } } );
	const page = await context.newPage();
	await page.goto( '/settings/' );
	await page.getByRole( 'button', { name: 'ログアウト' } ).click();
	await expect( page ).toHaveURL( /wp-login\.php/ );
	// Logged out: the app is no longer reachable.
	await page.goto( '/' );
	await expect( page ).toHaveURL( /wp-login\.php/ );
	await context.close();
} );
