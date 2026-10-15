<?php
/**
 * Read-only queries for looking back: timeline, calendar, year, on this day.
 *
 * Every query is limited to one author's private entries.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Query {

	/**
	 * Characters for each calendar dot size (1: short, 2: medium, 3: long).
	 */
	const LEVELS = array( 1, 200, 800 );

	/**
	 * Length of the excerpt returned for timeline cards.
	 */
	const EXCERPT_CHARS = 240;

	/**
	 * Size level 0-3 for a character count.
	 *
	 * @param int $chars Characters.
	 * @return int
	 */
	public static function level( $chars ) {
		$level = 0;
		foreach ( self::LEVELS as $i => $min ) {
			if ( $chars >= $min ) {
				$level = $i + 1;
			}
		}
		return $level;
	}

	/**
	 * Entry rows with their date, mood, character count and photo count.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    from, to (inclusive, YYYY-MM-DD), before (exclusive), mood, tag, has_photo, month_day (MM-DD), limit, excerpt.
	 * @return object[] Rows ordered by date, newest first.
	 */
	public static function rows( $user_id, array $args = array() ) {
		global $wpdb;

		$where  = array( 'p.post_type = %s', "p.post_status = 'private'", 'p.post_author = %d' );
		$params = array( Hibi_Entry_Type::POST_TYPE, (int) $user_id );
		$joins  = '';

		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'd.meta_value >= %s';
			$params[] = $args['from'];
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'd.meta_value <= %s';
			$params[] = $args['to'];
		}
		if ( ! empty( $args['before'] ) ) {
			$where[]  = 'd.meta_value < %s';
			$params[] = $args['before'];
		}
		if ( ! empty( $args['month_day'] ) ) {
			$where[]  = 'RIGHT(d.meta_value, 5) = %s';
			$params[] = $args['month_day'];
		}
		if ( ! empty( $args['mood'] ) ) {
			$where[]  = 'CAST(m.meta_value AS UNSIGNED) = %d';
			$params[] = (int) $args['mood'];
		}
		if ( ! empty( $args['has_photo'] ) ) {
			$where[] = 'ph.photos > 0';
		}
		if ( ! empty( $args['tag'] ) ) {
			$joins   .= " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.name = %s";
			$join_params = array( Hibi_Entry_Type::TAXONOMY, $args['tag'] );
		}
		if ( empty( $args['include_empty'] ) ) {
			$where[] = 'CAST(c.meta_value AS UNSIGNED) > 0 OR ph.photos > 0 OR m.meta_value IS NOT NULL';
		}

		$excerpt = empty( $args['excerpt'] ) ? '' : ', LEFT(p.post_content, ' . (int) self::EXCERPT_CHARS . ') AS excerpt';
		$limit   = empty( $args['limit'] ) ? '' : ' LIMIT ' . max( 1, (int) $args['limit'] );

		$where_sql = '(' . implode( ') AND (', $where ) . ')';

		$sql = "SELECT p.ID AS id, d.meta_value AS day, CAST(c.meta_value AS UNSIGNED) AS chars,
				m.meta_value AS mood, COALESCE(ph.photos, 0) AS photos{$excerpt}
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_hibi_date'
			LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_hibi_char_count'
			LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_hibi_mood'
			LEFT JOIN (
				SELECT post_parent, COUNT(*) AS photos FROM {$wpdb->posts}
				WHERE post_type = 'attachment' AND post_author = %d
				GROUP BY post_parent
			) ph ON ph.post_parent = p.ID
			{$joins}
			WHERE {$where_sql}
			ORDER BY d.meta_value DESC{$limit}";

		$all_params = array_merge( array( (int) $user_id ), isset( $join_params ) ? $join_params : array(), $params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names and the fixed SQL fragments above are built from trusted values; every value goes through prepare().
		return $wpdb->get_results( $wpdb->prepare( $sql, $all_params ) );
	}

	/**
	 * Calendar month: one item per day that has an entry.
	 *
	 * @param int $user_id User ID.
	 * @param int $year    Year.
	 * @param int $month   Month 1-12.
	 * @return array
	 */
	public static function month( $user_id, $year, $month ) {
		$from = sprintf( '%04d-%02d-01', $year, $month );
		$to   = sprintf( '%04d-%02d-31', $year, $month );
		$days = array();
		foreach ( self::rows(
			$user_id,
			array(
				'from' => $from,
				'to'   => $to,
			)
		) as $row ) {
			$days[ $row->day ] = array(
				'mood'   => null === $row->mood ? null : (int) $row->mood,
				'level'  => self::level( (int) $row->chars ),
				'chars'  => (int) $row->chars,
				'photos' => (int) $row->photos,
			);
		}
		return array(
			'year'  => (int) $year,
			'month' => (int) $month,
			'days'  => (object) $days,
		);
	}

	/**
	 * Year heat map: level and mood per written day.
	 *
	 * @param int $user_id User ID.
	 * @param int $year    Year.
	 * @return array
	 */
	public static function year( $user_id, $year ) {
		$days = array();
		foreach ( self::rows(
			$user_id,
			array(
				'from' => sprintf( '%04d-01-01', $year ),
				'to'   => sprintf( '%04d-12-31', $year ),
			)
		) as $row ) {
			$days[ $row->day ] = array(
				self::level( (int) $row->chars ),
				null === $row->mood ? null : (int) $row->mood,
			);
		}
		return array(
			'year' => (int) $year,
			'days' => (object) $days,
		);
	}

	/**
	 * Timeline page, newest first. The cursor is the last date of the
	 * previous page.
	 *
	 * @param int   $user_id User ID.
	 * @param array $filters tag, mood, has_photo, from, to, cursor, limit.
	 * @return array{items:array,next_cursor:string|null}
	 */
	public static function timeline( $user_id, array $filters ) {
		$limit = min( 50, max( 1, isset( $filters['limit'] ) ? (int) $filters['limit'] : 20 ) );
		$rows  = self::rows(
			$user_id,
			array(
				'from'      => isset( $filters['from'] ) ? $filters['from'] : '',
				'to'        => isset( $filters['to'] ) ? $filters['to'] : '',
				'before'    => isset( $filters['cursor'] ) ? $filters['cursor'] : '',
				'mood'      => isset( $filters['mood'] ) ? $filters['mood'] : 0,
				'tag'       => isset( $filters['tag'] ) ? $filters['tag'] : '',
				'has_photo' => ! empty( $filters['has_photo'] ),
				'limit'     => $limit + 1,
				'excerpt'   => true,
			)
		);

		$more  = count( $rows ) > $limit;
		$rows  = array_slice( $rows, 0, $limit );
		$items = self::cards( $rows );

		return array(
			'items'       => $items,
			'next_cursor' => $more && $rows ? end( $rows )->day : null,
		);
	}

	/**
	 * Past years' entries on the same month and day.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    YYYY-MM-DD.
	 * @return array
	 */
	public static function on_this_day( $user_id, $date ) {
		$rows = self::rows(
			$user_id,
			array(
				'month_day' => substr( $date, 5 ),
				'before'    => $date,
				'excerpt'   => true,
			)
		);
		return array(
			'date'  => $date,
			'items' => self::cards( $rows ),
		);
	}

	/**
	 * Card data for timeline-style lists.
	 *
	 * @param object[] $rows Rows with excerpt.
	 * @return array
	 */
	private static function cards( array $rows ) {
		if ( ! $rows ) {
			return array();
		}
		$ids = wp_list_pluck( $rows, 'id' );
		update_object_term_cache( $ids, Hibi_Entry_Type::POST_TYPE );
		$photos = Hibi_Photos::for_entries( $ids );

		$items = array();
		foreach ( $rows as $row ) {
			$terms   = get_the_terms( (int) $row->id, Hibi_Entry_Type::TAXONOMY );
			$items[] = array(
				'date'    => $row->day,
				'mood'    => null === $row->mood ? null : (int) $row->mood,
				'chars'   => (int) $row->chars,
				'excerpt' => isset( $row->excerpt ) ? self::plain_excerpt( $row->excerpt ) : '',
				'tags'    => is_array( $terms ) ? array_values( wp_list_pluck( $terms, 'name' ) ) : array(),
				'photos'  => isset( $photos[ (int) $row->id ] ) ? array_slice( $photos[ (int) $row->id ], 0, 4 ) : array(),
			);
		}
		return $items;
	}

	/**
	 * Markdown excerpt with photo tokens and heading/list markers removed,
	 * keeping line breaks so the card can show the first lines.
	 *
	 * @param string $text Raw Markdown.
	 * @return string
	 */
	public static function plain_excerpt( $text ) {
		$text = preg_replace( '/!\[[^\]]*\]\([^)]*\)/u', '', (string) $text );
		$text = preg_replace( '/^\s{0,3}(#{1,6}\s+|>\s?|[-*+]\s+(\[[ xX]\]\s+)?|\d+\.\s+)/mu', '', $text );
		$text = preg_replace( '/(\*\*|__|\*|_|`)/u', '', $text );
		$text = preg_replace( "/\n{2,}/u", "\n", $text );
		return trim( (string) $text );
	}

	/**
	 * Tags the author has used, most used first.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array{name:string,count:int}>
	 */
	public static function tags( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Per-author tag counts; the term API counts across all authors.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.name, COUNT(*) AS uses
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE p.post_type = %s AND p.post_status = 'private' AND p.post_author = %d
				GROUP BY t.term_id, t.name
				ORDER BY uses DESC, t.name ASC
				LIMIT 200",
				Hibi_Entry_Type::TAXONOMY,
				Hibi_Entry_Type::POST_TYPE,
				(int) $user_id
			)
		);
		return array_map(
			static function ( $row ) {
				return array(
					'name'  => $row->name,
					'count' => (int) $row->uses,
				);
			},
			$rows
		);
	}
}
