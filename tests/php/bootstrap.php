<?php
/**
 * PHPUnit bootstrap. Set up the suite first with bin/install-wp-tests.sh.
 *
 * @package HibiJournal
 */

$hibi_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $hibi_tests_dir ) {
	$hibi_version   = trim( (string) shell_exec( 'wp core version --path=/var/www/html 2>/dev/null' ) );
	$hibi_tests_dir = getenv( 'HOME' ) . '/.cache/hibi-wp-tests/' . $hibi_version . '/tests-lib';
}

if ( ! file_exists( $hibi_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "The WordPress test library is missing at {$hibi_tests_dir}. Run bin/install-wp-tests.sh first.\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $hibi_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		update_option( 'timezone_string', 'America/Toronto' );
		require dirname( __DIR__, 2 ) . '/plugin/hibi-journal.php';
	}
);

require $hibi_tests_dir . '/includes/bootstrap.php';
