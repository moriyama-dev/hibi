<?php
/**
 * Reads and writes journal entries: one entry per author per day.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Entries {

	/**
	 * A revision is kept when the previous one is at least this old.
	 */
	const REVISION_INTERVAL = 600;

	/**
	 * Whether the save in progress may create a revision.
	 *
	 * @var bool
	 */
	private static $allow_revision = false;

	public static function init() {
		add_filter( 'wp_save_post_revision_post_has_changed', array( __CLASS__, 'filter_revision' ), 20, 3 );
	}

	/**
	 * The entry post for an author and a date, or null.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    YYYY-MM-DD.
	 * @return WP_Post|null
	 */
	public static function find( $user_id, $date ) {
		$query = new WP_Query(
			array(
				'post_type'              => Hibi_Entry_Type::POST_TYPE,
				'post_status'            => 'private',
				'author'                 => (int) $user_id,
				'meta_key'               => '_hibi_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed lookup on a small, per-author set.
				'meta_value'             => $date, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
				'suppress_filters'       => true,
			)
		);
		return $query->posts ? $query->posts[0] : null;
	}

	/**
	 * Entry data for the API. Never returns another author's entry.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    YYYY-MM-DD.
	 * @return array
	 */
	public static function get( $user_id, $date ) {
		$post = self::find( $user_id, $date );
		if ( ! $post ) {
			return array(
				'date'       => $date,
				'exists'     => false,
				'content'    => '',
				'mood'       => null,
				'tags'       => array(),
				'weather'    => null,
				'location'   => null,
				'char_count' => 0,
				'version'    => 0,
				'updated_at' => null,
			);
		}
		return self::to_array( $post );
	}

	/**
	 * Create or update an entry.
	 *
	 * The read-compare-write runs under a MySQL named lock per author and
	 * date, so two devices saving the first draft at the same moment still
	 * end up with a single entry, and a stale base_version is always caught.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    YYYY-MM-DD.
	 * @param array  $fields  content, mood, tags, weather, location, base_version, final.
	 * @return array|WP_Error Saved entry, or WP_Error (hibi_conflict carries the server entry).
	 */
	public static function save( $user_id, $date, array $fields ) {
		global $wpdb;

		$lock = 'hibi_entry_' . (int) $user_id . '_' . $date;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Named locks have no WordPress API.
		$got = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) );
		if ( 1 !== $got ) {
			return new WP_Error( 'hibi_busy', __( 'The entry is being saved from another request. Please try again.', 'hibi-journal' ), array( 'status' => 503 ) );
		}

		try {
			return self::save_locked( $user_id, $date, $fields );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- See above.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Body of save(), run while holding the lock.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    YYYY-MM-DD.
	 * @param array  $fields  Fields.
	 * @return array|WP_Error
	 */
	private static function save_locked( $user_id, $date, array $fields ) {
		$post    = self::find( $user_id, $date );
		$current = $post ? (int) get_post_meta( $post->ID, '_hibi_version', true ) : 0;
		$base    = isset( $fields['base_version'] ) ? (int) $fields['base_version'] : 0;

		if ( $base !== $current ) {
			return new WP_Error(
				'hibi_conflict',
				__( 'This entry was updated on another device.', 'hibi-journal' ),
				array(
					'status' => 409,
					'entry'  => self::get( $user_id, $date ),
				)
			);
		}

		$content = isset( $fields['content'] ) ? self::normalize_content( $fields['content'] ) : ( $post ? $post->post_content : '' );

		$postarr = array(
			'post_type'    => Hibi_Entry_Type::POST_TYPE,
			'post_status'  => 'private',
			'post_author'  => (int) $user_id,
			'post_title'   => $date,
			'post_content' => $content,
		);
		list( $postarr['post_date'], $postarr['post_date_gmt'] ) = Hibi_Day::noon( $date );

		self::$allow_revision = $post ? self::revision_due( $post->ID, ! empty( $fields['final'] ) ) : false;

		// Markdown is stored as typed. kses would rewrite text such as "a < b";
		// the reader escapes everything before rendering instead.
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $kses ) {
			kses_remove_filters();
		}

		if ( $post ) {
			$postarr['ID'] = $post->ID;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( $kses ) {
			kses_init_filters();
		}
		self::$allow_revision = false;

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_hibi_date', $date );
		update_post_meta( $post_id, '_hibi_version', $current + 1 );
		update_post_meta( $post_id, '_hibi_char_count', self::char_count( $content ) );
		// post_modified equals post_date (noon) on insert, so keep the real time here.
		update_post_meta( $post_id, '_hibi_updated_at', time() );

		if ( array_key_exists( 'mood', $fields ) ) {
			$mood = self::sanitize_mood( $fields['mood'] );
			if ( null === $mood ) {
				delete_post_meta( $post_id, '_hibi_mood' );
			} else {
				update_post_meta( $post_id, '_hibi_mood', $mood );
			}
		}

		if ( array_key_exists( 'tags', $fields ) ) {
			wp_set_object_terms( $post_id, self::sanitize_tags( $fields['tags'] ), Hibi_Entry_Type::TAXONOMY, false );
		}

		if ( array_key_exists( 'weather', $fields ) ) {
			$weather = self::sanitize_weather( $fields['weather'] );
			if ( null === $weather ) {
				delete_post_meta( $post_id, '_hibi_weather' );
			} else {
				update_post_meta( $post_id, '_hibi_weather', wp_slash( wp_json_encode( $weather ) ) );
			}
		}

		if ( array_key_exists( 'location', $fields ) ) {
			$settings = Hibi_Settings::get( $user_id );
			$location = $settings['location_enabled'] ? self::sanitize_location( $fields['location'] ) : null;
			if ( null === $location ) {
				delete_post_meta( $post_id, '_hibi_location' );
			} else {
				update_post_meta( $post_id, '_hibi_location', wp_slash( wp_json_encode( $location ) ) );
			}
		}

		/**
		 * Fires after a journal entry is saved.
		 *
		 * @param int    $post_id Entry post ID.
		 * @param int    $user_id Author.
		 * @param string $date    YYYY-MM-DD.
		 */
		do_action( 'hibi_entry_saved', $post_id, $user_id, $date );

		return self::to_array( get_post( $post_id ) );
	}

	/**
	 * Revisions are thinned out: one when the previous is ten minutes old,
	 * or when the writer leaves the page.
	 *
	 * @param int  $post_id Entry post ID.
	 * @param bool $final   Saved because the page is being left.
	 * @return bool
	 */
	private static function revision_due( $post_id, $final ) {
		if ( $final ) {
			return true;
		}
		$latest = wp_get_post_revisions(
			$post_id,
			array(
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! $latest ) {
			return true;
		}
		$revision = get_post( reset( $latest ) );
		return ! $revision || ( time() - (int) get_post_time( 'U', true, $revision ) ) >= self::REVISION_INTERVAL;
	}

	/**
	 * Suppresses the revision WordPress would otherwise make on every save.
	 *
	 * @param bool    $has_changed   Whether the post changed.
	 * @param WP_Post $last_revision Last revision.
	 * @param WP_Post $post          Post.
	 * @return bool
	 */
	public static function filter_revision( $has_changed, $last_revision, $post ) {
		unset( $last_revision );
		if ( $post instanceof WP_Post && Hibi_Entry_Type::POST_TYPE === $post->post_type && ! self::$allow_revision ) {
			return false;
		}
		return $has_changed;
	}

	/**
	 * API representation of an entry post.
	 *
	 * @param WP_Post $post Entry post.
	 * @return array
	 */
	public static function to_array( WP_Post $post ) {
		$mood  = get_post_meta( $post->ID, '_hibi_mood', true );
		$terms = wp_get_object_terms( $post->ID, Hibi_Entry_Type::TAXONOMY, array( 'fields' => 'names' ) );

		return array(
			'date'       => (string) get_post_meta( $post->ID, '_hibi_date', true ),
			'exists'     => true,
			'content'    => $post->post_content,
			'mood'       => '' === $mood ? null : (int) $mood,
			'tags'       => is_wp_error( $terms ) ? array() : array_values( $terms ),
			'weather'    => self::decode_json_meta( $post->ID, '_hibi_weather' ),
			'location'   => self::decode_json_meta( $post->ID, '_hibi_location' ),
			'char_count' => (int) get_post_meta( $post->ID, '_hibi_char_count', true ),
			'version'    => (int) get_post_meta( $post->ID, '_hibi_version', true ),
			'updated_at' => self::updated_at( $post ),
		);
	}

	/**
	 * Last save time in ISO 8601 (UTC).
	 *
	 * @param WP_Post $post Entry post.
	 * @return string
	 */
	private static function updated_at( WP_Post $post ) {
		$time = (int) get_post_meta( $post->ID, '_hibi_updated_at', true );
		return $time ? gmdate( 'c', $time ) : (string) get_post_modified_time( 'c', true, $post );
	}

	/**
	 * Characters excluding whitespace.
	 *
	 * @param string $content Text.
	 * @return int
	 */
	public static function char_count( $content ) {
		return mb_strlen( (string) preg_replace( '/\s+/u', '', (string) $content ) );
	}

	/**
	 * Plain text with unified newlines and no invalid UTF-8 or control
	 * characters other than tab and newline.
	 *
	 * @param mixed $content Raw content.
	 * @return string
	 */
	public static function normalize_content( $content ) {
		$content = wp_check_invalid_utf8( (string) $content, true );
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content );
	}

	/**
	 * Mood 1-5, or null for none.
	 *
	 * @param mixed $mood Raw mood.
	 * @return int|null
	 */
	public static function sanitize_mood( $mood ) {
		if ( null === $mood || '' === $mood ) {
			return null;
		}
		$mood = (int) $mood;
		return ( $mood >= 1 && $mood <= 5 ) ? $mood : null;
	}

	/**
	 * Unique tag names, trimmed, without a leading "#".
	 *
	 * @param mixed $tags Raw tags.
	 * @return string[]
	 */
	public static function sanitize_tags( $tags ) {
		$out = array();
		foreach ( (array) $tags as $tag ) {
			$tag = ltrim( sanitize_text_field( (string) $tag ), '#＃' );
			$tag = mb_substr( trim( $tag ), 0, 50 );
			if ( '' !== $tag ) {
				$out[ mb_strtolower( $tag ) ] = $tag;
			}
		}
		return array_slice( array_values( $out ), 0, 30 );
	}

	/**
	 * Weather snapshot, or null.
	 *
	 * @param mixed $weather Raw weather.
	 * @return array|null
	 */
	public static function sanitize_weather( $weather ) {
		if ( ! is_array( $weather ) || ! isset( $weather['weather_code'] ) ) {
			return null;
		}
		return array(
			'weather_code' => (int) $weather['weather_code'],
			'temp_max'     => isset( $weather['temp_max'] ) ? round( (float) $weather['temp_max'], 1 ) : null,
			'temp_min'     => isset( $weather['temp_min'] ) ? round( (float) $weather['temp_min'], 1 ) : null,
			'label'        => isset( $weather['label'] ) ? sanitize_text_field( (string) $weather['label'] ) : '',
			'source'       => 'open-meteo',
		);
	}

	/**
	 * Location, or null.
	 *
	 * @param mixed $location Raw location.
	 * @return array|null
	 */
	public static function sanitize_location( $location ) {
		if ( ! is_array( $location ) || ! isset( $location['lat'], $location['lng'] ) ) {
			return null;
		}
		$lat = (float) $location['lat'];
		$lng = (float) $location['lng'];
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return null;
		}
		return array(
			'lat'   => round( $lat, 4 ),
			'lng'   => round( $lng, 4 ),
			'label' => isset( $location['label'] ) ? sanitize_text_field( (string) $location['label'] ) : '',
		);
	}

	/**
	 * Decoded JSON meta, or null.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return array|null
	 */
	private static function decode_json_meta( $post_id, $key ) {
		$raw = get_post_meta( $post_id, $key, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}
}
