import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';

// Output goes into the theme, which loads it through .vite/manifest.json.
export default defineConfig( {
	plugins: [ preact() ],
	root: resolve( __dirname, 'app' ),
	base: './',
	build: {
		outDir: resolve( __dirname, 'theme/assets/dist' ),
		emptyOutDir: true,
		manifest: 'manifest.json',
		target: 'es2020',
		cssCodeSplit: false,
		modulePreload: false,
		rollupOptions: {
			input: resolve( __dirname, 'app/src/main.tsx' ),
		},
	},
} );
