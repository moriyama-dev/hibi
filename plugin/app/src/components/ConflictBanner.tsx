import { t } from '../i18n';

interface Props {
	onChoose: ( choice: 'mine' | 'theirs' | 'both' ) => void;
}

export function ConflictBanner( { onChoose }: Props ) {
	return (
		<div class="conflict" role="alert">
			<p>
				<strong>{ t( 'conflict.title' ) }</strong> 
				{ t( 'conflict.body' ) }
			</p>
			<div class="conflict-actions">
				<button type="button" onClick={ () => onChoose( 'mine' ) }>
					{ t( 'conflict.mine' ) }
				</button>
				<button type="button" onClick={ () => onChoose( 'theirs' ) }>
					{ t( 'conflict.theirs' ) }
				</button>
				<button type="button" onClick={ () => onChoose( 'both' ) }>
					{ t( 'conflict.both' ) }
				</button>
			</div>
		</div>
	);
}
