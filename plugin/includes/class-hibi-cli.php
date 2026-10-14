<?php
/**
 * WP-CLI commands: wp hibi <command>.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_CLI {

	const SEED_LOGIN = 'hibi-seed';

	/**
	 * Fills a dedicated test user's journal with generated entries, or removes them.
	 *
	 * The entries belong to the user "hibi-seed", so no real journal is ever
	 * touched, and --clean removes that user together with every entry.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Number of days to fill, counting back from today.
	 * ---
	 * default: 3000
	 * ---
	 *
	 * [--skip=<percent>]
	 * : Percentage of days left empty, so streaks vary.
	 * ---
	 * default: 15
	 * ---
	 *
	 * [--clean]
	 * : Delete the test user and all of its entries instead.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hibi seed --days=3000
	 *     wp hibi seed --clean
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function seed( $args, $assoc_args ) {
		unset( $args );

		if ( ! empty( $assoc_args['clean'] ) ) {
			$this->clean();
			return;
		}

		$days = max( 1, (int) $assoc_args['days'] );
		$skip = min( 90, max( 0, (int) $assoc_args['skip'] ) );

		$user_id = $this->seed_user();
		$today   = Hibi_Day::today( $user_id );

		wp_defer_term_counting( true );
		wp_suspend_cache_addition( true );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating entries', $days );
		$created  = 0;
		$started  = microtime( true );

		for ( $i = 0; $i < $days; $i++ ) {
			$progress->tick();
			$date = Hibi_Day::shift( $today, -$i );
			if ( wp_rand( 1, 100 ) <= $skip ) {
				continue;
			}
			$existing = Hibi_Entries::get( $user_id, $date );
			$result   = Hibi_Entries::save(
				$user_id,
				$date,
				array(
					'base_version' => $existing['version'],
					'content'      => $this->fake_text(),
					'mood'         => wp_rand( 0, 5 ) ? wp_rand( 1, 5 ) : null,
					'tags'         => $this->fake_tags(),
				)
			);
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( $date . ': ' . $result->get_error_message() );
				continue;
			}
			update_post_meta( $this->entry_id( $user_id, $date ), '_hibi_seed', 1 );
			++$created;
		}

		$progress->finish();
		wp_suspend_cache_addition( false );
		wp_defer_term_counting( false );

		WP_CLI::success( sprintf( '%d entries for user %s (ID %d) in %.1fs.', $created, self::SEED_LOGIN, $user_id, microtime( true ) - $started ) );
	}

	/**
	 * Sends today's reminder to everyone whose reminder time has passed and
	 * who has not written yet. Run it from the server's cron every five
	 * minutes; the README shows the crontab line.
	 *
	 * @subcommand send-reminders
	 */
	public function send_reminders() {
		$results = Hibi_Push::send_due_reminders();
		foreach ( $results as $user_id => $result ) {
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'User %d: %s', $user_id, $result->get_error_message() ) );
				continue;
			}
			WP_CLI::log( sprintf( 'User %d: sent %d, failed %d, removed %d.', $user_id, $result['sent'], $result['failed'], $result['removed'] ) );
		}
		WP_CLI::success( sprintf( '%d reminder(s) due.', count( $results ) ) );
	}

	/**
	 * Removes the seed user and all of its entries.
	 */
	private function clean() {
		$user = get_user_by( 'login', self::SEED_LOGIN );
		if ( ! $user || ! get_user_meta( $user->ID, '_hibi_seed', true ) ) {
			WP_CLI::success( 'Nothing to clean.' );
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => Hibi_Entry_Type::POST_TYPE,
				'post_status'    => 'any',
				'author'         => $user->ID,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting entries', count( $ids ) );
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
			$progress->tick();
		}
		$progress->finish();

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user->ID );

		WP_CLI::success( sprintf( 'Deleted %d entries and the user %s.', count( $ids ), self::SEED_LOGIN ) );
	}

	/**
	 * The seed user, created on first use with an unusable random password.
	 *
	 * @return int
	 */
	private function seed_user() {
		$user = get_user_by( 'login', self::SEED_LOGIN );
		if ( $user ) {
			if ( ! get_user_meta( $user->ID, '_hibi_seed', true ) ) {
				WP_CLI::error( sprintf( 'The user %s exists but was not created by this command. Refusing to touch it.', self::SEED_LOGIN ) );
			}
			return $user->ID;
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => self::SEED_LOGIN,
				'user_pass'  => wp_generate_password( 64, true, true ),
				'user_email' => self::SEED_LOGIN . '@example.invalid',
				'role'       => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			WP_CLI::error( $user_id->get_error_message() );
		}
		update_user_meta( $user_id, '_hibi_seed', 1 );
		return $user_id;
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $date    Date.
	 * @return int
	 */
	private function entry_id( $user_id, $date ) {
		$post = Hibi_Entries::find( $user_id, $date );
		return $post ? $post->ID : 0;
	}

	/**
	 * Japanese-looking text of varied length, with the Markdown and
	 * time-stamp patterns the app produces.
	 *
	 * @return string
	 */
	private function fake_text() {
		$sentences = array(
			'朝は少し寒くて、コーヒーを二杯飲んだ。',
			'午前中は集中して作業が進んだ。',
			'昼ごはんは近所のラーメン屋に行った。',
			'子供の水泳の送り迎えをした。',
			'夕方に散歩をしたら、紅葉がきれいだった。',
			'新しいプラグインの設計について考えた。',
			'メールの返信に思ったより時間がかかった。',
			'夜は家族で映画を見た。',
			'なかなか寝つけなかった。',
			'週末の予定を立てた。',
			'図書館で本を三冊借りた。',
			'雨が降っていたので一日家にいた。',
			'久しぶりに友人と電話で話した。',
			'買い物に行って、冬物のコートを見た。',
			'仕事で小さなミスをして反省した。',
			'思っていたよりも早く課題が片付いた。',
			'晩ごはんはカレーにした。',
			'英語の勉強を三十分だけ続けた。',
			'TrueNAS のバックアップ設定を見直した。',
			'明日はもう少し早く起きたい。',
		);

		$paragraphs = wp_rand( 1, 6 );
		$out        = array();
		for ( $p = 0; $p < $paragraphs; $p++ ) {
			$line = sprintf( '%02d:%02d ', wp_rand( 6, 23 ), wp_rand( 0, 59 ) );
			$n    = wp_rand( 1, 8 );
			for ( $s = 0; $s < $n; $s++ ) {
				$line .= $sentences[ wp_rand( 0, count( $sentences ) - 1 ) ];
			}
			$out[] = $line;
		}
		if ( 0 === wp_rand( 0, 9 ) ) {
			$out[] = "## やること\n- [ ] 洗濯\n- [x] 買い物";
		}
		return implode( "\n\n", $out );
	}

	/**
	 * @return string[]
	 */
	private function fake_tags() {
		$pool = array( '子供', '仕事', '家族', '散歩', '読書', '料理', '開発', '英語' );
		shuffle( $pool );
		return array_slice( $pool, 0, wp_rand( 0, 3 ) );
	}
}
