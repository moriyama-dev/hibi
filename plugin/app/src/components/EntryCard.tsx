import { dayLabel, yearLabel } from '../date';
import { dayHref } from '../router';
import { today } from '../store';
import type { Card } from '../types';

export function MoodDot( { mood }: { mood: number | null } ) {
	return <span class={ `mood-dot${ mood ? ` mood-bg-${ mood }` : '' }` } aria-hidden="true" />;
}

export function EntryCard( { card, heading }: { card: Card; heading?: string } ) {
	return (
		<a class="card" href={ dayHref( card.date ) } data-nav>
			<span class="card-date">
				<MoodDot mood={ card.mood } />
				{ heading ?? (
					<>
						{ yearLabel( card.date, today.value ) }
						{ dayLabel( card.date ) }
					</>
				) }
			</span>
			{ card.excerpt && <span class="card-text">{ card.excerpt }</span> }
			{ card.photos.length > 0 && (
				<span class="card-photos">
					{ card.photos.map( ( p ) => (
						<img key={ p.id } src={ p.thumb } alt="" loading="lazy" width={ 56 } height={ 56 } />
					) ) }
				</span>
			) }
			{ card.tags.length > 0 && <span class="card-tags">{ card.tags.map( ( t ) => `#${ t }` ).join( ' ' ) }</span> }
		</a>
	);
}
