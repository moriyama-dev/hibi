import { href, route } from '../router';
import { t } from '../i18n';

const ITEMS = [
	{ path: '/', label: 'nav.today', match: [ 'day' ] },
	{ path: '/calendar', label: 'nav.review', match: [ 'calendar', 'timeline', 'on-this-day' ] },
	{ path: '/search', label: 'nav.search', match: [ 'search' ] },
	{ path: '/settings', label: 'nav.settings', match: [ 'settings' ] },
];

export function Nav() {
	const current = route.value.name;
	return (
		<nav class="tabs" aria-label={ t( 'nav.main' ) }>
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
					{ t( item.label ) }
				</a>
			) ) }
		</nav>
	);
}
