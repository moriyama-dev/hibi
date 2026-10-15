<?php
/**
 * Reminder notifications over Web Push.
 *
 * One reminder a day, at the user's reminder time, only if today's entry
 * is still empty. Sending is driven by `wp hibi send-reminders` from the
 * server's cron every five minutes; WP-Cron runs the same job as a backup,
 * because a login-only site gets too few visits for WP-Cron alone.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

// Bundled under the Hibi\Vendor namespace (bin/prefix-vendor.sh), so another
// plugin shipping its own copy of these libraries cannot clash with it.
use Hibi\Vendor\Minishlink\WebPush\Subscription;
use Hibi\Vendor\Minishlink\WebPush\VAPID;
use Hibi\Vendor\Minishlink\WebPush\WebPush;

class Hibi_Push {

	const VAPID_OPTION  = 'hibi_vapid_keys';
	const SUBS_META     = 'hibi_push_subscriptions';
	const SENT_META     = 'hibi_reminder_sent';
	const CRON_HOOK     = 'hibi_send_reminders';
	const CRON_SCHEDULE = 'hibi_five_minutes';
	const MAX_DEVICES   = 10;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Five minutes is the reminder resolution.
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_due_reminders' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		self::keys();
		self::ensure_cron();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes (Hibi Journal)', 'hibi-journal' ),
		);
		return $schedules;
	}

	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Loads the bundled library.
	 *
	 * @return bool
	 */
	public static function library_loaded() {
		if ( ! class_exists( WebPush::class ) && file_exists( HIBI_PLUGIN_DIR . 'vendor-prefixed/autoload.php' ) ) {
			require_once HIBI_PLUGIN_DIR . 'vendor-prefixed/autoload.php';
		}
		return class_exists( WebPush::class );
	}

	/**
	 * Whether this server can send notifications.
	 *
	 * @return true|WP_Error
	 */
	public static function available() {
		if ( ! extension_loaded( 'openssl' ) ) {
			return new WP_Error( 'hibi_push_unavailable', __( 'Notifications need the OpenSSL PHP extension.', 'hibi-journal' ), array( 'status' => 501 ) );
		}
		if ( ! extension_loaded( 'gmp' ) && ! extension_loaded( 'bcmath' ) ) {
			return new WP_Error( 'hibi_push_unavailable', __( 'Notifications need the GMP or BCMath PHP extension.', 'hibi-journal' ), array( 'status' => 501 ) );
		}
		if ( ! self::library_loaded() ) {
			return new WP_Error( 'hibi_push_unavailable', __( 'The notification library is missing.', 'hibi-journal' ), array( 'status' => 501 ) );
		}
		return true;
	}

	/**
	 * VAPID key pair, created on first use. Kept out of autoload: it is
	 * needed only when subscribing or sending.
	 *
	 * @return array{publicKey:string,privateKey:string}|null
	 */
	public static function keys() {
		$keys = get_option( self::VAPID_OPTION );
		if ( is_array( $keys ) && ! empty( $keys['publicKey'] ) && ! empty( $keys['privateKey'] ) ) {
			return $keys;
		}
		if ( true !== self::available() ) {
			return null;
		}
		try {
			$keys = VAPID::createVapidKeys();
		} catch ( Exception $e ) {
			return null;
		}
		update_option( self::VAPID_OPTION, $keys, false );
		return $keys;
	}

	/**
	 * Public key for PushManager.subscribe().
	 *
	 * @return string
	 */
	public static function public_key() {
		$keys = self::keys();
		return $keys ? $keys['publicKey'] : '';
	}

	/**
	 * @param int $user_id User ID.
	 * @return array[]
	 */
	public static function subscriptions( $user_id ) {
		$subs = get_user_meta( $user_id, self::SUBS_META, true );
		return is_array( $subs ) ? $subs : array();
	}

	/**
	 * Adds or refreshes a device subscription.
	 *
	 * @param int   $user_id User ID.
	 * @param array $sub     endpoint, keys.p256dh, keys.auth.
	 * @return true|WP_Error
	 */
	public static function subscribe( $user_id, $sub ) {
		$endpoint = isset( $sub['endpoint'] ) ? esc_url_raw( (string) $sub['endpoint'], array( 'https' ) ) : '';
		$p256dh   = isset( $sub['keys']['p256dh'] ) ? (string) $sub['keys']['p256dh'] : '';
		$auth     = isset( $sub['keys']['auth'] ) ? (string) $sub['keys']['auth'] : '';
		if ( '' === $endpoint || ! preg_match( '/^[A-Za-z0-9_-]+={0,2}$/', $p256dh ) || ! preg_match( '/^[A-Za-z0-9_-]+={0,2}$/', $auth ) ) {
			return new WP_Error( 'hibi_push_invalid', __( 'The notification subscription is invalid.', 'hibi-journal' ), array( 'status' => 400 ) );
		}

		$subs = array_filter(
			self::subscriptions( $user_id ),
			static function ( $s ) use ( $endpoint ) {
				return $s['endpoint'] !== $endpoint;
			}
		);
		$subs[] = array(
			'endpoint' => $endpoint,
			'p256dh'   => $p256dh,
			'auth'     => $auth,
			'created'  => time(),
		);
		update_user_meta( $user_id, self::SUBS_META, array_slice( array_values( $subs ), -self::MAX_DEVICES ) );
		return true;
	}

	/**
	 * @param int    $user_id  User ID.
	 * @param string $endpoint Endpoint.
	 */
	public static function unsubscribe( $user_id, $endpoint ) {
		$subs = array_values(
			array_filter(
				self::subscriptions( $user_id ),
				static function ( $s ) use ( $endpoint ) {
					return $s['endpoint'] !== $endpoint;
				}
			)
		);
		update_user_meta( $user_id, self::SUBS_META, $subs );
	}

	/**
	 * Sends one notification to every device of a user. Subscriptions the
	 * push service reports as gone (404/410) are removed.
	 *
	 * @param int    $user_id User ID.
	 * @param string $title   Title.
	 * @param string $body    Body.
	 * @return array{sent:int,failed:int,removed:int}|WP_Error
	 */
	public static function send( $user_id, $title, $body ) {
		$ready = self::available();
		if ( true !== $ready ) {
			return $ready;
		}
		$keys = self::keys();
		$subs = self::subscriptions( $user_id );
		if ( ! $keys || ! $subs ) {
			return array(
				'sent'    => 0,
				'failed'  => 0,
				'removed' => 0,
			);
		}

		$payload = wp_json_encode(
			array(
				'title' => $title,
				'body'  => $body,
				'url'   => home_url( '/' ),
				'tag'   => 'hibi-reminder',
			)
		);

		$push = new WebPush(
			array(
				'VAPID' => array(
					'subject'    => home_url( '/' ),
					'publicKey'  => $keys['publicKey'],
					'privateKey' => $keys['privateKey'],
				),
			),
			array( 'TTL' => 4 * HOUR_IN_SECONDS ),
			20
		);

		foreach ( $subs as $sub ) {
			$push->queueNotification(
				Subscription::create(
					array(
						'endpoint'        => $sub['endpoint'],
						'publicKey'       => $sub['p256dh'],
						'authToken'       => $sub['auth'],
						'contentEncoding' => 'aes128gcm',
					)
				),
				$payload
			);
		}

		$result = array(
			'sent'    => 0,
			'failed'  => 0,
			'removed' => 0,
		);
		foreach ( $push->flush() as $report ) {
			if ( $report->isSuccess() ) {
				++$result['sent'];
				continue;
			}
			++$result['failed'];
			if ( $report->isSubscriptionExpired() ) {
				self::unsubscribe( $user_id, $report->getEndpoint() );
				++$result['removed'];
			} else {
				self::log( sprintf( 'Push to %s failed: %s', wp_parse_url( $report->getEndpoint(), PHP_URL_HOST ), $report->getReason() ) );
			}
		}
		return $result;
	}

	/**
	 * Users whose reminder is due now.
	 *
	 * @param DateTimeImmutable|null $now Current time; injectable for tests.
	 * @return int[]
	 */
	public static function due_users( $now = null ) {
		$now   = $now ? $now : new DateTimeImmutable( 'now', wp_timezone() );
		$users = get_users(
			array(
				'meta_key'     => self::SUBS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only users with a device.
				'meta_compare' => 'EXISTS',
				'fields'       => 'ID',
				'number'       => 500,
			)
		);

		$due = array();
		foreach ( $users as $user_id ) {
			$user_id  = (int) $user_id;
			$settings = Hibi_Settings::get( $user_id );
			if ( ! $settings['reminder_enabled'] || ! self::subscriptions( $user_id ) ) {
				continue;
			}
			$today = Hibi_Day::today( $user_id, $now );
			if ( get_user_meta( $user_id, self::SENT_META, true ) === $today ) {
				continue;
			}
			// The reminder time belongs to the journal day, so 01:00 means the
			// early hours after that day's evening.
			$at = new DateTimeImmutable( $today . ' ' . $settings['reminder_time'] . ':00', wp_timezone() );
			if ( (int) substr( $settings['reminder_time'], 0, 2 ) < $settings['day_start_hour'] ) {
				$at = $at->modify( '+1 day' );
			}
			if ( $now < $at ) {
				continue;
			}
			$entry = Hibi_Entries::get( $user_id, $today );
			if ( $entry['char_count'] > 0 ) {
				continue;
			}
			$due[] = $user_id;
		}
		return $due;
	}

	/**
	 * Sends every due reminder.
	 *
	 * @param DateTimeImmutable|null $now Current time; injectable for tests.
	 * @return array<int,array> Result per user.
	 */
	public static function send_due_reminders( $now = null ) {
		$now = $now instanceof DateTimeImmutable ? $now : null;

		// WP-Cron and the system cron may fire together; one run is enough.
		if ( get_transient( 'hibi_reminders_running' ) ) {
			return array();
		}
		set_transient( 'hibi_reminders_running', 1, 2 * MINUTE_IN_SECONDS );

		$results = array();
		try {
			foreach ( self::due_users( $now ) as $user_id ) {
				// Mark first: a slow push service must never cause a second reminder.
				update_user_meta( $user_id, self::SENT_META, Hibi_Day::today( $user_id, $now ) );
				$results[ $user_id ] = self::send( $user_id, __( 'Hibi', 'hibi-journal' ), __( 'Would you like to write a little about today?', 'hibi-journal' ) );
			}
		} finally {
			delete_transient( 'hibi_reminders_running' );
		}
		return $results;
	}

	/**
	 * @param string $message Message.
	 */
	private static function log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[hibi-journal] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug log only.
		}
	}
}
