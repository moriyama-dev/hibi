import { href } from '../router';

/** Screens that are not built yet. */
export function Pending( { title }: { title: string } ) {
	return (
		<div class="pending">
			<h1>{ title }</h1>
			<p>この画面は次の段階で作ります。</p>
			<a href={ href( '/' ) } data-nav>
				今日の日記へ
			</a>
		</div>
	);
}
