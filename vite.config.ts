import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';

// The app's source lives in the plugin (plugin/app) and builds into
// plugin/assets/app, which the plugin loads through manifest.json.
export default defineConfig( {
	plugins: [ preact() ],
	root: resolve( __dirname, 'plugin/app' ),
	base: './',
	build: {
		outDir: resolve( __dirname, 'plugin/assets/app' ),
		emptyOutDir: true,
		manifest: 'manifest.json',
		target: 'es2020',
		cssCodeSplit: false,
		modulePreload: false,
		rollupOptions: {
			input: resolve( __dirname, 'plugin/app/src/main.tsx' ),
		},
	},
} );
