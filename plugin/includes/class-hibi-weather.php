<?php
/**
 * The day's weather from Open-Meteo (free, no API key), fetched by the
 * server so the browser never talks to a third party.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Weather {

	const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';
	const ARCHIVE_URL  = 'https://archive-api.open-meteo.com/v1/archive';

	/**
	 * The forecast API covers this many past days; older dates use the archive.
	 */
	const FORECAST_PAST_DAYS = 90;

	/**
	 * Daily weather for a place and date.
	 *
	 * @param float  $lat  Latitude.
	 * @param float  $lng  Longitude.
	 * @param string $date YYYY-MM-DD.
	 * @return array|WP_Error weather_code, temp_max, temp_min, label, source.
	 */
	public static function get( $lat, $lng, $date ) {
		$lat = round( (float) $lat, 2 );
		$lng = round( (float) $lng, 2 );
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return new WP_Error( 'hibi_weather_place', __( 'The location is invalid.', 'hibi-journal' ), array( 'status' => 400 ) );
		}

		$key    = 'hibi_weather_' . md5( "{$lat},{$lng},{$date}" );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$age = ( time() - strtotime( $date . ' 12:00:00' ) ) / DAY_IN_SECONDS;
		$url = add_query_arg(
			array(
				'latitude'   => $lat,
				'longitude'  => $lng,
				'daily'      => 'weather_code,temperature_2m_max,temperature_2m_min',
				'timezone'   => wp_timezone_string(),
				'start_date' => $date,
				'end_date'   => $date,
			),
			$age > self::FORECAST_PAST_DAYS ? self::ARCHIVE_URL : self::FORECAST_URL
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'user-agent' => 'HibiJournal/' . HIBI_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			self::log( 'Open-Meteo request failed: ' . $response->get_error_message() );
			return new WP_Error( 'hibi_weather_unavailable', __( 'The weather service could not be reached.', 'hibi-journal' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! isset( $data['daily']['weather_code'][0] ) ) {
			self::log( sprintf( 'Open-Meteo returned HTTP %d for %s', $code, $date ) );
			return new WP_Error( 'hibi_weather_unavailable', __( 'No weather is available for this day.', 'hibi-journal' ), array( 'status' => 502 ) );
		}

		$weather_code = (int) $data['daily']['weather_code'][0];
		$weather      = array(
			'weather_code' => $weather_code,
			'temp_max'     => isset( $data['daily']['temperature_2m_max'][0] ) ? round( (float) $data['daily']['temperature_2m_max'][0], 1 ) : null,
			'temp_min'     => isset( $data['daily']['temperature_2m_min'][0] ) ? round( (float) $data['daily']['temperature_2m_min'][0], 1 ) : null,
			'label'        => self::label( $weather_code ),
			'source'       => 'open-meteo',
		);
		set_transient( $key, $weather, HOUR_IN_SECONDS );
		return $weather;
	}

	/**
	 * Places matching a name, for choosing where the weather comes from.
	 *
	 * @param string $name Place name, e.g. "Toronto" or "トロント".
	 * @param string $lang Result language (ja, en, ...).
	 * @return array|WP_Error List of name, admin, country, lat, lng.
	 */
	public static function geocode( $name, $lang = 'ja' ) {
		$name = trim( (string) $name );
		if ( mb_strlen( $name ) < 2 ) {
			return array();
		}
		$key    = 'hibi_geo_' . md5( $name . '|' . $lang );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_remote_get(
			add_query_arg(
				array(
					'name'     => rawurlencode( $name ),
					'count'    => 8,
					'language' => preg_replace( '/[^a-z]/', '', strtolower( substr( $lang, 0, 2 ) ) ),
					'format'   => 'json',
				),
				'https://geocoding-api.open-meteo.com/v1/search'
			),
			array(
				'timeout'    => 8,
				'user-agent' => 'HibiJournal/' . HIBI_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			self::log( 'Open-Meteo geocoding failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ) );
			return new WP_Error( 'hibi_weather_unavailable', __( 'The place search could not be reached.', 'hibi-journal' ), array( 'status' => 502 ) );
		}
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$places = array();
		foreach ( isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array() as $row ) {
			$places[] = array(
				'name'    => isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '',
				'admin'   => isset( $row['admin1'] ) ? sanitize_text_field( $row['admin1'] ) : '',
				'country' => isset( $row['country'] ) ? sanitize_text_field( $row['country'] ) : '',
				'lat'     => round( (float) $row['latitude'], 2 ),
				'lng'     => round( (float) $row['longitude'], 2 ),
			);
		}
		set_transient( $key, $places, DAY_IN_SECONDS );
		return $places;
	}

	/**
	 * Short label for a WMO weather code.
	 *
	 * @param int $code WMO code.
	 * @return string
	 */
	public static function label( $code ) {
		if ( 0 === $code ) {
			return __( 'Clear', 'hibi-journal' );
		}
		if ( $code <= 2 ) {
			return __( 'Partly cloudy', 'hibi-journal' );
		}
		if ( 3 === $code ) {
			return __( 'Cloudy', 'hibi-journal' );
		}
		if ( $code <= 48 ) {
			return __( 'Fog', 'hibi-journal' );
		}
		if ( $code <= 57 ) {
			return __( 'Drizzle', 'hibi-journal' );
		}
		if ( $code <= 67 || ( $code >= 80 && $code <= 82 ) ) {
			return __( 'Rain', 'hibi-journal' );
		}
		if ( $code <= 77 || 85 === $code || 86 === $code ) {
			return __( 'Snow', 'hibi-journal' );
		}
		if ( $code >= 95 ) {
			return __( 'Thunderstorm', 'hibi-journal' );
		}
		return __( 'Cloudy', 'hibi-journal' );
	}

	/**
	 * @param string $message Message.
	 */
	private static function log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[hibi-journal] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug log only.
		}
	}
}
