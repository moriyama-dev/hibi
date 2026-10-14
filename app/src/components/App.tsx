import { useEffect } from 'preact/hooks';
import { href, navigate, route } from '../router';
import { refreshToday, settings } from '../store';
import { CalendarView } from './CalendarView';
import { DayView, useDayShortcuts } from './DayView';
import { Nav } from './Nav';
import { OnThisDayView } from './OnThisDay';
import { Pending } from './Pending';
import { SearchView } from './SearchView';
import { TimelineView } from './TimelineView';

export function App() {
	const r = route.value;
	useDayShortcuts( r.name === 'day' ? r.date : null );

	// Ctrl/Cmd+K opens search from anywhere.
	useEffect( () => {
		const onKey = ( event: KeyboardEvent ) => {
			if ( ( event.metaKey || event.ctrlKey ) && ! event.altKey && event.key.toLowerCase() === 'k' ) {
				event.preventDefault();
				if ( route.value.name !== 'search' ) {
					navigate( href( '/search' ) );
				}
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [] );

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
				{ r.name === 'calendar' && <CalendarView /> }
				{ r.name === 'timeline' && <TimelineView /> }
				{ r.name === 'on-this-day' && <OnThisDayView /> }
				{ r.name === 'search' && <SearchView /> }
				{ r.name === 'settings' && <Pending title="設定" /> }
				{ r.name === 'not-found' && <Pending title="ページが見つかりません" /> }
			</main>
		</div>
	);
}
