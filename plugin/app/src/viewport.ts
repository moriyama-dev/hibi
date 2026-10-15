// Keeps the toolbar right above the on-screen keyboard and hides the bottom
// tabs while the keyboard is up. Uses visualViewport where available.

const KEYBOARD_MIN_PX = 150;

export function watchKeyboard(): void {
	const vv = window.visualViewport;
	if ( ! vv ) {
		return;
	}
	const root = document.documentElement;
	let frame = 0;
	const update = () => {
		cancelAnimationFrame( frame );
		frame = requestAnimationFrame( () => {
			const covered = Math.max( 0, window.innerHeight - vv.height - vv.offsetTop );
			root.style.setProperty( '--kb', `${ covered }px` );
			root.classList.toggle( 'kb-open', covered > KEYBOARD_MIN_PX );
		} );
	};
	vv.addEventListener( 'resize', update );
	vv.addEventListener( 'scroll', update );
	update();
}
