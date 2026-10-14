import { php, wp } from './wp';

export interface ServerEntry {
	exists: boolean;
	content: string;
	mood: number | null;
	tags: string[];
	version: number;
	photos: { id: number; full: string; thumb: string }[];
}

export const userId = () => Number( wp( 'user', 'get', 'hibi-e2e', '--field=ID' ) );

export function today(): string {
	return php<string>( `echo wp_json_encode( Hibi_Day::today( ${ userId() } ) );` );
}

export function shift( date: string, days: number ): string {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	return new Date( Date.UTC( y, m - 1, d + days ) ).toISOString().slice( 0, 10 );
}

export function serverEntry( date: string ): ServerEntry {
	return php<ServerEntry>( `echo wp_json_encode( Hibi_Entries::get( ${ userId() }, '${ date }' ) );` );
}

export function writeOnServer( date: string, content: string, extra: { mood?: number; tags?: string[] } = {} ): void {
	const fields = JSON.stringify( { content, ...extra } );
	php(
		`$f = json_decode( ${ JSON.stringify( fields ) }, true ); $e = Hibi_Entries::get( ${ userId() }, '${ date }' ); $f['base_version'] = $e['version']; Hibi_Entries::save( ${ userId() }, '${ date }', $f ); echo '1';`
	);
}

export function clearEntries(): void {
	php(
		`foreach ( get_posts( array( 'post_type' => 'hibi_entry', 'post_status' => 'any', 'author' => ${ userId() }, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); } echo '1';`
	);
}
