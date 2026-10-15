import { useEffect, useRef, useState } from 'preact/hooks';
import { api, ApiError } from '../api';
import { drafts } from '../drafts';
import { t } from '../i18n';
import { disablePush, enablePush, pushStatus, type PushStatus } from '../push';
import { boot, settings } from '../store';
import type { Place, Settings } from '../types';

async function save( changes: Partial<Settings> ): Promise<void> {
	settings.value = { ...settings.value, ...changes };
	settings.value = await api.putSettings( changes );
}

export function SettingsView() {
	return (
		<div class="settings">
			<h1 class="review-title">{ t( 'settings.title' ) }</h1>
			<ReminderSection />
			<DisplaySection />
			<WeatherSection />
			<DaySection />
			<InstallSection />
			<DataSection />
			<LogoutSection />
		</div>
	);
}

interface Choice<T> {
	value: T;
	label: string;
}

/** A row of radio buttons that saves on change. */
function Choices<K extends keyof Settings>( { name, legend, choices }: { name: K; legend: string; choices: Choice<Settings[ K ]>[] } ) {
	const current = settings.value[ name ];
	return (
		<fieldset class="choices">
			<legend>{ legend }</legend>
			{ choices.map( ( c ) => (
				<label key={ String( c.value ) } class="choice">
					<input type="radio" name={ String( name ) } checked={ current === c.value } onChange={ () => save( { [ name ]: c.value } as Partial<Settings> ) } />
					{ c.label }
				</label>
			) ) }
		</fieldset>
	);
}

function DisplaySection() {
	const s = settings.value;
	return (
		<section class="setting">
			<h2>{ t( 'settings.display' ) }</h2>
			<Choices
				name="font_size"
				legend={ t( 'settings.font_size' ) }
				choices={ [
					{ value: 'S', label: t( 'settings.size_s' ) },
					{ value: 'M', label: t( 'settings.size_m' ) },
					{ value: 'L', label: t( 'settings.size_l' ) },
				] }
			/>
			<Choices
				name="font_family"
				legend={ t( 'settings.font_family' ) }
				choices={ [
					{ value: 'sans', label: t( 'settings.sans' ) },
					{ value: 'serif', label: t( 'settings.serif' ) },
				] }
			/>
			<Choices
				name="color_theme"
				legend={ t( 'settings.color' ) }
				choices={ [
					{ value: 'auto', label: t( 'settings.color_auto' ) },
					{ value: 'light', label: t( 'settings.color_light' ) },
					{ value: 'dark', label: t( 'settings.color_dark' ) },
				] }
			/>
			<label class="check">
				<input type="checkbox" checked={ s.prompts_enabled } onChange={ ( e ) => save( { prompts_enabled: ( e.currentTarget as HTMLInputElement ).checked } ) } />
				{ t( 'settings.prompts' ) }
			</label>
		</section>
	);
}

function DaySection() {
	return (
		<section class="setting">
			<h2>{ t( 'settings.day_start' ) }</h2>
			<p class="hint">{ t( 'settings.day_start_hint' ) }</p>
			<select aria-label={ t( 'settings.day_start' ) } value={ settings.value.day_start_hour } onChange={ ( e ) => save( { day_start_hour: +( e.currentTarget as HTMLSelectElement ).value } ) }>
				{ [ 0, 1, 2, 3, 4, 5, 6 ].map( ( h ) => (
					<option key={ h } value={ h }>
						{ h }:00
					</option>
				) ) }
			</select>
		</section>
	);
}

