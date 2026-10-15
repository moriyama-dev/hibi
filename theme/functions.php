<?php
/**
 * Hibi theme: the writing app for the Hibi Journal plugin.
 *
 * Every app URL returns the same shell page; the app switches screens in
 * the browser. All data comes from the plugin's REST API.
 *
 * @package Hibi
 */

defined( 'ABSPATH' ) || exit;

define( 'HIBI_THEME_VERSION', '0.1.0' );
define( 'HIBI_THEME_DIR', get_template_directory() );
define( 'HIBI_THEME_URL', get_template_directory_uri() );

require_once HIBI_THEME_DIR . '/inc/strings.php';

/**
 * App routes, without the leading slash. "d/<date>" is handled separately.
 */
const HIBI_THEME_VIEWS = array( 'calendar', 'timeline', 'search', 'on-this-day', 'settings' );

add_action( 'after_setup_theme', 'hibi_theme_setup' );
add_action( 'init', 'hibi_theme_rewrites' );
add_filter( 'query_vars', 'hibi_theme_query_vars' );
add_action( 'after_switch_theme', 'hibi_theme_flush_rewrites' );
add_action( 'switch_theme', 'flush_rewrite_rules' );
add_action( 'wp_enqueue_scripts', 'hibi_theme_assets', 20 );
add_action( 'template_redirect', 'hibi_theme_use_user_locale', 1 );
add_action( 'template_redirect', 'hibi_theme_not_found_status' );
add_action( 'parse_request', 'hibi_theme_serve_app_files', 0 );
add_action( 'init', 'hibi_theme_maybe_flush_rewrites', 20 );
add_action( 'wp_head', 'hibi_theme_head_tags', 2 );
add_action( 'send_headers', 'hibi_theme_shell_header' );

