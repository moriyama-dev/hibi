<?php
/**
 * Prints a logged-in cookie for the e2e test user as JSON.
 * Run with: wp eval-file tests/e2e/login-cookie.php
 *
 * The user "hibi-e2e" is created on first use and marked, so teardown only
 * ever deletes a user this script made.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

$hibi_user = get_user_by( 'login', 'hibi-e2e' );
if ( ! $hibi_user ) {
	$hibi_id = wp_insert_user(
		array(
			'user_login' => 'hibi-e2e',
			'user_pass'  => wp_generate_password( 64, true, true ),
			'user_email' => 'hibi-e2e@example.invalid',
			'role'       => 'subscriber',
		)
	);
	update_user_meta( $hibi_id, '_hibi_e2e', 1 );
	// The tests read the Japanese interface.
	update_user_meta( $hibi_id, 'locale', 'ja' );
	$hibi_user = get_user_by( 'id', $hibi_id );
} elseif ( ! get_user_meta( $hibi_user->ID, '_hibi_e2e', true ) ) {
	fwrite( STDERR, "hibi-e2e exists but was not created by the tests.\n" );
	exit( 1 );
}

$hibi_expiration = time() + DAY_IN_SECONDS;
$hibi_token      = WP_Session_Tokens::get_instance( $hibi_user->ID )->create( $hibi_expiration );

echo wp_json_encode(
	array(
		'user_id' => $hibi_user->ID,
		'name'    => LOGGED_IN_COOKIE,
		'value'   => wp_generate_auth_cookie( $hibi_user->ID, $hibi_expiration, 'logged_in', $hibi_token ),
	)
);
