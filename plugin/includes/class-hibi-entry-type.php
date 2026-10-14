<?php
/**
 * Registers the journal entry post type, the tag taxonomy and their guards.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Entry_Type {

	const POST_TYPE = 'hibi_entry';
	const TAXONOMY  = 'hibi_tag';

	/**
	 * Revisions kept per entry.
	 */
	const REVISIONS_TO_KEEP = 50;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'force_private' ), 10, 2 );
		add_filter( 'wp_' . self::POST_TYPE . '_revisions_to_keep', array( __CLASS__, 'revisions_to_keep' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'limit_admin_list_to_own' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Journal Entries', 'hibi-journal' ),
					'singular_name' => __( 'Journal Entry', 'hibi-journal' ),
					'menu_name'     => __( 'Hibi Journal', 'hibi-journal' ),
					'all_items'     => __( 'All Entries', 'hibi-journal' ),
					'edit_item'     => __( 'Edit Entry', 'hibi-journal' ),
					'search_items'  => __( 'Search Entries', 'hibi-journal' ),
					'not_found'     => __( 'No entries found.', 'hibi-journal' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-book-alt',
				'supports'            => array( 'editor', 'author', 'revisions' ),
				// Entries are created by the app only, never from the "Add New" screen.
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Journal Tags', 'hibi-journal' ),
					'singular_name' => __( 'Journal Tag', 'hibi-journal' ),
				),
				'public'            => false,
				'publicly_queryable' => false,
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_in_nav_menus' => false,
				'show_in_rest'      => false,
				'show_tagcloud'     => false,
				'show_admin_column' => true,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/**
	 * Keep every entry private, whatever screen or API saves it. If the
	 * access gate is ever switched off, entries still stay hidden.
	 *
	 * @param array $data    Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public static function force_private( $data, $postarr ) {
		unset( $postarr );
		if ( self::POST_TYPE === $data['post_type'] && ! in_array( $data['post_status'], array( 'private', 'trash', 'auto-draft', 'inherit' ), true ) ) {
			$data['post_status'] = 'private';
		}
		return $data;
	}

	public static function revisions_to_keep() {
		return self::REVISIONS_TO_KEEP;
	}

	/**
	 * In wp-admin, an editor sees only their own entries, never another
	 * user's journal.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function limit_admin_list_to_own( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$query->set( 'author', get_current_user_id() );
	}
}