function hibi_theme_setup() {
	load_theme_textdomain( 'hibi', HIBI_THEME_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	// Tells the Hibi Journal plugin that the app owns the front end, so the
	// plugin may change login behaviour and hide the admin bar.
	add_theme_support( 'hibi-app' );
}

function hibi_theme_rewrites() {
	add_rewrite_rule( '^hibi-sw\\.js$', 'index.php?hibi_asset=sw', 'top' );
	add_rewrite_rule( '^manifest\\.webmanifest$', 'index.php?hibi_asset=manifest', 'top' );
	add_rewrite_rule( '^d/(\d{4}-\d{2}-\d{2})/?$', 'index.php?hibi_view=day&hibi_date=$matches[1]', 'top' );
	add_rewrite_rule( '^(' . implode( '|', array_map( 'preg_quote', HIBI_THEME_VIEWS ) ) . ')/?$', 'index.php?hibi_view=$matches[1]', 'top' );
}

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function hibi_theme_query_vars( $vars ) {
	$vars[] = 'hibi_view';
	$vars[] = 'hibi_date';
	$vars[] = 'hibi_asset';
	return $vars;
}

function hibi_theme_flush_rewrites() {
	hibi_theme_rewrites();
	flush_rewrite_rules();
}

/**
 * Rewrite rules change with the theme version; flush once after an update.
 */
function hibi_theme_maybe_flush_rewrites() {
	if ( get_option( 'hibi_theme_rewrite_version' ) !== HIBI_THEME_VERSION . '-2' ) {
		flush_rewrite_rules( false );
		update_option( 'hibi_theme_rewrite_version', HIBI_THEME_VERSION . '-2', false );
	}
}

/**
 * The app speaks the language chosen in the user's profile, not the
 * site's language.
 */
function hibi_theme_use_user_locale() {
	if ( is_user_logged_in() ) {
		switch_to_user_locale( get_current_user_id() );
	}
}

/**
 * The user's locale as a BCP 47 tag for Intl, e.g. "ja" or "en-US".
 *
 * @return string
 */
function hibi_theme_bcp47_locale() {
	return str_replace( '_', '-', determine_locale() );
}

/**
 * Whether the current request is one of the app's screens.
 *
 * @return bool
 */
function hibi_theme_is_app_request() {
	return is_front_page() || is_home() || is_singular() || '' !== (string) get_query_var( 'hibi_view' );
}

/**
 * The shell is served for every front-end URL, but URLs that are not app
 * screens keep a 404 status.
 */
function hibi_theme_not_found_status() {
	if ( ! hibi_theme_is_app_request() ) {
		status_header( 404 );
		nocache_headers();
	}
}

/**
 * Built files from assets/dist/manifest.json (written by the Vite build).
 *
 * @return array{js:string,css:string[]}|null
 */
function hibi_theme_build() {
	$path = HIBI_THEME_DIR . '/assets/dist/manifest.json';
	if ( ! is_readable( $path ) ) {
		return null;
	}
	$manifest = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	if ( ! is_array( $manifest ) || empty( $manifest['src/main.tsx']['file'] ) ) {
		return null;
	}
	$css = array();
	foreach ( $manifest as $chunk ) {
		if ( isset( $chunk['file'] ) && '.css' === substr( $chunk['file'], -4 ) ) {
			$css[] = $chunk['file'];
		}
	}
	return array(
		'js'  => $manifest['src/main.tsx']['file'],
		'css' => $css,
	);
}

function hibi_theme_assets() {
	if ( is_singular() && ! is_front_page() ) {
		wp_enqueue_style( 'hibi-content', HIBI_THEME_URL . '/assets/content.css', array(), HIBI_THEME_VERSION );
		return;
	}

	// The app needs none of the block styles; keeping the shell light keeps
	// the first paint fast.
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	$build = hibi_theme_build();
	if ( ! $build || ! function_exists( 'hibi_is_active' ) ) {
		return;
	}
	foreach ( $build['css'] as $i => $file ) {
		// Hashed file names change on every build, so no version string is needed.
		wp_enqueue_style( 'hibi-app-' . $i, HIBI_THEME_URL . '/assets/dist/' . $file, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
	wp_enqueue_script_module( 'hibi-app', HIBI_THEME_URL . '/assets/dist/' . $build['js'], array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
}

/**
 * Prints the data the app needs for its first paint, so it does not wait
 * for an API round trip. JSON_HEX_TAG keeps "</script>" in an entry from
 * closing the tag.
 */
function hibi_theme_print_bootstrap() {
	if ( ! function_exists( 'hibi_is_active' ) || ! is_user_logged_in() ) {
		return;
	}
	$data             = Hibi_Rest::bootstrap_data();
	$data['locale']   = hibi_theme_bcp47_locale();
	$data['i18n']     = hibi_theme_strings();
	$data['prompts']  = hibi_theme_prompts();
	// wp_logout_url() returns an HTML-escaped URL; the app needs it raw.
	$data['logout_url'] = html_entity_decode( wp_logout_url( wp_login_url() ), ENT_QUOTES );
	printf(
		'<script type="application/json" id="hibi-bootstrap">%s</script>' . "\n",
		wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HTML-significant characters hex-escaped.
	);
}

/**
 * Marks the shell so the service worker caches only real app pages, never
 * a login form or an error page.
 */
function hibi_theme_shell_header() {
	if ( is_user_logged_in() && ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		header( 'X-Hibi-Shell: 1' );
	}
}

/**
 * Home path, e.g. "/" or "/journal/".
 *
 * @return string
 */
function hibi_theme_home_path() {
	return trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
}

/**
 * Paths the service worker stores on install.
 *
 * @return string[]
 */
function hibi_theme_precache() {
	$base  = (string) wp_parse_url( HIBI_THEME_URL, PHP_URL_PATH );
	$paths = array();
	$path  = HIBI_THEME_DIR . '/assets/dist/manifest.json';
	$data  = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	foreach ( (array) $data as $chunk ) {
		if ( ! empty( $chunk['file'] ) ) {
			$paths[] = $base . '/assets/dist/' . $chunk['file'];
		}
	}
	foreach ( array( 'icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png', 'favicon-32.png' ) as $icon ) {
		$paths[] = $base . '/assets/icons/' . $icon;
	}
	return array_values( array_unique( $paths ) );
}

/**
 * Serves /hibi-sw.js and /manifest.webmanifest. Runs before the access
 * gate redirects logged-out visitors: neither file holds journal data, and
 * the browser must be able to update the service worker at any time.
 *
 * @param WP $wp Request.
 */
function hibi_theme_serve_app_files( $wp ) {
	$asset = isset( $wp->query_vars['hibi_asset'] ) ? $wp->query_vars['hibi_asset'] : '';
	if ( 'sw' === $asset ) {
		hibi_theme_send_service_worker();
	}
	if ( 'manifest' === $asset ) {
		hibi_theme_send_manifest();
	}
}

function hibi_theme_send_service_worker() {
	$source   = HIBI_THEME_DIR . '/assets/sw.js';
	$precache = hibi_theme_precache();
	$version  = substr( md5( HIBI_THEME_VERSION . implode( ',', $precache ) . filemtime( $source ) ), 0, 12 );
	if ( is_user_logged_in() ) {
		switch_to_user_locale( get_current_user_id() );
	}
	$config = array(
		'offline'  => __( 'You are offline. Connect and open the app again.', 'hibi' ),
		'version'  => $version,
		'home'     => hibi_theme_home_path(),
		'precache' => $precache,
		'icon'     => (string) wp_parse_url( HIBI_THEME_URL, PHP_URL_PATH ) . '/assets/icons/icon-192.png',
	);

	status_header( 200 );
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: ' . hibi_theme_home_path() );
	header( 'Cache-Control: no-cache' );
	header( 'X-Content-Type-Options: nosniff' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- JSON and a static script shipped with the theme, sent as JavaScript.
	echo 'self.HIBI_SW = ' . wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) . ";\n" . file_get_contents( $source );
	exit;
}

function hibi_theme_send_manifest() {
	$icons    = HIBI_THEME_URL . '/assets/icons/';
	$manifest = array(
		'name'             => 'Hibi',
		'short_name'       => 'Hibi',
		'description'      => __( 'A private daily journal.', 'hibi' ),
		'lang'             => get_bloginfo( 'language' ),
		'id'               => hibi_theme_home_path(),
		'start_url'        => hibi_theme_home_path(),
		'scope'            => hibi_theme_home_path(),
		'display'          => 'standalone',
		'background_color' => '#f7f6f3',
		'theme_color'      => '#f7f6f3',
		'icons'            => array(
			array(
				'src'     => $icons . 'icon-192.png',
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
			array(
				'src'     => $icons . 'icon-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
			array(
				'src'     => $icons . 'icon-maskable-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			),
		),
	);

	status_header( 200 );
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'Cache-Control: no-cache' );
	echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document.
	exit;
}

/**
 * Manifest, icons and colours for installing the app.
 */
function hibi_theme_head_tags() {
	$icons = HIBI_THEME_URL . '/assets/icons/';
	printf( '<link rel="manifest" href="%s" crossorigin="use-credentials">' . "\n", esc_url( home_url( '/manifest.webmanifest' ) ) );
	printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( $icons . 'favicon-32.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $icons . 'apple-touch-icon.png' ) );
	echo '<meta name="theme-color" content="#f7f6f3" media="(prefers-color-scheme: light)">' . "\n";
	echo '<meta name="theme-color" content="#15171b" media="(prefers-color-scheme: dark)">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="Hibi">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
}
