<?php
/**
 * @package HibiJournal
 */

class Test_Hibi_Push_Weather extends WP_UnitTestCase {

	/**
	 * A local stand-in for a push service: /ok answers 201, /gone 410.
	 *
	 * @var resource|null
	 */
	private static $server;

	/**
	 * @var string
	 */
	private static $log;

	const PORT = 8765;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$log = sys_get_temp_dir() . '/hibi-push-' . getmypid() . '.log';
		$router    = sys_get_temp_dir() . '/hibi-push-router-' . getmypid() . '.php';
		file_put_contents(
			$router,
			'<?php file_put_contents( ' . var_export( self::$log, true ) . ', json_encode( array( "path" => $_SERVER["REQUEST_URI"], "encoding" => $_SERVER["HTTP_CONTENT_ENCODING"] ?? "", "auth" => substr( $_SERVER["HTTP_AUTHORIZATION"] ?? "", 0, 6 ), "bytes" => strlen( file_get_contents( "php://input" ) ) ) ) . "\n", FILE_APPEND ); http_response_code( "/gone" === $_SERVER["REQUEST_URI"] ? 410 : 201 );'
		);
		self::$server = proc_open( array( PHP_BINARY, '-S', '127.0.0.1:' . self::PORT, $router ), array( array( 'pipe', 'r' ), array( 'file', '/dev/null', 'w' ), array( 'file', '/dev/null', 'w' ) ), $pipes );
		usleep( 300000 );
	}

	public static function tear_down_after_class() {
		if ( self::$server ) {
			proc_terminate( self::$server );
		}
		@unlink( self::$log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		parent::tear_down_after_class();
	}

	public function set_up() {
		parent::set_up();
		@unlink( self::$log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * A browser-like subscription: a P-256 public key and a 16-byte secret.
	 *
	 * @param string $path Endpoint path on the local server.
	 * @return array
	 */
	private function device( $path ) {
		$key     = openssl_pkey_new(
			array(
				'curve_name'       => 'prime256v1',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
			)
		);
		$details = openssl_pkey_get_details( $key );
		$point   = "\x04" . str_pad( $details['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $details['ec']['y'], 32, "\0", STR_PAD_LEFT );
		$b64     = static function ( $bytes ) {
			return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
		};
		return array(
			'endpoint' => 'http://127.0.0.1:' . self::PORT . $path,
			'p256dh'   => $b64( $point ),
			'auth'     => $b64( random_bytes( 16 ) ),
			'created'  => time(),
		);
	}

	private function requests() {
		return file_exists( self::$log ) ? array_map( 'json_decode', file( self::$log, FILE_IGNORE_NEW_LINES ) ) : array();
	}

	public function test_keys_are_created_once_and_not_autoloaded() {
		delete_option( Hibi_Push::VAPID_OPTION );
		$first = Hibi_Push::keys();
		$this->assertNotEmpty( $first['publicKey'] );
		$this->assertSame( $first, Hibi_Push::keys() );

		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Hibi_Push::VAPID_OPTION ) );
		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}

	public function test_subscribe_validates_and_replaces_by_endpoint() {
		$user_id = self::factory()->user->create();

		$this->assertWPError( Hibi_Push::subscribe( $user_id, array( 'endpoint' => 'http://insecure.example/push' ) ) );
		$this->assertWPError(
			Hibi_Push::subscribe(
				$user_id,
				array(
					'endpoint' => 'https://push.example/1',
					'keys'     => array(
						'p256dh' => '<bad>',
						'auth'   => 'x',
					),
				)
			)
		);

		$good = array(
			'endpoint' => 'https://push.example/1',
			'keys'     => array(
				'p256dh' => 'BKey',
				'auth'   => 'secret',
			),
		);
		$this->assertTrue( Hibi_Push::subscribe( $user_id, $good ) );
		$this->assertTrue( Hibi_Push::subscribe( $user_id, $good ) );
		$this->assertCount( 1, Hibi_Push::subscriptions( $user_id ) );

		Hibi_Push::unsubscribe( $user_id, 'https://push.example/1' );
		$this->assertCount( 0, Hibi_Push::subscriptions( $user_id ) );
	}

	public function test_send_encrypts_and_drops_gone_devices() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, Hibi_Push::SUBS_META, array( $this->device( '/ok' ), $this->device( '/gone' ) ) );

		$result = Hibi_Push::send( $user_id, 'Hibi', 'Test' );

		$this->assertSame(
			array(
				'sent'    => 1,
				'failed'  => 1,
				'removed' => 1,
			),
			$result
		);
		$requests = $this->requests();
		$this->assertCount( 2, $requests );
		foreach ( $requests as $request ) {
			$this->assertSame( 'aes128gcm', $request->encoding );
			$this->assertSame( 'vapid ', $request->auth );
			$this->assertGreaterThan( 86, $request->bytes, 'The body is an encrypted record, not the plain payload.' );
		}
		$left = Hibi_Push::subscriptions( $user_id );
		$this->assertCount( 1, $left );
		$this->assertStringEndsWith( '/ok', $left[0]['endpoint'] );
	}

	public function test_reminder_is_due_once_after_the_time_and_only_if_empty() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, Hibi_Push::SUBS_META, array( $this->device( '/ok' ) ) );
		Hibi_Settings::update(
			$user_id,
			array(
				'reminder_enabled' => true,
				'reminder_time'    => '21:00',
			)
		);
		$tz = new DateTimeZone( 'America/Toronto' );

		$this->assertNotContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-14 20:59', $tz ) ) );
		$this->assertContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-14 21:00', $tz ) ) );
		// Before the day boundary the reminder still belongs to the 14th.
		$this->assertContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-15 02:00', $tz ) ) );

		$results = Hibi_Push::send_due_reminders( new DateTimeImmutable( '2026-10-14 21:05', $tz ) );
		$this->assertSame( 1, $results[ $user_id ]['sent'] );
		$this->assertCount( 1, $this->requests() );

		// Once a day.
		$this->assertSame( array(), Hibi_Push::send_due_reminders( new DateTimeImmutable( '2026-10-14 21:10', $tz ) ) );
		$this->assertCount( 1, $this->requests() );

		// Not when the day is already written.
		Hibi_Entries::save(
			$user_id,
			'2026-10-15',
			array(
				'base_version' => 0,
				'content'      => '書いた',
			)
		);
		$this->assertNotContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-15 21:30', $tz ) ) );
		$this->assertContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-16 21:30', $tz ) ) );
	}

	public function test_reminder_time_after_midnight_belongs_to_the_evening_before() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, Hibi_Push::SUBS_META, array( $this->device( '/ok' ) ) );
		Hibi_Settings::update(
			$user_id,
			array(
				'reminder_enabled' => true,
				'reminder_time'    => '01:00',
			)
		);
		$tz = new DateTimeZone( 'America/Toronto' );
		$this->assertNotContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-14 23:00', $tz ) ) );
		$this->assertContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-15 01:30', $tz ) ) );
	}

	public function test_disabled_reminder_is_never_due() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, Hibi_Push::SUBS_META, array( $this->device( '/ok' ) ) );
		$this->assertNotContains( $user_id, Hibi_Push::due_users( new DateTimeImmutable( '2026-10-14 23:00', new DateTimeZone( 'America/Toronto' ) ) ) );
	}

	public function test_cron_is_scheduled_every_five_minutes() {
		Hibi_Push::ensure_cron();
		$this->assertNotFalse( wp_next_scheduled( Hibi_Push::CRON_HOOK ) );
		$this->assertSame( Hibi_Push::CRON_SCHEDULE, wp_get_schedule( Hibi_Push::CRON_HOOK ) );
		$this->assertSame( 300, wp_get_schedules()[ Hibi_Push::CRON_SCHEDULE ]['interval'] );
	}

	public function test_weather_from_open_meteo() {
		$asked = array();
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$asked ) {
				$asked[] = $url;
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'daily' => array(
								'weather_code'       => array( 61 ),
								'temperature_2m_max' => array( 14.26 ),
								'temperature_2m_min' => array( 7.04 ),
							),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$weather = Hibi_Weather::get( 43.6532, -79.3832, '2026-10-14' );
		$this->assertSame( 61, $weather['weather_code'] );
		$this->assertSame( 14.3, $weather['temp_max'] );
		$this->assertSame( 'Rain', $weather['label'] );
		$this->assertStringContainsString( 'api.open-meteo.com', $asked[0] );
		$this->assertStringContainsString( 'latitude=43.65', $asked[0] );

		Hibi_Weather::get( 43.6532, -79.3832, '2026-10-14' );
		$this->assertCount( 1, $asked, 'The second call comes from the cache.' );

		Hibi_Weather::get( 43.6532, -79.3832, '2020-10-14' );
		$this->assertStringContainsString( 'archive-api.open-meteo.com', $asked[1], 'Old dates use the archive.' );
	}

	public function test_weather_failure_is_an_error_not_a_crash() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'timeout' );
			}
		);
		$result = Hibi_Weather::get( 10, 10, '2026-10-01' );
		$this->assertWPError( $result );
		$this->assertSame( 502, $result->get_error_data()['status'] );
	}

	public function test_weather_place_settings_are_rounded_and_validated() {
		$user_id = self::factory()->user->create();
		$saved   = Hibi_Settings::update(
			$user_id,
			array(
				'weather_lat'   => 43.65321,
				'weather_lng'   => -79.38318,
				'weather_place' => '<b>トロント</b>',
			)
		);
		$this->assertSame( 43.65, $saved['weather_lat'] );
		$this->assertSame( 'トロント', $saved['weather_place'] );

		$bad = Hibi_Settings::update( $user_id, array( 'weather_lat' => 123 ) );
		$this->assertNull( $bad['weather_lat'] );
		$this->assertNull( $bad['weather_lng'] );
	}
}
