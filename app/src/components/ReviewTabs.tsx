import { href, route } from '../router';

const TABS = [
	{ name: 'calendar', path: '/calendar', label: 'カレンダー' },
	{ name: 'timeline', path: '/timeline', label: 'タイムライン' },
	{ name: 'on-this-day', path: '/on-this-day', label: '1年前' },
];

export function ReviewTabs() {
	return (
		<nav class="subtabs" aria-label="振り返り">
			{ TABS.map( ( t ) => (
				<a key={ t.name } href={ href( t.path ) } data-nav aria-current={ route.value.name === t.name ? 'page' : undefined }>
					{ t.label }
				</a>
			) ) }
		</nav>
	);
}
