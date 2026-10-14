import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

export const SITE_PATH = process.env.HIBI_SITE_PATH ?? '/var/www/html';

export function wp( ...args: string[] ): string {
	return execFileSync( 'wp', [ ...args, `--path=${ SITE_PATH }` ], { encoding: 'utf8' } ).trim();
}

export interface LoginCookie {
	user_id: number;
	name: string;
	value: string;
}

export function loginCookie(): LoginCookie {
	return JSON.parse( wp( 'eval-file', resolve( import.meta.dirname, 'login-cookie.php' ) ) );
}

/** Runs PHP inside WordPress and returns the JSON it prints. */
export function php<T>( code: string ): T {
	return JSON.parse( wp( 'eval', code ) );
}
