import type { SaveState } from '../saver';

const LABELS: Record<SaveState, string> = {
	saved: '保存済み',
	dirty: '保存待ち',
	saving: '保存中…',
	offline: 'オフライン（再送します）',
	error: '保存できません（再送します）',
	conflict: '別の端末で更新あり',
};

export function SaveStatus( { state }: { state: SaveState } ) {
	return (
		<span class={ `save-status is-${ state }` } role="status" aria-live="polite">
			{ LABELS[ state ] }
		</span>
	);
}
