import { t } from './i18n';
import type { EntryLocation, Settings } from './types';

/** Whether this browser can be asked for its position at all. */
export function canLocate(): boolean {
	return window.isSecureContext && 'geolocation' in navigator;
}

/** The device position, or a rejection when it is unavailable or refused. */
export function currentPosition( maximumAge = 30 * 60 * 1000 ): Promise<GeolocationPosition> {
	return new Promise( ( resolve, reject ) => {
		if ( ! canLocate() ) {
			reject( new Error( 'unavailable' ) );
			return;
		}
		navigator.geolocation.getCurrentPosition( resolve, reject, { maximumAge, timeout: 10000 } );
	} );
}

/** Distance in kilometres between two points. */
export function distanceKm( lat1: number, lng1: number, lat2: number, lng2: number ): number {
	const rad = Math.PI / 180;
	const dLat = ( lat2 - lat1 ) * rad;
	const dLng = ( lng2 - lng1 ) * rad;
	const a = Math.sin( dLat / 2 ) ** 2 + Math.cos( lat1 * rad ) * Math.cos( lat2 * rad ) * Math.sin( dLng / 2 ) ** 2;
	return 12742 * Math.asin( Math.sqrt( a ) );
}

/**
 * The entry's place. No address lookup service is used: the label is the
 * weather place from the settings when the position is near it, and empty
 * otherwise (the coordinates are shown instead).
 */
export function locationFrom( lat: number, lng: number, s: Settings ): EntryLocation {
	let label = '';
	if ( s.weather_place && s.weather_place !== t( 'settings.here' ) && s.weather_lat !== null && s.weather_lng !== null && distanceKm( lat, lng, s.weather_lat, s.weather_lng ) <= 25 ) {
		label = s.weather_place;
	}
	return { lat: Math.round( lat * 10000 ) / 10000, lng: Math.round( lng * 10000 ) / 10000, label };
}

/** Text shown for a place. */
export function locationText( loc: EntryLocation ): string {
	return loc.label || `${ loc.lat.toFixed( 3 ) }, ${ loc.lng.toFixed( 3 ) }`;
}

/** OpenStreetMap page for a place; opened only when the link is followed. */
export function mapUrl( loc: EntryLocation ): string {
	return `https://www.openstreetmap.org/?mlat=${ loc.lat }&mlon=${ loc.lng }#map=15/${ loc.lat }/${ loc.lng }`;
}

const removedKey = ( date: string ) => `hibi-place-removed-${ date }`;

/** Whether the writer removed the place of this day on this device. */
export function placeRemoved( date: string ): boolean {
	try {
		return localStorage.getItem( removedKey( date ) ) === '1';
	} catch {
		return false;
	}
}

/** Remembers that the place of this day was removed, so it is not added again. */
export function rememberPlaceRemoved( date: string ): void {
	try {
		localStorage.setItem( removedKey( date ), '1' );
	} catch {
		// Without storage the place may come back on the next visit; nothing is lost.
	}
}
