import { dayLabel } from '../date';
import { dayHref, route } from '../router';
import { conflictDates, pendingDates } from '../sync';

/** Days that are not open but still need attention. */
export function SyncNotice() {
	const open = route.value.name === 'day' ? route.value.date : '';
	const conflicts = conflictDates.value.filter( ( d ) => d !== open );
	const pending = pendingDates.value.filter( ( d ) => d !== open );
	if ( ! conflicts.length && ! pending.length ) {
		return null;
	}
	return (
		<div class="sync-notice" role="status">
			{ conflicts.length > 0 && (
				<p>
					別の端末で更新された日があります：
					{ conflicts.map( ( d ) => (
						<a key={ d } href={ dayHref( d ) } data-nav>
							{ dayLabel( d ) }
						</a>
					) ) }
				</p>
			) }
			{ pending.length > 0 && <p>未送信の日記が { pending.length } 日分あります。接続が戻ると送ります。</p> }
		</div>
	);
}
