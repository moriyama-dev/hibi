<?php
/**
 * @package HibiJournal
 */

class Test_Hibi_Stats_Settings extends WP_UnitTestCase {

	private function write( $user_id, $date, $content = 'x' ) {
		$entry = Hibi_Entries::get( $user_id, $date );
		Hibi_Entries::save(
			$user_id,
			$date,
			array(
				'base_version' => $entry['version'],
				'content'      => $content,
			)
		);
	}

	public function test_streaks() {
		$user_id = self::factory()->user->create();
		foreach ( array( '2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-10', '2026-10-12', '2026-10-13' ) as $date ) {
			$this->write( $user_id, $date );
		}
		$this->write( $user_id, '2026-10-11', '   ' );

		$stats = Hibi_Stats::get( $user_id, '2026-10-14' );
		$this->assertSame( 2, $stats['current_streak'], 'Today is still open, so the streak counts from yesterday.' );
		$this->assertSame( 4, $stats['longest_streak'], 'The run crosses the month boundary.' );
		$this->assertSame( 7, $stats['total_entries'], 'A whitespace-only entry does not count.' );
		$this->assertSame( 4, $stats['month_entries'] );
		$this->assertFalse( $stats['written_today'] );

		$this->write( $user_id, '2026-10-14' );
		$this->assertSame( 3, Hibi_Stats::get( $user_id, '2026-10-14' )['current_streak'] );

		$this->assertSame( 0, Hibi_Stats::get( $user_id, '2026-10-20' )['current_streak'] );
	}

	public function test_stats_ignore_other_users() {
		$a = self::factory()->user->create();
		$b = self::factory()->user->create();
		$this->write( $a, '2026-10-13', 'abc' );

		$this->assertSame( 0, Hibi_Stats::get( $b, '2026-10-14' )['total_entries'] );
		$this->assertSame( 3, Hibi_Stats::get( $a, '2026-10-14' )['total_chars'] );
	}

	public function test_settings_defaults_and_sanitizing() {
		$user_id = self::factory()->user->create();
		$this->assertSame( Hibi_Settings::defaults(), Hibi_Settings::get( $user_id ) );

		$saved = Hibi_Settings::update(
			$user_id,
			array(
				'day_start_hour'   => 9,
				'reminder_time'    => '25:00',
				'reminder_enabled' => 'true',
				'font_size'        => 'L',
				'color_theme'      => 'purple',
				'unknown'          => 'x',
			)
		);

		$this->assertSame( 4, $saved['day_start_hour'] );
		$this->assertSame( '21:00', $saved['reminder_time'] );
		$this->assertTrue( $saved['reminder_enabled'] );
		$this->assertSame( 'L', $saved['font_size'] );
		$this->assertSame( 'auto', $saved['color_theme'] );
		$this->assertArrayNotHasKey( 'unknown', $saved );
		$this->assertSame( $saved, Hibi_Settings::get( $user_id ) );
	}
}
