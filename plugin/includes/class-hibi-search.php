<?php
/**
 * Full-text search over one author's entries.
 *
 * Kept in one class so the LIKE scan can later be swapped for a full-text
 * index without touching the API.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Search {

	const PAGE_SIZE = 20;

	/**
	 * Characters of context on each side of the first match.
	 */
	const CONTEXT = 40;

	/**
	 * Collation that tells ば from は but treats hiragana and katakana, and
	 * upper and lower case, alike. MySQL 8 only.
	 */
	const COLLATION = 'utf8mb4_0900_as_ci';

	/**
	 * Search terms: split on ASCII and full-width spaces, at most 5.
	 *
	 * @param string $query Query.
	 * @return string[]
	 */
	public static function terms( $query ) {
		$parts = preg_split( '/[\s\x{3000}]+/u', trim( (string) $query ), -1, PREG_SPLIT_NO_EMPTY );
		$parts = array_map(
			static function ( $term ) {
				return mb_substr( $term, 0, 50 );
			},
			$parts ? $parts : array()
		);
		return array_slice( array_values( array_unique( $parts ) ), 0, 5 );
	}

	/**
	 * Entries containing every term, newest first.
	 *
	 * @param int    $user_id User ID.
	 * @param string $query   Query.
	 * @param string $cursor  Date of the last result on the previous page.
	 * @return array{items:array,next_cursor:string|null,terms:string[]}
	 */
	public static function search( $user_id, $query, $cursor = '' ) {
		global $wpdb;

		$terms = self::terms( $query );
		if ( ! $terms ) {
			return array(
				'items'       => array(),
				'next_cursor' => null,
				'terms'       => array(),
			);
		}

		$collate = self::collation_available() ? ' COLLATE ' . self::COLLATION : '';
		$where   = array( 'p.post_type = %s', "p.post_status = 'private'", 'p.post_author = %d' );
		$params  = array( Hibi_Entry_Type::POST_TYPE, (int) $user_id );

		foreach ( $terms as $term ) {
			$where[]  = "p.post_content LIKE %s{$collate}";
			$params[] = '%' . $wpdb->esc_like( $term ) . '%';
		}
		if ( Hibi_Day::is_valid( $cursor ) ) {
			$where[]  = 'd.meta_value < %s';
			$params[] = $cursor;
		}
		$params[] = self::PAGE_SIZE + 1;

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT p.ID AS id, d.meta_value AS day, p.post_content AS content, m.meta_value AS mood
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_hibi_date'
			LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_hibi_mood'
			WHERE {$where_sql}
			ORDER BY d.meta_value DESC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fixed SQL with trusted table names; values go through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$more  = count( $rows ) > self::PAGE_SIZE;
		$rows  = array_slice( $rows, 0, self::PAGE_SIZE );
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'date'     => $row->day,
				'mood'     => null === $row->mood ? null : (int) $row->mood,
				'segments' => self::snippet( Hibi_Query::plain_excerpt( $row->content ), $terms ),
			);
		}

		return array(
			'items'       => $items,
			'next_cursor' => $more && $rows ? end( $rows )->day : null,
			'terms'       => $terms,
		);
	}

	/**
	 * The text around the first match, cut into plain and matched segments
	 * so the client can highlight without parsing HTML.
	 *
	 * @param string   $text  Plain text.
	 * @param string[] $terms Terms.
	 * @return array<int,array{0:string,1:bool}> [text, is_match]
	 */
	public static function snippet( $text, array $terms ) {
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		$fold = self::fold( $text );

		$first = null;
		foreach ( $terms as $term ) {
			$pos = mb_strpos( $fold, self::fold( $term ) );
			if ( false !== $pos && ( null === $first || $pos < $first ) ) {
				$first = $pos;
			}
		}

		$length = mb_strlen( $text );
		$start  = null === $first ? 0 : max( 0, $first - self::CONTEXT );
		$end    = min( $length, ( null === $first ? 0 : $first ) + self::CONTEXT * 2 );
		$end    = max( $end, min( $length, $start + self::CONTEXT * 2 ) );

		$window      = mb_substr( $text, $start, $end - $start );
		$window_fold = mb_substr( $fold, $start, $end - $start );

		// Mark every occurrence of every term inside the window.
		$marks = array_fill( 0, mb_strlen( $window ), false );
		foreach ( $terms as $term ) {
			$needle = self::fold( $term );
			$n      = mb_strlen( $needle );
			$offset = 0;
			while ( $n > 0 && false !== ( $pos = mb_strpos( $window_fold, $needle, $offset ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				for ( $i = $pos; $i < $pos + $n; $i++ ) {
					$marks[ $i ] = true;
				}
				$offset = $pos + $n;
			}
		}

		$segments = array();
		$buffer   = '';
		$state    = null;
		foreach ( mb_str_split( $window ) as $i => $char ) {
			if ( $marks[ $i ] !== $state && '' !== $buffer ) {
				$segments[] = array( $buffer, (bool) $state );
				$buffer     = '';
			}
			$state   = $marks[ $i ];
			$buffer .= $char;
		}
		if ( '' !== $buffer ) {
			$segments[] = array( $buffer, (bool) $state );
		}

		if ( $start > 0 ) {
			array_unshift( $segments, array( '…', false ) );
		}
		if ( $end < $length ) {
			$segments[] = array( '…', false );
		}
		return $segments;
	}

	/**
	 * Case- and kana-folded text with the same length as the input, so
	 * positions found in it apply to the original.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function fold( $text ) {
		return mb_strtolower( mb_convert_kana( $text, 'C' ) );
	}

	/**
	 * @return bool
	 */
	private static function collation_available() {
		static $available = null;
		if ( null === $available ) {
			$cached = get_transient( 'hibi_search_collation' );
			if ( false === $cached ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off capability probe, cached for a day.
				$cached = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.COLLATIONS WHERE COLLATION_NAME = %s', self::COLLATION ) ) > 0 ? 'yes' : 'no';
				set_transient( 'hibi_search_collation', $cached, DAY_IN_SECONDS );
			}
			$available = 'yes' === $cached;
		}
		return $available;
	}
}
