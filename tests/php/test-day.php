<?php
/**
 * @package HibiJournal
 */

class Test_Hibi_Day extends WP_UnitTestCase {

	/**
	 * @dataProvider boundaries
	 *
	 * @param string $local    Local wall-clock time in America/Toronto.
	 * @param int    $start    Day start hour.
	 * @param string $expected Journal date.
	 */
	public function test_date_for( $local, $start, $expected ) {
		$moment = new DateTimeImmutable( $local, new DateTimeZone( 'America/Toronto' ) );
		$this->assertSame( $expected, Hibi_Day::date_for( $moment, $start ) );
	}

	public function boundaries() {
		return array(
			'just before 4:00'          => array( '2026-10-14 03:59:59', 4, '2026-10-13' ),
			'exactly 4:00'              => array( '2026-10-14 04:00:00', 4, '2026-10-14' ),
			'midnight'                  => array( '2026-10-14 00:00:00', 4, '2026-10-13' ),
			'new year past midnight'    => array( '2026-01-01 00:10:00', 4, '2025-12-31' ),
			'day start 0'               => array( '2026-10-14 00:00:00', 0, '2026-10-14' ),
			'DST ends, repeated 1:30'   => array( '2026-11-01 01:30:00', 4, '2026-10-31' ),
			'DST ends, 3:59'            => array( '2026-11-01 03:59:00', 4, '2026-10-31' ),
			'DST ends, 4:00'            => array( '2026-11-01 04:00:00', 4, '2026-11-01' ),
			'DST starts, 3:30'          => array( '2026-03-08 03:30:00', 4, '2026-03-07' ),
			'DST starts, 4:00'          => array( '2026-03-08 04:00:00', 4, '2026-03-08' ),
			'leap day late night'       => array( '2028-03-01 02:00:00', 4, '2028-02-29' ),
		);
	}

	public function test_moment_in_utc_is_converted_to_site_time() {
		// 07:59 UTC on 14 Oct is 03:59 EDT.
		$moment = new DateTimeImmutable( '2026-10-14 07:59:00', new DateTimeZone( 'UTC' ) );
		$this->assertSame( '2026-10-13', Hibi_Day::date_for( $moment, 4 ) );
	}

	public function test_is_valid() {
		$this->assertTrue( Hibi_Day::is_valid( '2028-02-29' ) );
		$this->assertFalse( Hibi_Day::is_valid( '2026-02-29' ) );
		$this->assertFalse( Hibi_Day::is_valid( '2026-13-01' ) );
		$this->assertFalse( Hibi_Day::is_valid( '2026-1-01' ) );
		$this->assertFalse( Hibi_Day::is_valid( '20261014' ) );
		$this->assertFalse( Hibi_Day::is_valid( array() ) );
	}

	public function test_noon_across_dst() {
		$this->assertSame( array( '2026-11-01 12:00:00', '2026-11-01 17:00:00' ), Hibi_Day::noon( '2026-11-01' ) );
		$this->assertSame( array( '2026-10-31 12:00:00', '2026-10-31 16:00:00' ), Hibi_Day::noon( '2026-10-31' ) );
	}

	public function test_shift() {
		$this->assertSame( '2026-03-01', Hibi_Day::shift( '2026-02-28', 1 ) );
		$this->assertSame( '2025-12-31', Hibi_Day::shift( '2026-01-01', -1 ) );
		$this->assertSame( '2026-11-02', Hibi_Day::shift( '2026-11-01', 1 ) );
	}

	public function test_today_uses_user_day_start() {
		$user_id = self::factory()->user->create();
		$moment  = new DateTimeImmutable( '2026-10-14 05:30:00', new DateTimeZone( 'America/Toronto' ) );

		$this->assertSame( '2026-10-14', Hibi_Day::today( $user_id, $moment ) );

		Hibi_Settings::update( $user_id, array( 'day_start_hour' => 6 ) );
		$this->assertSame( '2026-10-13', Hibi_Day::today( $user_id, $moment ) );
	}
}
