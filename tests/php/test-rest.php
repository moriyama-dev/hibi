<?php
/**
 * @package HibiJournal
 */

class Test_Hibi_Rest extends WP_Test_REST_TestCase {

	/**
	 * @var WP_REST_Server
	 */
	private $server;

	public function set_up() {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	private function request( $method, $route, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/hibi/v1' . $route );
		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return $this->server->dispatch( $request );
	}

	public function routes() {
		return array(
			array( 'GET', '/bootstrap' ),
			array( 'GET', '/entries/2026-10-14' ),
			array( 'PUT', '/entries/2026-10-14' ),
			array( 'GET', '/settings' ),
			array( 'PUT', '/settings' ),
			array( 'GET', '/stats' ),
		);
	}

	/**
	 * @dataProvider routes
	 *
	 * @param string $method Method.
	 * @param string $route  Route.
	 */
	public function test_logged_out_gets_401( $method, $route ) {
		wp_set_current_user( 0 );
		$response = $this->request( $method, $route, array( 'base_version' => 0 ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_put_get_and_conflict() {
		wp_set_current_user( self::factory()->user->create() );
		$today = Hibi_Day::today( get_current_user_id() );

		$created = $this->request(
			'PUT',
			'/entries/' . $today,
			array(
				'base_version' => 0,
				'content'      => '今日',
				'mood'         => 5,
			)
		);
		$this->assertSame( 200, $created->get_status() );
		$this->assertSame( 1, $created->get_data()['version'] );

		$read = $this->request( 'GET', '/entries/' . $today );
		$this->assertSame( '今日', $read->get_data()['content'] );
		$this->assertStringContainsString( 'no-store', $read->get_headers()['Cache-Control'] );

		$stale = $this->request(
			'PUT',
			'/entries/' . $today,
			array(
				'base_version' => 0,
				'content'      => 'other device',
			)
		);
		$this->assertErrorResponse( 'hibi_conflict', $stale, 409 );
		$this->assertSame( '今日', $stale->get_data()['data']['entry']['content'] );
	}

	public function test_other_users_entry_is_invisible() {
		$owner = self::factory()->user->create();
		Hibi_Entries::save(
			$owner,
			'2026-10-10',
			array(
				'base_version' => 0,
				'content'      => 'secret',
			)
		);

		wp_set_current_user( self::factory()->user->create() );
		$response = $this->request( 'GET', '/entries/2026-10-10' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['exists'] );
		$this->assertSame( '', $response->get_data()['content'] );

		$this->request(
			'PUT',
			'/entries/2026-10-10',
			array(
				'base_version' => 0,
				'content'      => 'mine',
			)
		);
		$this->assertSame( 'secret', Hibi_Entries::get( $owner, '2026-10-10' )['content'] );
	}

	public function test_invalid_input() {
		wp_set_current_user( self::factory()->user->create() );

		$this->assertSame( 400, $this->request( 'GET', '/entries/2026-02-30' )->get_status() );
		$this->assertSame( 400, $this->request( 'PUT', '/entries/2026-10-01', array( 'content' => 'no version' ) )->get_status() );
		$this->assertSame(
			400,
			$this->request(
				'PUT',
				'/entries/2026-10-01',
				array(
					'base_version' => 0,
					'mood'         => 9,
				)
			)->get_status()
		);
		$this->assertErrorResponse(
			'hibi_future_date',
			$this->request(
				'PUT',
				'/entries/2099-01-01',
				array( 'base_version' => 0 )
			),
			400
		);
	}

	public function test_bootstrap() {
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Yoshi' ) ) );
		$data = $this->request( 'GET', '/bootstrap' )->get_data();

		$this->assertSame( 'Yoshi', $data['user']['display_name'] );
		$this->assertSame( 'America/Toronto', $data['timezone'] );
		$this->assertSame( Hibi_Day::today( get_current_user_id() ), $data['today'] );
		$this->assertFalse( $data['entry']['exists'] );
		$this->assertArrayHasKey( 'current_streak', $data['stats'] );
		$this->assertNotEmpty( $data['nonce'] );
	}

	public function test_settings_roundtrip() {
		wp_set_current_user( self::factory()->user->create() );
		$saved = $this->request( 'PUT', '/settings', array( 'font_size' => 'S' ) )->get_data();
		$this->assertSame( 'S', $saved['font_size'] );
		$this->assertSame( 'S', $this->request( 'GET', '/settings' )->get_data()['font_size'] );
	}
}
