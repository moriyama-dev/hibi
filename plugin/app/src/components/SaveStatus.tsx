import type { SaveState } from '../saver';
import { t } from '../i18n';


export function SaveStatus( { state }: { state: SaveState } ) {
	return (
		<span class={ `save-status is-${ state }` } role="status" aria-live="polite">
			{ t( `save.${ state }` ) }
		</span>
	);
}
