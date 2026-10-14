import { wp } from './wp';

/** Deletes the e2e user and, with it, every entry the tests wrote. */
export default async function globalTeardown() {
	let id: string;
	try {
		id = wp( 'user', 'get', 'hibi-e2e', '--field=ID' );
	} catch {
		return;
	}
	if ( wp( 'user', 'meta', 'get', id, '_hibi_e2e' ) === '1' ) {
		wp( 'user', 'delete', id, '--yes' );
	}
}
