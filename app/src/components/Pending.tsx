import { href } from '../router';
import { t } from '../i18n';

/** Screens that are not built yet. */
export function Pending( { title }: { title: string } ) {
	return (
		<div class="pending">
			<h1>{ title }</h1>
			<a href={ href( '/' ) } data-nav>
				{ t( 'back_to_today' ) }
			</a>
		</div>
	);
}
