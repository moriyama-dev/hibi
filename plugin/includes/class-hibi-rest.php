<?php
/**
 * REST API under hibi/v1. Every route requires a logged-in user and only
 * ever touches that user's own entries.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Rest {

	const NAMESPACE_V1 = 'hibi/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_ajax_hibi_nonce', array( __CLASS__, 'ajax_nonce' ) );
	}

	public static function register_routes() {
		$auth = array( __CLASS__, 'permission' );
		$date = array(
			'date' => array(
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( __CLASS__, 'validate_date' ),
			),
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/bootstrap',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'bootstrap' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/entries/(?P<date>\d{4}-\d{2}-\d{2})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_entry' ),
					'permission_callback' => $auth,
					'args'                => $date,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'put_entry' ),
					'permission_callback' => $auth,
					'args'                => array_merge(
						$date,
						array(
							'content'      => array(
								'type'      => 'string',
								'maxLength' => 200000,
							),
							'mood'         => array(
								'type'    => array( 'integer', 'null' ),
								'minimum' => 1,
								'maximum' => 5,
							),
							'tags'         => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'weather'      => array( 'type' => array( 'object', 'null' ) ),
							'location'     => array( 'type' => array( 'object', 'null' ) ),
							'base_version' => array(
								'type'     => 'integer',
								'minimum'  => 0,
								'required' => true,
							),
							'final'        => array(
								'type'    => 'boolean',
								'default' => false,
							),
						)
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => $auth,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'put_settings' ),
					'permission_callback' => $auth,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_stats' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_entries' ),
				'permission_callback' => $auth,
				'args'                => array(
					'from'      => array(
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_optional_date' ),
					),
					'to'        => array(
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_optional_date' ),
					),
					'cursor'    => array(
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_optional_date' ),
					),
					'tag'       => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'mood'      => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 5,
					),
					'has_photo' => array( 'type' => 'boolean' ),
					'limit'     => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 50,
						'default' => 20,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_calendar' ),
				'permission_callback' => $auth,
				'args'                => array(
					'year'  => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1900,
						'maximum'  => 9999,
					),
					'month' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
						'maximum'  => 12,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/year',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_year' ),
				'permission_callback' => $auth,
				'args'                => array(
					'year' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1900,
						'maximum'  => 9999,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/on-this-day',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_on_this_day' ),
				'permission_callback' => $auth,
				'args'                => array(
					'date' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => $auth,
				'args'                => array(
					'q'      => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 200,
					),
					'cursor' => array(
						'type'              => 'string',
						'validate_callback' => array( __CLASS__, 'validate_optional_date' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/tags',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_tags' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/entries/(?P<date>\d{4}-\d{2}-\d{2})/photos',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'upload_photo' ),
				'permission_callback' => $auth,
				'args'                => $date,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/photos/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_photo' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/weather',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_weather' ),
				'permission_callback' => $auth,
				'args'                => array(
					'lat'  => array(
						'type'     => 'number',
						'required' => true,
						'minimum'  => -90,
						'maximum'  => 90,
					),
					'lng'  => array(
						'type'     => 'number',
						'required' => true,
						'minimum'  => -180,
						'maximum'  => 180,
					),
					'date' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( __CLASS__, 'validate_date' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/places',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_places' ),
				'permission_callback' => $auth,
				'args'                => array(
					'q' => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 80,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/push/subscribe',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'push_subscribe' ),
					'permission_callback' => $auth,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'push_unsubscribe' ),
					'permission_callback' => $auth,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'export' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'import' ),
				'permission_callback' => $auth,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/push/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'push_test' ),
				'permission_callback' => $auth,
			)
		);
	}

	/**
	 * Logged-in users with the journal capability only.
	 *
	 * @return true|WP_Error
	 */
	public static function permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'hibi_not_logged_in', __( 'Please log in.', 'hibi-journal' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( self::capability() ) ) {
			return new WP_Error( 'hibi_forbidden', __( 'You cannot use the journal.', 'hibi-journal' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Capability needed to keep a journal.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability needed to keep a journal.
		 *
		 * @param string $capability Default "read": every logged-in user has a private journal.
		 */
		return (string) apply_filters( 'hibi_capability', 'read' );
	}

	/**
	 * @param string $value Date parameter.
	 * @return bool
	 */
	public static function validate_date( $value ) {
		return Hibi_Day::is_valid( $value );
	}

	/**
	 * Everything the app needs for its first paint.
	 *
	 * @return array
	 */
	public static function bootstrap_data() {
		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$today   = Hibi_Day::today( $user_id );

		return array(
			'user'     => array(
				'id'           => $user_id,
				'display_name' => $user->display_name,
			),
			'settings' => Hibi_Settings::get( $user_id ),
			'today'    => $today,
			'timezone' => wp_timezone_string(),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'stats'    => Hibi_Stats::get( $user_id, $today ),
			'entry'    => Hibi_Entries::get( $user_id, $today ),
			'api'      => array(
				'root'      => esc_url_raw( rest_url( self::NAMESPACE_V1 . '/' ) ),
				'nonce_url' => esc_url_raw( add_query_arg( 'action', 'hibi_nonce', admin_url( 'admin-ajax.php' ) ) ),
				'login_url' => esc_url_raw( wp_login_url() ),
				// Where the app lives: "/" with the Hibi theme, "/journal/" otherwise.
				'home_path' => Hibi_App::base_path(),
				'sw_url'    => (string) wp_parse_url( home_url( '/hibi-sw.js' ), PHP_URL_PATH ),
				'photo_url' => (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ),
			),
			'push'     => array(
				'public_key' => Hibi_Push::public_key(),
			),
		);
	}

	public static function bootstrap() {
		return self::no_store( rest_ensure_response( self::bootstrap_data() ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_entry( WP_REST_Request $request ) {
		return self::no_store( rest_ensure_response( Hibi_Entries::get( get_current_user_id(), $request['date'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function put_entry( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$date    = $request['date'];

		if ( $date > Hibi_Day::shift( Hibi_Day::today( $user_id ), 1 ) ) {
			return new WP_Error( 'hibi_future_date', __( 'You cannot write an entry for a future date.', 'hibi-journal' ), array( 'status' => 400 ) );
		}

		$fields = array(
			'base_version' => (int) $request['base_version'],
			'final'        => (bool) $request['final'],
		);
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : $request->get_body_params();
		foreach ( array( 'content', 'mood', 'tags', 'weather', 'location' ) as $key ) {
			if ( array_key_exists( $key, (array) $params ) ) {
				$fields[ $key ] = $request[ $key ];
			}
		}

		$result = Hibi_Entries::save( $user_id, $date, $fields );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::no_store( rest_ensure_response( $result ) );
	}

	/**
	 * @param mixed $value Optional date parameter.
	 * @return bool
	 */
	public static function validate_optional_date( $value ) {
		return '' === $value || null === $value || Hibi_Day::is_valid( $value );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list_entries( WP_REST_Request $request ) {
		return self::no_store(
			rest_ensure_response(
				Hibi_Query::timeline(
					get_current_user_id(),
					array(
						'from'      => (string) $request['from'],
						'to'        => (string) $request['to'],
						'cursor'    => (string) $request['cursor'],
						'tag'       => (string) $request['tag'],
						'mood'      => (int) $request['mood'],
						'has_photo' => (bool) $request['has_photo'],
						'limit'     => (int) $request['limit'],
					)
				)
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_calendar( WP_REST_Request $request ) {
		return self::no_store( rest_ensure_response( Hibi_Query::month( get_current_user_id(), (int) $request['year'], (int) $request['month'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_year( WP_REST_Request $request ) {
		return self::no_store( rest_ensure_response( Hibi_Query::year( get_current_user_id(), (int) $request['year'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_on_this_day( WP_REST_Request $request ) {
		return self::no_store( rest_ensure_response( Hibi_Query::on_this_day( get_current_user_id(), $request['date'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function search( WP_REST_Request $request ) {
		return self::no_store( rest_ensure_response( Hibi_Search::search( get_current_user_id(), (string) $request['q'], (string) $request['cursor'] ) ) );
	}

	public static function get_tags() {
		return self::no_store( rest_ensure_response( Hibi_Query::tags( get_current_user_id() ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload_photo( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'hibi_photo_upload', __( 'No photo was sent.', 'hibi-journal' ), array( 'status' => 400 ) );
		}
		$user_id = get_current_user_id();
		if ( $request['date'] > Hibi_Day::shift( Hibi_Day::today( $user_id ), 1 ) ) {
			return new WP_Error( 'hibi_future_date', __( 'You cannot write an entry for a future date.', 'hibi-journal' ), array( 'status' => 400 ) );
		}
		$photo = Hibi_Photos::upload( $user_id, $request['date'], $files['file'] );
		if ( is_wp_error( $photo ) ) {
			return $photo;
		}
		$response = rest_ensure_response( $photo );
		$response->set_status( 201 );
		return self::no_store( $response );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_photo( WP_REST_Request $request ) {
		$result = Hibi_Photos::delete( (int) $request['id'], get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::no_store( rest_ensure_response( array( 'deleted' => true ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_weather( WP_REST_Request $request ) {
		$weather = Hibi_Weather::get( $request['lat'], $request['lng'], $request['date'] );
		if ( is_wp_error( $weather ) ) {
			return $weather;
		}
		return self::no_store( rest_ensure_response( $weather ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_places( WP_REST_Request $request ) {
		$query = (string) $request['q'];
		// Place names are matched in the result language, so a Japanese query
		// needs Japanese results whatever the user's locale.
		$lang   = preg_match( '/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $query ) ? 'ja' : substr( get_user_locale(), 0, 2 );
		$places = Hibi_Weather::geocode( $query, $lang );
		if ( is_wp_error( $places ) ) {
			return $places;
		}
		return self::no_store( rest_ensure_response( $places ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function push_subscribe( WP_REST_Request $request ) {
		$ready = Hibi_Push::available();
		if ( true !== $ready ) {
			return $ready;
		}
		$result = Hibi_Push::subscribe( get_current_user_id(), (array) $request->get_json_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::no_store( rest_ensure_response( array( 'subscribed' => true ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function push_unsubscribe( WP_REST_Request $request ) {
		$params = (array) $request->get_json_params();
		Hibi_Push::unsubscribe( get_current_user_id(), isset( $params['endpoint'] ) ? (string) $params['endpoint'] : '' );
		return self::no_store( rest_ensure_response( array( 'subscribed' => false ) ) );
	}

	/**
	 * Sends the whole journal as a ZIP download. The file is streamed
	 * directly: a REST response cannot carry a binary body.
	 *
	 * @return WP_Error|void
	 */
	public static function export() {
		$path = Hibi_Export::build_zip( get_current_user_id() );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$name = sprintf( 'hibi-%s.zip', wp_date( 'Y-m-d' ) );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams the export.
		wp_delete_file( $path );
		exit;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function import( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file']['tmp_name'] ) || ! is_uploaded_file( $files['file']['tmp_name'] ) ) {
			return new WP_Error( 'hibi_import_file', __( 'No file was sent.', 'hibi-journal' ), array( 'status' => 400 ) );
		}
		$result = Hibi_Export::import( get_current_user_id(), $files['file']['tmp_name'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::no_store( rest_ensure_response( $result ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function push_test() {
		$result = Hibi_Push::send( get_current_user_id(), __( 'Hibi', 'hibi-journal' ), __( 'Notifications are working.', 'hibi-journal' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::no_store( rest_ensure_response( $result ) );
	}

	public static function get_settings() {
		return self::no_store( rest_ensure_response( Hibi_Settings::get( get_current_user_id() ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function put_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		return self::no_store( rest_ensure_response( Hibi_Settings::update( get_current_user_id(), is_array( $params ) ? $params : array() ) ) );
	}

	public static function get_stats() {
		return self::no_store( rest_ensure_response( Hibi_Stats::get( get_current_user_id() ) ) );
	}

	/**
	 * A fresh REST nonce, authenticated by the login cookie alone.
	 *
	 * This lives on admin-ajax rather than REST: once the REST nonce has
	 * expired, WordPress treats a REST request as logged out, so a REST
	 * route could never hand out the replacement. Reading the response
	 * cross-site is blocked by the same-origin policy.
	 */
	public static function ajax_nonce() {
		nocache_headers();
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
	}

	/**
	 * Journal data must never sit in a shared cache.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_REST_Response
	 */
	private static function no_store( $response ) {
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
}
