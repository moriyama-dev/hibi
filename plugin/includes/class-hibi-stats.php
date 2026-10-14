<?php
/**
 * Writing statistics: streaks, totals.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Stats {

	/**
	 * Dates (ascending) of the author's entries that have any text, with
	 * their character counts.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,int> date => char count
	 */
	public static function written_days( $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregate over meta that WP_Query cannot express; cached per request by the caller.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.meta_value AS day, CAST(c.meta_value AS UNSIGNED) AS chars
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_hibi_date'
				INNER JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_hibi_char_count'
				WHERE p.post_type = %s AND p.post_status = 'private' AND p.post_author = %d
				AND CAST(c.meta_value AS UNSIGNED) > 0
				ORDER BY d.meta_value ASC",
				Hibi_Entry_Type::POST_TYPE,
				(int) $user_id
			)
		);

		$days = array();
		foreach ( $rows as $row ) {
			$days[ $row->day ] = (int) $row->chars;
		}
		return $days;
	}

	/**
	 * Statistics for the author, relative to their today.
	 *
	 * The current streak stays alive through today until today ends: if
	 * yesterday was written but today is still empty, the streak counts
	 * back from yesterday.
	 *
	 * @param int         $user_id User ID.
	 * @param string|null $today   YYYY-MM-DD; injectable for tests.
	 * @return array
	 */
	public static function get( $user_id, $today = null ) {
		$today = $today ? $today : Hibi_Day::today( $user_id );
		$days  = self::written_days( $user_id );

		$longest = 0;
		$run     = 0;
		$prev    = null;
		foreach ( array_keys( $days ) as $day ) {
			$run     = ( null !== $prev && Hibi_Day::shift( $prev, 1 ) === $day ) ? $run + 1 : 1;
			$longest = max( $longest, $run );
			$prev    = $day;
		}

		$current = 0;
		$cursor  = isset( $days[ $today ] ) ? $today : Hibi_Day::shift( $today, -1 );
		while ( isset( $days[ $cursor ] ) ) {
			++$current;
			$cursor = Hibi_Day::shift( $cursor, -1 );
		}

		$month       = substr( $today, 0, 7 );
		$month_count = 0;
		foreach ( array_keys( $days ) as $day ) {
			if ( 0 === strpos( $day, $month ) ) {
				++$month_count;
			}
		}

		return array(
			'current_streak' => $current,
			'longest_streak' => $longest,
			'total_entries'  => count( $days ),
			'total_chars'    => array_sum( $days ),
			'month_entries'  => $month_count,
			'written_today'  => isset( $days[ $today ] ),
		);
	}
}
