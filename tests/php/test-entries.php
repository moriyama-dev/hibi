<?php
/**
 * @package HibiJournal
 */

class Test_Hibi_Entries extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $this->user_id );
	}

	private function save( $date, array $fields ) {
		return Hibi_Entries::save( $this->user_id, $date, $fields );
	}

	public function test_missing_entry_is_not_an_error() {
		$entry = Hibi_Entries::get( $this->user_id, '2026-10-14' );
		$this->assertFalse( $entry['exists'] );
		$this->assertSame( 0, $entry['version'] );
	}

	public function test_create_and_update_one_entry_per_day() {
		$first = $this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'one',
			)
		);
		$this->assertSame( 1, $first['version'] );

		$second = $this->save(
			'2026-10-14',
			array(
				'base_version' => 1,
				'content'      => 'two',
			)
		);
		$this->assertSame( 2, $second['version'] );
		$this->assertSame( 'two', $second['content'] );

		$ids = get_posts(
			array(
				'post_type'   => Hibi_Entry_Type::POST_TYPE,
				'post_status' => 'any',
				'author'      => $this->user_id,
				'fields'      => 'ids',
			)
		);
		$this->assertCount( 1, $ids );
	}

	public function test_stale_version_conflicts_and_returns_server_entry() {
		$this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'phone',
			)
		);
		$result = $this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'laptop',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'hibi_conflict', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 409, $data['status'] );
		$this->assertSame( 'phone', $data['entry']['content'] );
		$this->assertSame( 'phone', Hibi_Entries::get( $this->user_id, '2026-10-14' )['content'] );
	}

	public function test_entry_is_always_private_and_dated_noon() {
		$this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'x',
			)
		);
		$post = Hibi_Entries::find( $this->user_id, '2026-10-14' );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( '2026-10-14 12:00:00', $post->post_date );

		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'private', get_post_status( $post->ID ) );
	}

	public function test_markdown_is_stored_verbatim() {
		$text = "14:32 a < b && c > d\n<script>alert(1)</script>\n- [ ] todo\n\\backslash \"quoted\"";

		$entry = $this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => str_replace( "\n", "\r\n", $text ),
			)
		);
		$this->assertSame( $text, $entry['content'] );
	}

	public function test_kses_is_restored_after_save() {
		$editor = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $editor );
		kses_init();
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );

		Hibi_Entries::save(
			$editor,
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'x',
			)
		);
		$this->assertNotFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
	}

	public function test_char_count_ignores_whitespace() {
		$this->assertSame( 5, Hibi_Entries::char_count( " 今日は\n\t晴れ　 " ) );
	}

	public function test_mood_tags_weather_location() {
		Hibi_Settings::update( $this->user_id, array( 'location_enabled' => true ) );

		$entry = $this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'x',
				'mood'         => 4,
				'tags'         => array( '#子供', '子供', ' 仕事 ', '' ),
				'weather'      => array(
					'weather_code' => 3,
					'temp_max'     => 14.26,
					'temp_min'     => 7,
					'label'        => '<b>くもり</b>',
				),
				'location'     => array(
					'lat'   => 43.65321,
					'lng'   => -79.38318,
					'label' => 'Toronto',
				),
			)
		);

		$this->assertSame( 4, $entry['mood'] );
		$this->assertEqualSets( array( '子供', '仕事' ), $entry['tags'] );
		$this->assertSame( 14.3, $entry['weather']['temp_max'] );
		$this->assertSame( 'くもり', $entry['weather']['label'] );
		$this->assertSame( 43.6532, $entry['location']['lat'] );

		$cleared = $this->save(
			'2026-10-14',
			array(
				'base_version' => 1,
				'mood'         => null,
				'tags'         => array(),
			)
		);
		$this->assertNull( $cleared['mood'] );
		$this->assertSame( array(), $cleared['tags'] );
		$this->assertSame( 'x', $cleared['content'], 'Fields not sent stay as they were.' );
	}

	public function test_location_is_dropped_when_disabled() {
		$entry = $this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'location'     => array(
					'lat' => 43.6,
					'lng' => -79.3,
				),
			)
		);
		$this->assertNull( $entry['location'] );
	}

	public function test_invalid_mood_is_dropped() {
		$this->assertNull( Hibi_Entries::sanitize_mood( 9 ) );
		$this->assertNull( Hibi_Entries::sanitize_mood( 0 ) );
		$this->assertSame( 3, Hibi_Entries::sanitize_mood( '3' ) );
	}

	private function revision_count( $date ) {
		return count( wp_get_post_revisions( Hibi_Entries::find( $this->user_id, $date )->ID ) );
	}

	public function test_revisions_are_thinned() {
		$version = 0;
		for ( $i = 0; $i < 5; $i++ ) {
			$entry   = $this->save(
				'2026-10-14',
				array(
					'base_version' => $version,
					'content'      => 'draft ' . $i,
				)
			);
			$version = $entry['version'];
		}
		$this->assertSame( 1, $this->revision_count( '2026-10-14' ), 'Rapid autosaves keep one revision.' );

		$this->save(
			'2026-10-14',
			array(
				'base_version' => $version,
				'content'      => 'leaving',
				'final'        => true,
			)
		);
		$this->assertSame( 2, $this->revision_count( '2026-10-14' ), 'Leaving the page keeps a revision.' );
	}

	public function test_revision_after_interval() {
		$this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'a',
			)
		);
		$this->save(
			'2026-10-14',
			array(
				'base_version' => 1,
				'content'      => 'b',
			)
		);
		$this->assertSame( 1, $this->revision_count( '2026-10-14' ) );

		global $wpdb;
		$revision_ids = wp_get_post_revisions( Hibi_Entries::find( $this->user_id, '2026-10-14' )->ID, array( 'fields' => 'ids' ) );
		$old          = gmdate( 'Y-m-d H:i:s', time() - Hibi_Entries::REVISION_INTERVAL - 5 );
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_date'     => $old,
				'post_date_gmt' => $old,
			),
			array( 'ID' => reset( $revision_ids ) )
		);
		clean_post_cache( reset( $revision_ids ) );

		$this->save(
			'2026-10-14',
			array(
				'base_version' => 2,
				'content'      => 'c',
			)
		);
		$this->assertSame( 2, $this->revision_count( '2026-10-14' ) );
	}

	public function test_revisions_to_keep() {
		$post_id = self::factory()->post->create( array( 'post_type' => Hibi_Entry_Type::POST_TYPE ) );
		$this->assertSame( 50, wp_revisions_to_keep( get_post( $post_id ) ) );
	}

	public function test_entries_are_per_author() {
		$other = self::factory()->user->create();
		$this->save(
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'mine',
			)
		);

		$this->assertFalse( Hibi_Entries::get( $other, '2026-10-14' )['exists'] );

		$theirs = Hibi_Entries::save(
			$other,
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'theirs',
			)
		);
		$this->assertSame( 1, $theirs['version'] );
		$this->assertSame( 'mine', Hibi_Entries::get( $this->user_id, '2026-10-14' )['content'] );
	}
}
