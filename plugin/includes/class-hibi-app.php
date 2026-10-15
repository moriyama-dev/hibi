<?php
/**
 * The writing app: its URLs, its shell page, the service worker and the
 * web app manifest.
 *
 * The app lives at /journal/ with any theme. When the active theme
 * declares support for "hibi-app" (the Hibi theme does), the app takes the
 * site's front page instead and its screens move to the root (/d/<date>/,
 * /calendar/ ...).
 *
 * The shell is a page of its own, not a theme template: no theme styles or
 * scripts reach the app, whatever theme is active.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_App {

	/**
	 * Screens besides today and a date, without slashes.
	 */
	const VIEWS = array( 'calendar', 'timeline', 'search', 'on-this-day', 'settings' );

	/**
	 * Bump when the rewrite rules change, so they are flushed once.
	 */
	const RULES_VERSION = '3';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'parse_request', array( __CLASS__, 'serve_files' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 1 );
		add_action( 'after_switch_theme', array( __CLASS__, 'flush_rewrites' ) );
	}

	/**
	 * Whether the active theme hands the front page to the app.
	 *
	 * @return bool
	 */
	public static function theme_mode() {
		return current_theme_supports( 'hibi-app' );
	}

	/**
	 * Path segment of the app when it does not own the front page.
	 *
	 * @return string
	 */
	public static function slug() {
		/**
		 * Filters the path of the app, "journal" by default (example.com/journal/).
		 *
		 * @param string $slug Path segment.
		 */
		$slug = sanitize_title( (string) apply_filters( 'hibi_app_slug', 'journal' ) );
		return '' === $slug ? 'journal' : $slug;
	}

	/**
	 * Site path, e.g. "/" or "/blog/".
	 *
	 * @return string
	 */
	public static function site_path() {
		return trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	/**
	 * Path of the app, e.g. "/journal/" or "/" in theme mode.
	 *
	 * @return string
	 */
	public static function base_path() {
		return self::theme_mode() ? self::site_path() : self::site_path() . self::slug() . '/';
	}

	/**
	 * Full URL of the app.
	 *
	 * @return string
	 */
	public static function url() {
		return home_url( self::theme_mode() ? '/' : '/' . self::slug() . '/' );
	}

	public static function rewrites() {
		add_rewrite_rule( '^hibi-sw\.js$', 'index.php?hibi_asset=sw', 'top' );
		add_rewrite_rule( '^hibi-manifest\.webmanifest$', 'index.php?hibi_asset=manifest', 'top' );

		$prefix = self::theme_mode() ? '' : preg_quote( self::slug(), '#' ) . '/';
		if ( '' !== $prefix ) {
			add_rewrite_rule( '^' . self::slug() . '/?$', 'index.php?hibi_view=today', 'top' );
		}
		add_rewrite_rule( '^' . $prefix . 'd/(\d{4}-\d{2}-\d{2})/?$', 'index.php?hibi_view=day&hibi_date=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $prefix . '(' . implode( '|', array_map( 'preg_quote', self::VIEWS ) ) . ')/?$', 'index.php?hibi_view=$matches[1]', 'top' );
	}

	/**
	 * The rules depend on the plugin version and on the theme; flush once
	 * whenever either changes.
	 */
	public static function maybe_flush_rewrites() {
		$signature = self::RULES_VERSION . '|' . ( self::theme_mode() ? 'theme' : self::slug() );
		if ( get_option( 'hibi_rewrite_signature' ) !== $signature ) {
			flush_rewrite_rules( false );
			update_option( 'hibi_rewrite_signature', $signature, false );
		}
	}

	public static function flush_rewrites() {
		delete_option( 'hibi_rewrite_signature' );
	}

	/**
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'hibi_view';
		$vars[] = 'hibi_date';
		$vars[] = 'hibi_asset';
		return $vars;
	}

	/**
	 * Whether this request shows the app.
	 *
	 * @return bool
	 */
	public static function is_app_request() {
		return '' !== (string) get_query_var( 'hibi_view' ) || ( self::theme_mode() && is_front_page() );
	}

	/**
	 * Renders the app shell for app URLs. Logged-out visitors go to the
	 * login form and come back here afterwards.
	 */
	public static function maybe_render() {
		if ( ! self::is_app_request() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! current_user_can( Hibi_Rest::capability() ) ) {
			wp_die( esc_html__( 'You cannot use the journal.', 'hibi-journal' ), '', array( 'response' => 403 ) );
		}

		// The app speaks the language of the user's profile.
		switch_to_user_locale( get_current_user_id() );

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		// Lets the service worker keep this page, and only this kind of page, for offline use.
		header( 'X-Hibi-Shell: 1' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		require HIBI_PLUGIN_DIR . 'templates/app.php';
		exit;
	}

	/**
	 * Built files from assets/app/manifest.json (written by the Vite build).
	 *
	 * @return array{js:string,css:string[],chunks:string[]}|null Paths relative to assets/app.
	 */
	public static function build() {
		static $build = false;
		if ( false !== $build ) {
			return $build;
		}
		$path     = HIBI_PLUGIN_DIR . 'assets/app/manifest.json';
		$manifest = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		if ( ! is_array( $manifest ) || empty( $manifest['src/main.tsx']['file'] ) ) {
			$build = null;
			return $build;
		}
		$css    = array();
		$chunks = array();
		foreach ( $manifest as $chunk ) {
			if ( empty( $chunk['file'] ) ) {
				continue;
			}
			$chunks[] = $chunk['file'];
			if ( '.css' === substr( $chunk['file'], -4 ) ) {
				$css[] = $chunk['file'];
			}
		}
		$build = array(
			'js'     => $manifest['src/main.tsx']['file'],
			'css'    => $css,
			'chunks' => $chunks,
		);
		return $build;
	}

	/**
	 * URL of a file in the plugin's assets folder.
	 *
	 * @param string $file Path below assets/.
	 * @return string
	 */
	public static function asset_url( $file ) {
		return HIBI_PLUGIN_URL . 'assets/' . ltrim( $file, '/' );
	}

	/**
	 * Data the app needs for its first paint, printed into the shell.
	 *
	 * @return array
	 */
	public static function bootstrap() {
		$data               = Hibi_Rest::bootstrap_data();
		$data['locale']     = str_replace( '_', '-', determine_locale() );
		$data['i18n']       = Hibi_Strings::all();
		$data['prompts']    = Hibi_Strings::prompts();
		// wp_logout_url() returns an HTML-escaped URL; the app needs it raw.
		$data['logout_url'] = html_entity_decode( wp_logout_url( wp_login_url( self::url() ) ), ENT_QUOTES );
		return $data;
	}

	/**
	 * Paths the service worker stores on install.
	 *
	 * @return string[]
	 */
	public static function precache() {
		$base  = (string) wp_parse_url( HIBI_PLUGIN_URL, PHP_URL_PATH ) . 'assets/';
		$build = self::build();
		$paths = array();
		foreach ( $build ? $build['chunks'] : array() as $file ) {
			$paths[] = $base . 'app/' . $file;
		}
		foreach ( array( 'icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png', 'favicon-32.png' ) as $icon ) {
			$paths[] = $base . 'icons/' . $icon;
		}
		return array_values( array_unique( $paths ) );
	}

	/**
	 * Serves /hibi-sw.js and /hibi-manifest.webmanifest. Runs before any
	 * access gate redirects logged-out visitors: neither file holds journal
	 * data, and the browser must be able to update the worker at any time.
	 *
	 * @param WP $wp Request.
	 */
	public static function serve_files( $wp ) {
		$asset = isset( $wp->query_vars['hibi_asset'] ) ? $wp->query_vars['hibi_asset'] : '';
		if ( 'sw' === $asset ) {
			self::send_service_worker();
		}
		if ( 'manifest' === $asset ) {
			self::send_manifest();
		}
	}

	private static function send_service_worker() {
		$source   = HIBI_PLUGIN_DIR . 'assets/sw.js';
		$precache = self::precache();
		if ( is_user_logged_in() ) {
			switch_to_user_locale( get_current_user_id() );
		}
		$config = array(
			'version'  => substr( md5( HIBI_VERSION . implode( ',', $precache ) . filemtime( $source ) ), 0, 12 ),
			'home'     => self::base_path(),
			'precache' => $precache,
			'icon'     => (string) wp_parse_url( self::asset_url( 'icons/icon-192.png' ), PHP_URL_PATH ),
			'offline'  => __( 'You are offline. Connect and open the app again.', 'hibi-journal' ),
		);

		status_header( 200 );
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . self::base_path() );
		header( 'Cache-Control: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- JSON and a static script shipped with the plugin, sent as JavaScript.
		echo 'self.HIBI_SW = ' . wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) . ";\n" . file_get_contents( $source );
		exit;
	}

	private static function send_manifest() {
		if ( is_user_logged_in() ) {
			switch_to_user_locale( get_current_user_id() );
		}
		$icon     = static function ( $file, $size, $purpose ) {
			return array(
				'src'     => self::asset_url( 'icons/' . $file ),
				'sizes'   => $size,
				'type'    => 'image/png',
				'purpose' => $purpose,
			);
		};
		$manifest = array(
			'name'             => 'Hibi',
			'short_name'       => 'Hibi',
			'description'      => __( 'A private daily journal.', 'hibi-journal' ),
			'lang'             => str_replace( '_', '-', determine_locale() ),
			'id'               => self::base_path(),
			'start_url'        => self::base_path(),
			'scope'            => self::base_path(),
			'display'          => 'standalone',
			'background_color' => '#f7f6f3',
			'theme_color'      => '#f7f6f3',
			'icons'            => array(
				$icon( 'icon-192.png', '192x192', 'any' ),
				$icon( 'icon-512.png', '512x512', 'any' ),
				$icon( 'icon-maskable-512.png', '512x512', 'maskable' ),
			),
		);

		status_header( 200 );
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document.
		exit;
	}
}
