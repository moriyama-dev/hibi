// Reminder notifications: subscribe this device with the server's key.

import { api } from './api';
import { boot } from './store';

export type PushStatus = 'unsupported' | 'insecure' | 'denied' | 'off' | 'on';

function keyBytes( base64url: string ): Uint8Array<ArrayBuffer> {
	const pad = '='.repeat( ( 4 - ( base64url.length % 4 ) ) % 4 );
	const raw = atob( ( base64url + pad ).replace( /-/g, '+' ).replace( /_/g, '/' ) );
	const out = new Uint8Array( new ArrayBuffer( raw.length ) );
	for ( let i = 0; i < raw.length; i++ ) {
		out[ i ] = raw.charCodeAt( i );
	}
	return out;
}

export async function pushStatus(): Promise<PushStatus> {
	if ( ! window.isSecureContext ) {
		return 'insecure';
	}
	if ( ! ( 'serviceWorker' in navigator ) || ! ( 'PushManager' in window ) || ! boot.push.public_key ) {
		return 'unsupported';
	}
	if ( Notification.permission === 'denied' ) {
		return 'denied';
	}
	const reg = await navigator.serviceWorker.getRegistration();
	const sub = reg ? await reg.pushManager.getSubscription() : null;
	return sub ? 'on' : 'off';
}

export async function enablePush(): Promise<PushStatus> {
	if ( ( await Notification.requestPermission() ) !== 'granted' ) {
		return pushStatus();
	}
	const reg = await navigator.serviceWorker.ready;
	const sub =
		( await reg.pushManager.getSubscription() ) ??
		( await reg.pushManager.subscribe( { userVisibleOnly: true, applicationServerKey: keyBytes( boot.push.public_key ) } ) );
	await api.pushSubscribe( sub.toJSON() );
	return 'on';
}

export async function disablePush(): Promise<PushStatus> {
	const reg = await navigator.serviceWorker.getRegistration();
	const sub = reg ? await reg.pushManager.getSubscription() : null;
	if ( sub ) {
		await api.pushUnsubscribe( sub.endpoint ).catch( () => undefined );
		await sub.unsubscribe();
	}
	return pushStatus();
}

export function registerServiceWorker(): void {
	if ( ! ( 'serviceWorker' in navigator ) || ! window.isSecureContext ) {
		return;
	}
	const home = boot.api.home_path;
	window.addEventListener( 'load', () => {
		navigator.serviceWorker.register( `${ home }hibi-sw.js`, { scope: home } ).catch( () => undefined );
	} );
}
