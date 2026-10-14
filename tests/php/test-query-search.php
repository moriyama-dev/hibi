<?php
/**
 * @package HibiJournal
 */

class Test_Hibi_Query_Search extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create();
	}

	private function write( $date, $content, $mood = null, array $tags = array(), $user_id = null ) {
		$user_id = $user_id ? $user_id : $this->user_id;
		$entry   = Hibi_Entries::get( $user_id, $date );
		Hibi_Entries::save(
			$user_id,
			$date,
			array(
				'base_version' => $entry['version'],
				'content'      => $content,
				'mood'         => $mood,
				'tags'         => $tags,
			)
		);
	}

	public function test_levels() {
		$this->assertSame( 0, Hibi_Query::level( 0 ) );
		$this->assertSame( 1, Hibi_Query::level( 5 ) );
		$this->assertSame( 2, Hibi_Query::level( 200 ) );
		$this->assertSame( 3, Hibi_Query::level( 5000 ) );
	}

	public function test_month() {
		$this->write( '2026-10-01', str_repeat( 'あ', 250 ), 4 );
		$this->write( '2026-10-31', 'short' );
		$this->write( '2026-11-01', 'next month' );
		$this->write( '2026-10-15', '', 2 ); // Mood only still counts as written.

		$month = Hibi_Query::month( $this->user_id, 2026, 10 );
		$days  = (array) $month['days'];
		$this->assertEqualSets( array( '2026-10-01', '2026-10-15', '2026-10-31' ), array_keys( $days ) );
		$this->assertSame( 2, $days['2026-10-01']['level'] );
		$this->assertSame( 4, $days['2026-10-01']['mood'] );
		$this->assertSame( 0, $days['2026-10-15']['level'] );
		$this->assertNull( $days['2026-10-31']['mood'] );
	}

	public function test_timeline_pages_and_filters() {
		for ( $d = 1; $d <= 5; $d++ ) {
			$this->write( sprintf( '2026-10-%02d', $d ), "## 見出し\n本文 {$d}", 0 === $d % 2 ? 5 : 3, 0 === $d % 2 ? array( '子供' ) : array( '仕事' ) );
		}

		$page = Hibi_Query::timeline( $this->user_id, array( 'limit' => 2 ) );
		$this->assertSame( array( '2026-10-05', '2026-10-04' ), wp_list_pluck( $page['items'], 'date' ) );
		$this->assertSame( '2026-10-04', $page['next_cursor'] );
		$this->assertSame( "見出し\n本文 5", $page['items'][0]['excerpt'] );

		$page = Hibi_Query::timeline(
			$this->user_id,
			array(
				'limit'  => 2,
				'cursor' => $page['next_cursor'],
			)
		);
		$this->assertSame( array( '2026-10-03', '2026-10-02' ), wp_list_pluck( $page['items'], 'date' ) );

		$tagged = Hibi_Query::timeline( $this->user_id, array( 'tag' => '子供' ) );
		$this->assertSame( array( '2026-10-04', '2026-10-02' ), wp_list_pluck( $tagged['items'], 'date' ) );
		$this->assertSame( array( '子供' ), $tagged['items'][0]['tags'] );
		$this->assertNull( $tagged['next_cursor'] );

		$moody = Hibi_Query::timeline( $this->user_id, array( 'mood' => 3 ) );
		$this->assertCount( 3, $moody['items'] );
	}

	public function test_on_this_day() {
		$this->write( '2024-10-14', 'two years ago' );
		$this->write( '2025-10-14', 'last year' );
		$this->write( '2025-10-15', 'not this day' );
		$this->write( '2026-10-14', 'today' );

		$result = Hibi_Query::on_this_day( $this->user_id, '2026-10-14' );
		$this->assertSame( array( '2025-10-14', '2024-10-14' ), wp_list_pluck( $result['items'], 'date' ) );
	}

	public function test_queries_ignore_other_users() {
		$other = self::factory()->user->create();
		$this->write( '2026-10-01', 'secret 秘密', 5, array( '秘密' ), $other );

		$this->assertCount( 0, (array) Hibi_Query::month( $this->user_id, 2026, 10 )['days'] );
		$this->assertCount( 0, Hibi_Query::timeline( $this->user_id, array() )['items'] );
		$this->assertCount( 0, Hibi_Search::search( $this->user_id, '秘密' )['items'] );
		$this->assertSame( array(), Hibi_Query::tags( $this->user_id ) );
	}

	public function test_search_requires_every_term() {
		$this->write( '2026-10-01', '今日は公園で散歩した。' );
		$this->write( '2026-10-02', '公園には行かなかった。' );
		$this->write( '2026-10-03', '散歩だけした。' );

		$result = Hibi_Search::search( $this->user_id, "公園\u{3000}散歩" );
		$this->assertSame( array( '2026-10-01' ), wp_list_pluck( $result['items'], 'date' ) );
		$this->assertSame( array( '公園', '散歩' ), $result['terms'] );
	}

	public function test_search_highlights_matches() {
		$this->write( '2026-10-01', '朝はコーヒー。夜もコーヒー。' );
		$segments = Hibi_Search::search( $this->user_id, 'コーヒー' )['items'][0]['segments'];

		$this->assertSame(
			array(
				array( '朝は', false ),
				array( 'コーヒー', true ),
				array( '。夜も', false ),
				array( 'コーヒー', true ),
				array( '。', false ),
			),
			$segments
		);
	}

	public function test_search_kana_and_case() {
		$this->write( '2026-10-01', 'カフェでTrueNASを触った' );

		$this->assertCount( 1, Hibi_Search::search( $this->user_id, 'truenas' )['items'] );
		$this->assertCount( 1, Hibi_Search::search( $this->user_id, 'かふぇ' )['items'], 'Hiragana finds katakana.' );

		$segments = Hibi_Search::search( $this->user_id, 'かふぇ' )['items'][0]['segments'];
		$this->assertSame( array( 'カフェ', true ), $segments[0], 'The katakana text is highlighted.' );
	}

	public function test_search_tells_voiced_kana_apart() {
		global $wpdb;
		if ( ! $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_0900_as_ci'" ) ) {
			$this->markTestSkipped( 'Needs MySQL 8 collations.' );
		}
		$this->write( '2026-10-01', 'はしを渡った' );
		$this->assertCount( 0, Hibi_Search::search( $this->user_id, 'ばし' )['items'] );
		$this->assertCount( 1, Hibi_Search::search( $this->user_id, 'はし' )['items'] );
	}

	public function test_search_escapes_like_wildcards() {
		$this->write( '2026-10-01', '100% 達成' );
		$this->write( '2026-10-02', '100 達成' );
		$this->assertCount( 1, Hibi_Search::search( $this->user_id, '100%' )['items'] );
		$this->assertCount( 0, Hibi_Search::search( $this->user_id, '_' )['items'] );
	}

	public function test_search_paging() {
		for ( $d = 1; $d <= 25; $d++ ) {
			$this->write( sprintf( '2026-09-%02d', $d ), '同じ言葉' );
		}
		$first = Hibi_Search::search( $this->user_id, '言葉' );
		$this->assertCount( Hibi_Search::PAGE_SIZE, $first['items'] );
		$second = Hibi_Search::search( $this->user_id, '言葉', $first['next_cursor'] );
		$this->assertCount( 5, $second['items'] );
		$this->assertNull( $second['next_cursor'] );
	}

	public function test_tags_are_counted_per_author() {
		$this->write( '2026-10-01', 'a', null, array( '子供', '仕事' ) );
		$this->write( '2026-10-02', 'b', null, array( '子供' ) );
		$this->assertSame(
			array(
				array(
					'name'  => '子供',
					'count' => 2,
				),
				array(
					'name'  => '仕事',
					'count' => 1,
				),
			),
			Hibi_Query::tags( $this->user_id )
		);
	}

	public function test_plain_excerpt() {
		$this->assertSame(
			"見出し\nやること\n太字ではない",
			Hibi_Query::plain_excerpt( "# 見出し\n\n- [ ] やること\n![](hibi-photo:3)\n**太字**ではない" )
		);
	}
}
