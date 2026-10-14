interface Props {
	onChoose: ( choice: 'mine' | 'theirs' | 'both' ) => void;
}

export function ConflictBanner( { onChoose }: Props ) {
	return (
		<div class="conflict" role="alert">
			<p>
				<strong>別の端末で更新されています。</strong>
				どちらを残すか選んでください。選ぶまで保存は止まっていて、どちらの内容も消えません。
			</p>
			<div class="conflict-actions">
				<button type="button" onClick={ () => onChoose( 'mine' ) }>
					この端末の内容を残す
				</button>
				<button type="button" onClick={ () => onChoose( 'theirs' ) }>
					もう一方を残す
				</button>
				<button type="button" onClick={ () => onChoose( 'both' ) }>
					両方をつなげて残す
				</button>
			</div>
		</div>
	);
}
