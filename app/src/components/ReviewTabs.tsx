import { href, route } from '../router';
import { t } from '../i18n';

const TABS = [
	{ name: 'calendar', path: '/calendar', label: 'review.calendar' },
	{ name: 'timeline', path: '/timeline', label: 'review.timeline' },
	{ name: 'on-this-day', path: '/on-this-day', label: 'review.otd' },
];

export function ReviewTabs() {
	return (
		<nav class="subtabs" aria-label={ t( 'nav.review' ) }>
			{ TABS.map( ( tab ) => (
				<a key={ tab.name } href={ href( tab.path ) } data-nav aria-current={ route.value.name === tab.name ? 'page' : undefined }>
					{ t( tab.label ) }
				</a>
			) ) }
		</nav>
	);
}
