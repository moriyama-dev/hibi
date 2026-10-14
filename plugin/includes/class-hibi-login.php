<?php
/**
 * Login behaviour for the app: land on today's entry, stay logged in, no
 * admin bar. Applies only while the active theme declares "hibi-app", so
 * activating the plugin alone never changes how another site's theme works.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Login {

	public static function init() {
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'show_admin_bar' ) );
		add_filter( 'auth_cookie_expiration', array( __CLASS__, 'cookie_expiration' ), 10, 3 );
		add_action( 'login_footer', array( __CLASS__, 'check_remember_me' ) );
	}

	/**
	 * @return bool
	 */
	public static function app_theme_active() {
		return current_theme_supports( 'hibi-app' );
	}

	/**
	 * After login, go to today's entry instead of the dashboard. A specific
	 * page asked for in redirect_to (for example a date page that sent the
	 * user to log in) is kept.
	 *
	 * @param string           $redirect_to Destination.
	 * @param string           $requested   Destination asked for.
	 * @param WP_User|WP_Error $user        User.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( ! self::app_theme_active() || ! $user instanceof WP_User ) {
			return $redirect_to;
		}
		$admin = admin_url();
		if ( '' === $requested || $requested === $admin || untrailingslashit( $requested ) === untrailingslashit( $admin ) ) {
			return home_url( '/' );
		}
		return $redirect_to;
	}

	/**
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public static function show_admin_bar( $show ) {
		return ( self::app_theme_active() && ! is_admin() ) ? false : $show;
	}

	/**
	 * "Remember me" keeps the login for a year, so opening the app never
	 * starts with a login form.
	 *
	 * @param int  $length   Seconds.
	 * @param int  $user_id  User ID.
	 * @param bool $remember Whether "Remember me" was ticked.
	 * @return int
	 */
	public static function cookie_expiration( $length, $user_id, $remember ) {
		unset( $user_id );
		return ( $remember && self::app_theme_active() ) ? YEAR_IN_SECONDS : $length;
	}

	/**
	 * Ticks "Remember me" by default on the login form.
	 */
	public static function check_remember_me() {
		if ( ! self::app_theme_active() ) {
			return;
		}
		wp_print_inline_script_tag( 'var hibiRemember=document.getElementById("rememberme");if(hibiRemember){hibiRemember.checked=true;}' );
	}
}
