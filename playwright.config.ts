import { defineConfig, devices } from '@playwright/test';

const BASE_URL = process.env.HIBI_BASE_URL ?? 'http://localhost';

// Runs against the WordPress site on the VM with the Hibi theme active.
// The VM serves plain http, so Chromium is told to treat it as secure:
// service workers, push and geolocation need a secure context.
export default defineConfig( {
	testDir: 'tests/e2e',
	outputDir: 'test-results/e2e',
	fullyParallel: false,
	workers: 1,
	reporter: [ [ 'list' ] ],
	globalSetup: './tests/e2e/global-setup.ts',
	globalTeardown: './tests/e2e/global-teardown.ts',
	use: {
		baseURL: BASE_URL,
		// Full Chromium: the headless shell ignores the secure-origin flag.
		channel: 'chromium',
		launchOptions: { args: [ `--unsafely-treat-insecure-origin-as-secure=${ BASE_URL }` ] },
		storageState: 'test-results/auth.json',
		locale: 'ja-JP',
		timezoneId: 'America/Toronto',
		trace: 'retain-on-failure',
	},
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ], channel: 'chromium' } },
		{ name: 'mobile', use: { ...devices[ 'Pixel 7' ], channel: 'chromium' } },
	],
} );
