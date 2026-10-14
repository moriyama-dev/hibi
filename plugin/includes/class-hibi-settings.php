<?php
/**
 * Per-user settings stored as JSON in user meta.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Settings {

	const META_KEY = 'hibi_settings';

	/**
	 * Default values. Every key here is the full list of accepted keys.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'day_start_hour'   => 4,
			'reminder_enabled' => false,
			'reminder_time'    => '21:00',
			'weather_enabled'  => true,
			'location_enabled' => false,
			'prompts_enabled'  => true,
			'font_size'        => 'M',
			'font_family'      => 'sans',
			'color_theme'      => 'auto',
		);
	}

	/**
	 * Settings for a user, merged over the defaults.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get( $user_id ) {
		$raw    = get_user_meta( $user_id, self::META_KEY, true );
		$stored = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return self::sanitize( array_merge( self::defaults(), $stored ) );
	}

	/**
	 * Merge a partial update into the stored settings and save them.
	 *
	 * @param int   $user_id User ID.
	 * @param array $changes Partial settings.
	 * @return array The saved settings.
	 */
	public static function update( $user_id, array $changes ) {
		$settings = self::sanitize( array_merge( self::get( $user_id ), array_intersect_key( $changes, self::defaults() ) ) );
		update_user_meta( $user_id, self::META_KEY, wp_json_encode( $settings ) );
		return $settings;
	}

	/**
	 * Coerce every field to a valid value, falling back to the default.
	 *
	 * @param array $input Settings.
	 * @return array
	 */
	public static function sanitize( array $input ) {
		$defaults = self::defaults();
		$out      = array();

		$hour                  = isset( $input['day_start_hour'] ) ? (int) $input['day_start_hour'] : $defaults['day_start_hour'];
		$out['day_start_hour'] = ( $hour >= 0 && $hour <= 6 ) ? $hour : $defaults['day_start_hour'];

		foreach ( array( 'reminder_enabled', 'weather_enabled', 'location_enabled', 'prompts_enabled' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? rest_sanitize_boolean( $input[ $key ] ) : $defaults[ $key ];
		}

		$time                 = isset( $input['reminder_time'] ) ? (string) $input['reminder_time'] : '';
		$out['reminder_time'] = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : $defaults['reminder_time'];

		$choices = array(
			'font_size'   => array( 'S', 'M', 'L' ),
			'font_family' => array( 'sans', 'serif' ),
			'color_theme' => array( 'auto', 'light', 'dark' ),
		);
		foreach ( $choices as $key => $allowed ) {
			$value       = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$out[ $key ] = in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
		}

		return $out;
	}
}
