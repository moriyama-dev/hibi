import { expect, test, type Page, type Request } from '@playwright/test';
import { php, wp } from './wp';

interface ServerEntry {
	exists: boolean;
	content: string;
	mood: number | null;
	version: number;
}

const userId = () => Number( wp( 'user', 'get', 'hibi-e2e', '--field=ID' ) );

function today(): string {
	return php<string>( `echo wp_json_encode( Hibi_Day::today( ${ userId() } ) );` );
}

function serverEntry( date: string ): ServerEntry {
	return php<ServerEntry>( `echo wp_json_encode( Hibi_Entries::get( ${ userId() }, '${ date }' ) );` );
}

function writeOnServer( date: string, content: string ): void {
	php( `$e = Hibi_Entries::get( ${ userId() }, '${ date }' ); Hibi_Entries::save( ${ userId() }, '${ date }', array( 'base_version' => $e['version'], 'content' => ${ JSON.stringify( content ) } ) ); echo '1';` );
}

function clearEntries(): void {
	php(
		`foreach ( get_posts( array( 'post_type' => 'hibi_entry', 'post_status' => 'any', 'author' => ${ userId() }, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); } echo '1';`
	);
}

const isEntryPut = ( r: Request ) => r.method() === 'PUT' && /hibi\/v1\/entries\//.test( decodeURIComponent( r.url() ) );

async function body( page: Page ) {
	const el = page.getByLabel( '本文' );
	await expect( el ).toBeVisible();
	return el;
}

test.beforeEach( () => clearEntries() );

test( 'today opens ready to write and keeps the text after a reload', async ( { page } ) => {
	await page.goto( '/' );
	const el = await body( page );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toContainText( '日（' );

	await el.click();
	await el.pressSequentially( '今日は晴れ。' );
	await expect( page.getByRole( 'status' ).filter( { hasText: '保存済み' } ) ).toBeVisible( { timeout: 8000 } );

	expect( serverEntry( today() ).content ).toBe( '今日は晴れ。' );

	await page.reload();
	await expect( await body( page ) ).toHaveValue( '今日は晴れ。' );
} );

test( 'autosave waits while an IME composition is open', async ( { page } ) => {
	await page.goto( '/' );
	const el = await body( page );
	await el.click();

	const puts: string[] = [];
	page.on( 'request', ( r ) => isEntryPut( r ) && puts.push( r.postData() ?? '' ) );

	// Typing "にほんご" through an IME: the composition stays open for 3 s.
	await el.evaluate( ( node: HTMLTextAreaElement ) => {
		node.dispatchEvent( new CompositionEvent( 'compositionstart', { bubbles: true } ) );
		node.value = 'にほんご';
		node.dispatchEvent( new InputEvent( 'input', { bubbles: true, isComposing: true, inputType: 'insertCompositionText', data: 'にほんご' } ) );
	} );
	await page.waitForTimeout( 3000 );
	expect( puts ).toHaveLength( 0 );
	await expect( page.locator( '.chars' ) ).toHaveText( '0字' );

	await el.evaluate( ( node: HTMLTextAreaElement ) => {
		node.value = '日本語';
		node.dispatchEvent( new CompositionEvent( 'compositionend', { bubbles: true, data: '日本語' } ) );
	} );
	await expect.poll( () => puts.length, { timeout: 5000 } ).toBeGreaterThan( 0 );
	expect( JSON.parse( puts[ 0 ] ).content ).toBe( '日本語' );
	await expect( page.locator( '.chars' ) ).toHaveText( '3字' );
} );

test( 'mood toggles and is saved at once', async ( { page } ) => {
	await page.goto( '/' );
	await body( page );
	const good = page.getByRole( 'button', { name: '良い' } );

	await good.click();
	await expect( good ).toHaveAttribute( 'aria-pressed', 'true' );
	await expect.poll( () => serverEntry( today() ).mood, { timeout: 5000 } ).toBe( 4 );

	await good.click();
	await expect( good ).toHaveAttribute( 'aria-pressed', 'false' );
	await expect.poll( () => serverEntry( today() ).mood, { timeout: 5000 } ).toBeNull();
} );

test( 'time stamp is inserted at the cursor on its own paragraph', async ( { page } ) => {
	await page.goto( '/' );
	const el = await body( page );
	await el.click();
	await el.pressSequentially( '朝' );
	await page.getByRole( 'button', { name: '時刻' } ).click();
	await el.pressSequentially( '昼' );

	await expect( el ).toHaveValue( /^朝\n\n\d{2}:\d{2} 昼$/ );
} );

test( 'leaving the page saves immediately', async ( { page } ) => {
	await page.goto( '/' );
	const el = await body( page );
	await el.click();
	await el.pressSequentially( 'すぐ閉じる' );
	// Leave before the 1.5 s idle timer fires.
	await page.goto( 'about:blank' );

	await expect.poll( () => serverEntry( today() ).content, { timeout: 5000 } ).toBe( 'すぐ閉じる' );
} );

test( 'previous and next day', async ( { page }, info ) => {
	await page.goto( '/' );
	await body( page );
	const date = today();
	const [ y, m, d ] = date.split( '-' ).map( Number );
	const yesterday = new Date( Date.UTC( y, m - 1, d - 1 ) ).toISOString().slice( 0, 10 );

	if ( info.project.name === 'desktop' ) {
		await page.keyboard.press( 'Alt+ArrowLeft' );
	} else {
		await page.getByRole( 'link', { name: '前の日' } ).click();
	}
	await expect( page ).toHaveURL( new RegExp( `/d/${ yesterday }/$` ) );
	await expect( page.getByRole( 'link', { name: '今日へ' } ) ).toBeVisible();
	await expect( await body( page ) ).toHaveAttribute( 'placeholder', 'この日のことを書く' );

	await page.getByRole( 'link', { name: '次の日' } ).click();
	await expect( page ).toHaveURL( /\/$/ );
	await expect( page.getByRole( 'link', { name: '次の日' } ) ).toHaveCount( 0 );
} );

test( 'a conflict keeps both versions until the writer chooses', async ( { page } ) => {
	await page.goto( '/' );
	const el = await body( page );
	await el.click();
	await el.pressSequentially( 'スマホで書いた' );
	await expect( page.getByRole( 'status' ).filter( { hasText: '保存済み' } ) ).toBeVisible( { timeout: 8000 } );

	// Another device saves in the meantime.
	writeOnServer( today(), 'PCで書いた' );

	await el.pressSequentially( '。続き' );
	await expect( page.getByRole( 'alert' ) ).toContainText( '別の端末で更新されています', { timeout: 8000 } );
	expect( serverEntry( today() ).content ).toBe( 'PCで書いた' );

	await page.getByRole( 'button', { name: '両方をつなげて残す' } ).click();
	await expect( page.getByRole( 'alert' ) ).toHaveCount( 0 );
	await expect( el ).toHaveValue( 'PCで書いた\n\n---\n\nスマホで書いた。続き' );
	await expect.poll( () => serverEntry( today() ).content, { timeout: 8000 } ).toBe( 'PCで書いた\n\n---\n\nスマホで書いた。続き' );
} );

test( 'the shell embeds the entry so the first paint needs no API call', async ( { page } ) => {
	writeOnServer( today(), '埋め込み済み' );
	const apiCalls: string[] = [];
	page.on( 'request', ( r ) => /hibi\/v1/.test( decodeURIComponent( r.url() ) ) && apiCalls.push( r.url() ) );

	await page.goto( '/' );
	await expect( await body( page ) ).toHaveValue( '埋め込み済み' );
	expect( apiCalls ).toHaveLength( 0 );
} );