function ReminderSection() {
	const s = settings.value;
	const [ status, setStatus ] = useState<PushStatus | null>( null );
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		pushStatus().then( setStatus );
	}, [] );

	const toggle = async ( on: boolean ) => {
		setMessage( '' );
		try {
			if ( on ) {
				const next = await enablePush();
				setStatus( next );
				await save( { reminder_enabled: next === 'on' } );
			} else {
				await save( { reminder_enabled: false } );
			}
		} catch {
			setMessage( t( 'settings.push_failed' ) );
		}
	};

	const test = async () => {
		setMessage( t( 'common.sending' ) );
		try {
			const r = await api.pushTest();
			setMessage( r.sent > 0 ? t( 'settings.test_sent' ) : t( 'settings.test_none' ) );
		} catch {
			setMessage( t( 'settings.test_failed' ) );
		}
	};

	const usable = status === 'on' || status === 'off';

	return (
		<section class="setting">
			<h2>{ t( 'settings.reminder' ) }</h2>
			<p class="hint">{ t( 'settings.reminder_hint' ) }</p>
			<label class="check">
				<input type="checkbox" checked={ s.reminder_enabled && status === 'on' } disabled={ ! usable } onChange={ ( e ) => toggle( ( e.currentTarget as HTMLInputElement ).checked ) } />
				{ t( 'settings.notify' ) }
			</label>
			<label class="field">
				{ t( 'settings.time' ) }
				<input type="time" value={ s.reminder_time } step={ 300 } onChange={ ( e ) => save( { reminder_time: ( e.currentTarget as HTMLInputElement ).value } ) } />
			</label>
			{ status && <p class="hint">{ t( `push.${ status }` ) }</p> }
			{ status === 'on' && (
				<>
					<button type="button" class="more" onClick={ test }>
						{ t( 'settings.test' ) }
					</button>
					<button type="button" class="link-button" onClick={ async () => setStatus( await disablePush() ) }>
						{ t( 'settings.stop_device' ) }
					</button>
				</>
			) }
			<p class="hint">{ t( 'settings.iphone' ) }</p>
			{ message && (
				<p class="hint" role="status">
					{ message }
				</p>
			) }
		</section>
	);
}

function WeatherSection() {
	const s = settings.value;
	const [ query, setQuery ] = useState( '' );
	const [ places, setPlaces ] = useState<Place[]>( [] );
	const [ message, setMessage ] = useState( '' );

	const search = async ( event: Event ) => {
		event.preventDefault();
		setMessage( t( 'common.searching' ) );
		try {
			const found = await api.places( query );
			setPlaces( found );
			setMessage( found.length ? '' : t( 'common.no_results' ) );
		} catch {
			setMessage( t( 'common.search_failed' ) );
		}
	};

	const choose = async ( p: Place ) => {
		await save( { weather_lat: p.lat, weather_lng: p.lng, weather_place: [ p.name, p.admin ].filter( Boolean ).join( ', ' ) } );
		setPlaces( [] );
		setQuery( '' );
		setMessage( '' );
	};

	const here = () => {
		if ( ! navigator.geolocation ) {
			setMessage( t( 'settings.here_unavailable' ) );
			return;
		}
		setMessage( t( 'settings.here_getting' ) );
		navigator.geolocation.getCurrentPosition(
			( pos ) => {
				save( { weather_lat: pos.coords.latitude, weather_lng: pos.coords.longitude, weather_place: t( 'settings.here' ) } ).then( () => setMessage( '' ) );
			},
			() => setMessage( t( 'settings.here_failed' ) ),
			{ maximumAge: 3600000, timeout: 10000 }
		);
	};

	const place = s.weather_place || ( s.weather_lat !== null ? `${ s.weather_lat }, ${ s.weather_lng }` : t( 'settings.place_unset' ) );

	return (
		<section class="setting">
			<h2>{ t( 'settings.weather' ) }</h2>
			<label class="check">
				<input type="checkbox" checked={ s.weather_enabled } onChange={ ( e ) => save( { weather_enabled: ( e.currentTarget as HTMLInputElement ).checked } ) } />
				{ t( 'settings.weather_auto' ) }
			</label>
			<p class="hint">{ t( 'settings.place', place ) }</p>
			<form class="place-search" onSubmit={ search }>
				<input type="search" aria-label={ t( 'settings.city' ) } placeholder={ t( 'settings.city_example' ) } value={ query } onInput={ ( e ) => setQuery( ( e.currentTarget as HTMLInputElement ).value ) } />
				<button type="submit" class="tool">
					{ t( 'settings.find' ) }
				</button>
				{ window.isSecureContext && (
					<button type="button" class="tool" onClick={ here }>
						{ t( 'settings.here' ) }
					</button>
				) }
			</form>
			{ places.length > 0 && (
				<ul class="places">
					{ places.map( ( p ) => (
						<li key={ `${ p.lat },${ p.lng }` }>
							<button type="button" onClick={ () => choose( p ) }>
								{ p.name }
								<small>{ [ p.admin, p.country ].filter( Boolean ).join( ', ' ) }</small>
							</button>
						</li>
					) ) }
				</ul>
			) }
			{ message && (
				<p class="hint" role="status">
					{ message }
				</p>
			) }
			<p class="hint">{ t( 'settings.weather_source' ) }</p>
		</section>
	);
}

