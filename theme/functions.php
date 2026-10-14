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
add_action( 'template_redirect', 'hibi_theme_not_found_status' );

function hibi_theme_setup() {
	load_theme_textdomain( 'hibi', HIBI_THEME_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	// Tells the Hibi Journal plugin that the app owns the front end, so the
	// plugin may change login behaviour and hide the admin bar.
	add_theme_support( 'hibi-app' );
}

function hibi_theme_rewrites() {
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
	return $vars;
}

function hibi_theme_flush_rewrites() {
	hibi_theme_rewrites();
	flush_rewrite_rules();
}

/**
 * Whether the current request is one of the app's screens.
 *
 * @return bool
 */
function hibi_theme_is_app_request() {
	return is_front_page() || is_home() || '' !== (string) get_query_var( 'hibi_view' );
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
	$data = Hibi_Rest::bootstrap_data();
	printf(
		'<script type="application/json" id="hibi-bootstrap">%s</script>' . "\n",
		wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HTML-significant characters hex-escaped.
	);
}
