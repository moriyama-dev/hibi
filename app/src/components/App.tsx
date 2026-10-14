import { useEffect } from 'preact/hooks';
import { route } from '../router';
import { refreshToday, settings } from '../store';
import { DayView, useDayShortcuts } from './DayView';
import { Nav } from './Nav';
import { Pending } from './Pending';

const TITLES = {
	calendar: '振り返り',
	timeline: 'タイムライン',
	search: '検索',
	'on-this-day': '1年前の今日',
	settings: '設定',
} as const;

export function App() {
	const r = route.value;
	useDayShortcuts( r.name === 'day' ? r.date : null );

	// When the app comes back after the day boundary, move "today" forward.
	useEffect( () => {
		const onVisible = () => document.visibilityState === 'visible' && refreshToday();
		document.addEventListener( 'visibilitychange', onVisible );
		const timer = window.setInterval( refreshToday, 60000 );
		return () => {
			document.removeEventListener( 'visibilitychange', onVisible );
			clearInterval( timer );
		};
	}, [] );

	const s = settings.value;
	useEffect( () => {
		const root = document.documentElement;
		if ( s.color_theme === 'auto' ) {
			root.removeAttribute( 'data-theme' );
		} else {
			root.setAttribute( 'data-theme', s.color_theme );
		}
		root.dataset.fontSize = s.font_size;
		root.dataset.fontFamily = s.font_family;
	}, [ s.color_theme, s.font_size, s.font_family ] );

	return (
		<div class="shell">
			<Nav />
			<main class="main" id="main">
				{ r.name === 'day' && <DayView key={ r.date } date={ r.date } isToday={ r.isToday } /> }
				{ r.name in TITLES && <Pending title={ TITLES[ r.name as keyof typeof TITLES ] } /> }
				{ r.name === 'not-found' && <Pending title="ページが見つかりません" /> }
			</main>
		</div>
	);
}