interface InstallPromptEvent extends Event {
	prompt: () => Promise<void>;
}

let deferredInstall: InstallPromptEvent | null = null;
window.addEventListener( 'beforeinstallprompt', ( event ) => {
	event.preventDefault();
	deferredInstall = event as InstallPromptEvent;
} );

function InstallSection() {
	const [ canInstall, setCanInstall ] = useState( deferredInstall !== null );
	const standalone = window.matchMedia( '(display-mode: standalone)' ).matches || ( navigator as Navigator & { standalone?: boolean } ).standalone === true;
	if ( standalone ) {
		return null;
	}
	const install = async () => {
		await deferredInstall?.prompt();
		deferredInstall = null;
		setCanInstall( false );
	};
	return (
		<section class="setting">
			<h2>{ t( 'settings.install' ) }</h2>
			{ canInstall && (
				<button type="button" class="more" onClick={ install }>
					{ t( 'settings.install_button' ) }
				</button>
			) }
			<p class="hint">{ t( 'settings.install_ios' ) }</p>
			<p class="hint">{ t( 'settings.install_android' ) }</p>
		</section>
	);
}

function DataSection() {
	const [ message, setMessage ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const input = useRef<HTMLInputElement>( null );

	const download = async () => {
		setBusy( true );
		setMessage( '' );
		try {
			const blob = await api.exportZip();
			const a = document.createElement( 'a' );
			a.href = URL.createObjectURL( blob );
			a.download = `hibi-${ boot.today }.zip`;
			document.body.appendChild( a );
			a.click();
			a.remove();
			setTimeout( () => URL.revokeObjectURL( a.href ), 10000 );
		} catch {
			setMessage( t( 'common.load_failed' ) );
		} finally {
			setBusy( false );
		}
	};

	const upload = async () => {
		const file = input.current?.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		setBusy( true );
		setMessage( t( 'common.sending' ) );
		try {
			const r = await api.importFile( file );
			setMessage( t( 'settings.import_done', r.imported, r.skipped ) );
		} catch ( err ) {
			setMessage( err instanceof ApiError ? err.message : t( 'settings.import_failed' ) );
		} finally {
			setBusy( false );
			if ( input.current ) {
				input.current.value = '';
			}
		}
	};

	return (
		<section class="setting">
			<h2>{ t( 'settings.data' ) }</h2>
			<button type="button" class="more" onClick={ download } disabled={ busy }>
				{ t( 'settings.export' ) }
			</button>
			<p class="hint">{ t( 'settings.export_hint' ) }</p>
			<label class="field file">
				{ t( 'settings.import' ) }
				<input ref={ input } type="file" accept=".zip,.json,application/zip,application/json" onChange={ upload } disabled={ busy } />
			</label>
			<p class="hint">{ t( 'settings.import_hint' ) }</p>
			{ message && (
				<p class="hint" role="status">
					{ message }
				</p>
			) }
		</section>
	);
}

function LogoutSection() {
	const logout = async () => {
		const unsent = await drafts.all();
		if ( unsent.length && ! window.confirm( t( 'settings.logout_unsent' ) ) ) {
			return;
		}
		// Someone else may use this device next: drop cached pages and entries.
		navigator.serviceWorker?.controller?.postMessage( 'clear-private-caches' );
		location.assign( boot.logout_url );
	};
	return (
		<section class="setting">
			<button type="button" class="link-button" onClick={ logout }>
				{ t( 'settings.logout' ) }
			</button>
		</section>
	);
}
