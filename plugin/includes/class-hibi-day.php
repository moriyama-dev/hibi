<?php
/**
 * Calendar day rules: which date "today" is, and date validation.
 *
 * A journal day starts at the user's day_start_hour (4:00 by default), so
 * writing at 1:00 still belongs to the previous day.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Day {

	/**
	 * Today's journal date for a user.
	 *
	 * @param int                     $user_id User ID.
	 * @param DateTimeImmutable|null $now     Current time; injectable for tests.
	 * @return string YYYY-MM-DD
	 */
	public static function today( $user_id, $now = null ) {
		$settings = Hibi_Settings::get( $user_id );
		return self::date_for( $now ? $now : new DateTimeImmutable( 'now', wp_timezone() ), $settings['day_start_hour'] );
	}

	/**
	 * Journal date for a moment, given the hour the day starts.
	 *
	 * Subtracting hours on the wall clock (not in UTC) keeps the boundary at
	 * the same local hour on daylight saving transition days.
	 *
	 * @param DateTimeImmutable $moment         Moment.
	 * @param int               $day_start_hour Hour (0-6).
	 * @return string YYYY-MM-DD
	 */
	public static function date_for( DateTimeImmutable $moment, $day_start_hour ) {
		$local = $moment->setTimezone( wp_timezone() );
		if ( (int) $local->format( 'G' ) < (int) $day_start_hour ) {
			$local = $local->modify( '-1 day' );
		}
		return $local->format( 'Y-m-d' );
	}

	/**
	 * Whether a string is a real calendar date in YYYY-MM-DD form.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function is_valid( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) && (int) $m[1] >= 1900;
	}

	/**
	 * Local noon of a date, as the post_date and post_date_gmt pair.
	 *
	 * @param string $date YYYY-MM-DD.
	 * @return array{0:string,1:string}
	 */
	public static function noon( $date ) {
		$noon = new DateTimeImmutable( $date . ' 12:00:00', wp_timezone() );
		return array(
			$noon->format( 'Y-m-d H:i:s' ),
			$noon->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * The date one day before or after.
	 *
	 * @param string $date   YYYY-MM-DD.
	 * @param int    $offset Days.
	 * @return string
	 */
	public static function shift( $date, $offset ) {
		$d = new DateTimeImmutable( $date . ' 12:00:00', new DateTimeZone( 'UTC' ) );
		return $d->modify( sprintf( '%+d day', $offset ) )->format( 'Y-m-d' );
	}
}
