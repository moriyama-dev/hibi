import { defineConfig, devices } from '@playwright/test';

// Runs against the WordPress site on the VM with the Hibi theme active.
export default defineConfig( {
	testDir: 'tests/e2e',
	outputDir: 'test-results/e2e',
	fullyParallel: false,
	workers: 1,
	reporter: [ [ 'list' ] ],
	globalSetup: './tests/e2e/global-setup.ts',
	globalTeardown: './tests/e2e/global-teardown.ts',
	use: {
		baseURL: process.env.HIBI_BASE_URL ?? 'http://localhost',
		storageState: 'test-results/auth.json',
		locale: 'ja-JP',
		timezoneId: 'America/Toronto',
		trace: 'retain-on-failure',
	},
	projects: [
		{ name: 'desktop', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'mobile', use: { ...devices[ 'Pixel 7' ] } },
	],
} );
