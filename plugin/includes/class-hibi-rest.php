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
