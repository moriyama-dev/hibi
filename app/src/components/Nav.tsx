import { href, route } from '../router';

const ITEMS = [
	{ path: '/', label: '今日', match: [ 'day' ] },
	{ path: '/calendar', label: '振り返り', match: [ 'calendar', 'timeline', 'on-this-day' ] },
	{ path: '/search', label: '検索', match: [ 'search' ] },
	{ path: '/settings', label: '設定', match: [ 'settings' ] },
];

export function Nav() {
	const current = route.value.name;
	return (
		<nav class="tabs" aria-label="メイン">
			<span class="brand" aria-hidden="true">
				Hibi
			</span>
			{ ITEMS.map( ( item ) => (
				<a
					key={ item.path }
					href={ href( item.path ) }
					data-nav
					class="tab"
					aria-current={ item.match.includes( current ) ? 'page' : undefined }
				>
					{ item.label }
				</a>
			) ) }
		</nav>
	);
}
