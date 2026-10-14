import { useEffect, useState } from 'preact/hooks';
import { api } from '../api';
import { disablePush, enablePush, pushStatus, type PushStatus } from '../push';
import { settings } from '../store';
import type { Place, Settings } from '../types';

async function save( changes: Partial<Settings> ): Promise<void> {
	settings.value = { ...settings.value, ...changes };
	settings.value = await api.putSettings( changes );
}

const PUSH_NOTES: Record<PushStatus, string> = {
	unsupported: 'このブラウザは通知に対応していません。',
	insecure: '通知は https で開いたときだけ使えます。',
	denied: '通知がブラウザで拒否されています。ブラウザのサイト設定から許可してください。',
	off: 'この端末には通知が届きません。',
	on: 'この端末に通知が届きます。',
};

export function SettingsView() {
	const s = settings.value;
	return (
		<div class="settings">
			<h1 class="review-title">設定</h1>
			<ReminderSection />
			<WeatherSection />
			<section class="setting">
				<h2>1日の始まり</h2>
				<p class="hint">この時刻より前に書いた日記は、前の日の日記になります。</p>
				<select
					aria-label="1日の始まり"
					value={ s.day_start_hour }
					onChange={ ( e ) => save( { day_start_hour: +( e.currentTarget as HTMLSelectElement ).value } ) }
				>
					{ [ 0, 1, 2, 3, 4, 5, 6 ].map( ( h ) => (
						<option key={ h } value={ h }>
							{ h }:00
						</option>
					) ) }
				</select>
			</section>
		</div>
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
			setMessage( '通知を設定できませんでした。' );
		}
	};

	const test = async () => {
		setMessage( '送信中…' );
		try {
			const r = await api.pushTest();
			setMessage( r.sent > 0 ? 'テスト通知を送りました。' : '送れる端末がありません。' );
		} catch {
			setMessage( 'テスト通知を送れませんでした。' );
		}
	};

	const usable = status === 'on' || status === 'off';

	return (
		<section class="setting">
			<h2>リマインダー</h2>
			<p class="hint">決めた時刻を過ぎても書いていない日だけ、1回お知らせします。</p>
			<label class="check">
				<input type="checkbox" checked={ s.reminder_enabled && status === 'on' } disabled={ ! usable } onChange={ ( e ) => toggle( ( e.currentTarget as HTMLInputElement ).checked ) } />
				通知する
			</label>
			<label class="field">
				時刻
				<input type="time" value={ s.reminder_time } step={ 300 } onChange={ ( e ) => save( { reminder_time: ( e.currentTarget as HTMLInputElement ).value } ) } />
			</label>
			{ status && <p class="hint">{ PUSH_NOTES[ status ] }</p> }
			{ status === 'on' && (
				<>
					<button type="button" class="more" onClick={ test }>
						テスト通知を送る
					</button>
					<button type="button" class="link-button" onClick={ async () => setStatus( await disablePush() ) }>
						この端末では通知を止める
					</button>
				</>
			) }
			<p class="hint">iPhone では、Safari の共有ボタンから「ホーム画面に追加」したアプリでだけ通知が届きます（iOS 16.4 以降）。</p>
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
		setMessage( '検索中…' );
		try {
			const found = await api.places( query );
			setPlaces( found );
			setMessage( found.length ? '' : '見つかりませんでした。' );
		} catch {
			setMessage( '検索できませんでした。' );
		}
	};

	const choose = async ( p: Place ) => {
		await save( { weather_lat: p.lat, weather_lng: p.lng, weather_place: [ p.name, p.admin ].filter( Boolean ).join( '、' ) } );
		setPlaces( [] );
		setQuery( '' );
		setMessage( '' );
	};

	const here = () => {
		if ( ! navigator.geolocation ) {
			setMessage( 'この端末では現在地を使えません。' );
			return;
		}
		setMessage( '現在地を取得中…' );
		navigator.geolocation.getCurrentPosition(
			( pos ) => {
				save( { weather_lat: pos.coords.latitude, weather_lng: pos.coords.longitude, weather_place: '現在地' } ).then( () => setMessage( '' ) );
			},
			() => setMessage( '現在地を取得できませんでした。都市名で探してください。' ),
			{ maximumAge: 3600000, timeout: 10000 }
		);
	};

	return (
		<section class="setting">
			<h2>天気</h2>
			<label class="check">
				<input type="checkbox" checked={ s.weather_enabled } onChange={ ( e ) => save( { weather_enabled: ( e.currentTarget as HTMLInputElement ).checked } ) } />
				その日の天気を自動で記録する
			</label>
			<p class="hint">場所：{ s.weather_place || ( s.weather_lat !== null ? `${ s.weather_lat }, ${ s.weather_lng }` : '未設定' ) }</p>
			<form class="place-search" onSubmit={ search }>
				<input type="search" aria-label="都市名" placeholder="都市名（例：トロント）" value={ query } onInput={ ( e ) => setQuery( ( e.currentTarget as HTMLInputElement ).value ) } />
				<button type="submit" class="tool">
					探す
				</button>
				{ window.isSecureContext && (
					<button type="button" class="tool" onClick={ here }>
						現在地
					</button>
				) }
			</form>
			{ places.length > 0 && (
				<ul class="places">
					{ places.map( ( p ) => (
						<li key={ `${ p.lat },${ p.lng }` }>
							<button type="button" onClick={ () => choose( p ) }>
								{ p.name }
								<small>{ [ p.admin, p.country ].filter( Boolean ).join( '、' ) }</small>
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
			<p class="hint">天気は Open-Meteo（open-meteo.com）から、このサーバーを通して取得します。</p>
		</section>
	);
}
