<?php
/**
 * Every text the app shows, translated on the server and handed to the
 * browser with the bootstrap data. The app never contains UI text itself.
 *
 * Placeholders follow sprintf: %s, %d, and %1$s / %2$s for several values.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Strings {

	/**
	 * @return array<string,string> Key => translated text.
	 */
	public static function all() {
		return array(
			// Navigation.
			'nav.main'                 => __( 'Main', 'hibi-journal' ),
			'nav.today'                => __( 'Today', 'hibi-journal' ),
			'nav.review'               => __( 'Look back', 'hibi-journal' ),
			'nav.search'               => __( 'Search', 'hibi-journal' ),
			'nav.settings'             => __( 'Settings', 'hibi-journal' ),
			'review.calendar'          => __( 'Calendar', 'hibi-journal' ),
			'review.timeline'          => __( 'Timeline', 'hibi-journal' ),
			'review.otd'               => __( 'On this day', 'hibi-journal' ),
			'not_found'                => __( 'Page not found', 'hibi-journal' ),
			'back_to_today'            => __( "Back to today's entry", 'hibi-journal' ),

			// Common.
			'common.loading'           => __( 'Loading…', 'hibi-journal' ),
			'common.load_failed'       => __( 'Could not load.', 'hibi-journal' ),
			'common.close'             => __( 'Close', 'hibi-journal' ),
			'common.more'              => __( 'Show more', 'hibi-journal' ),
			'common.searching'         => __( 'Searching…', 'hibi-journal' ),
			'common.no_results'        => __( 'Nothing found.', 'hibi-journal' ),
			'common.search_failed'     => __( 'Search failed.', 'hibi-journal' ),
			'common.sending'           => __( 'Sending…', 'hibi-journal' ),

			// Save status.
			'save.saved'               => __( 'Saved', 'hibi-journal' ),
			'save.dirty'               => __( 'Waiting to save', 'hibi-journal' ),
			'save.saving'              => __( 'Saving…', 'hibi-journal' ),
			'save.offline'             => __( 'Offline (will retry)', 'hibi-journal' ),
			'save.error'               => __( 'Not saved (will retry)', 'hibi-journal' ),
			'save.conflict'            => __( 'Changed on another device', 'hibi-journal' ),

			// Moods.
			'mood.group'               => __( "Today's mood", 'hibi-journal' ),
			'mood.1'                   => __( 'Awful', 'hibi-journal' ),
			'mood.2'                   => __( 'Bad', 'hibi-journal' ),
			'mood.3'                   => __( 'Okay', 'hibi-journal' ),
			'mood.4'                   => __( 'Good', 'hibi-journal' ),
			'mood.5'                   => __( 'Great', 'hibi-journal' ),
			/* translators: %s: mood name, e.g. "Good". */
			'mood.label'               => __( 'Mood: %s', 'hibi-journal' ),

			// Day screen.
			'day.prev'                 => __( 'Previous day', 'hibi-journal' ),
			'day.next'                 => __( 'Next day', 'hibi-journal' ),
			'day.back_today'           => __( 'Go to today', 'hibi-journal' ),
			/* translators: %d: number of days in a row with an entry. */
			'day.streak'               => __( '%d-day streak', 'hibi-journal' ),
			'day.body'                 => __( 'Entry', 'hibi-journal' ),
			'day.placeholder_today'    => __( 'How was your day?', 'hibi-journal' ),
			'day.placeholder_other'    => __( 'Write about this day', 'hibi-journal' ),
			'day.edit'                 => __( 'Edit', 'hibi-journal' ),
			'day.write_this'           => __( 'Write about this day', 'hibi-journal' ),
			'day.load_failed_offline'  => __( "This day's entry could not be loaded. You may be offline.", 'hibi-journal' ),
			'day.write_anyway'         => __( 'Write anyway (sent when you are back online)', 'hibi-journal' ),
			'day.read_empty'           => __( 'Nothing was written on this day.', 'hibi-journal' ),
			'day.tools'                => __( 'Writing tools', 'hibi-journal' ),
			'day.time'                 => __( 'Time', 'hibi-journal' ),
			'day.tags'                 => __( 'Tags', 'hibi-journal' ),
			/* translators: %s: number of characters, already formatted. */
			'day.chars'                => __( '%s chars', 'hibi-journal' ),
			/* translators: %s: number of characters, already formatted. */
			'day.chars_label'          => __( '%s characters', 'hibi-journal' ),
			'day.prompt_hint'          => __( 'Tap to use this question', 'hibi-journal' ),

			// Conflict.
			'conflict.title'           => __( 'This entry was changed on another device.', 'hibi-journal' ),
			'conflict.body'            => __( 'Choose which version to keep. Saving is paused until you choose, and neither version is lost.', 'hibi-journal' ),
			'conflict.mine'            => __( "Keep this device's version", 'hibi-journal' ),
			'conflict.theirs'          => __( 'Keep the other version', 'hibi-journal' ),
			'conflict.both'            => __( 'Keep both, joined', 'hibi-journal' ),

			// Sync notice.
			'sync.conflicts'           => __( 'Days changed on another device:', 'hibi-journal' ),
			/* translators: %d: number of days with unsent changes. */
			'sync.pending'             => __( '%d day(s) waiting to be sent. They will be sent when you are back online.', 'hibi-journal' ),

			// Photos.
			'photo.button'             => __( 'Photo', 'hibi-journal' ),
			/* translators: %d: number of photos. */
			'photo.list'               => __( 'Photos: %d', 'hibi-journal' ),
			'photo.enlarge'            => __( 'Enlarge photo', 'hibi-journal' ),
			'photo.viewer'             => __( 'Photo', 'hibi-journal' ),
			'photo.original'           => __( 'Original size', 'hibi-journal' ),
			'photo.delete'             => __( 'Delete', 'hibi-journal' ),
			'photo.confirm_delete'     => __( 'Delete this photo? This cannot be undone.', 'hibi-journal' ),
			'photo.delete_failed'      => __( 'The photo could not be deleted. Check your connection.', 'hibi-journal' ),
			'photo.add_failed'         => __( 'The photo could not be added.', 'hibi-journal' ),

			// Tags.
			'tag.list'                 => __( 'Tags', 'hibi-journal' ),
			/* translators: %s: tag name. */
			'tag.remove'               => __( 'Remove %s', 'hibi-journal' ),
			'tag.add'                  => __( 'Add a tag', 'hibi-journal' ),

			// Calendar.
			'cal.prev_month'           => __( 'Previous month', 'hibi-journal' ),
			'cal.next_month'           => __( 'Next month', 'hibi-journal' ),
			/* translators: %s: date, e.g. "October 14". */
			'cal.written'              => __( '%s, written', 'hibi-journal' ),
			/* translators: 1: date, e.g. "October 14", 2: mood name. */
			'cal.written_mood'         => __( '%1$s, written, mood: %2$s', 'hibi-journal' ),
			'stats.days'               => __( 'Days written', 'hibi-journal' ),
			'stats.chars'              => __( 'Characters', 'hibi-journal' ),
			'stats.longest'            => __( 'Longest streak', 'hibi-journal' ),
			/* translators: %s: year, already formatted. */
			'heat.label'               => __( 'Entries in %s', 'hibi-journal' ),
			/* translators: %d: number of days. */
			'heat.days'                => __( '%d days', 'hibi-journal' ),

			// On this day.
			'otd.one'                  => __( 'A year ago today', 'hibi-journal' ),
			/* translators: %d: number of years. */
			'otd.many'                 => __( '%d years ago today', 'hibi-journal' ),
			/* translators: %s: month and day, e.g. "October 14". */
			'otd.title'                => __( 'Entries on %s', 'hibi-journal' ),
			'otd.empty'                => __( 'No entries on this day in past years yet.', 'hibi-journal' ),
			/* translators: %s: year, already formatted. */
			'otd.heading_one'          => __( 'A year ago (%s)', 'hibi-journal' ),
			/* translators: 1: number of years, 2: year, already formatted. */
			'otd.heading_many'         => __( '%1$d years ago (%2$s)', 'hibi-journal' ),

			// Timeline.
			'timeline.title'           => __( 'Timeline', 'hibi-journal' ),
			'timeline.by_tag'          => __( 'Filter by tag', 'hibi-journal' ),
			'timeline.all_tags'        => __( 'All tags', 'hibi-journal' ),
			'timeline.by_mood'         => __( 'Filter by mood', 'hibi-journal' ),
			'timeline.all_moods'       => __( 'All moods', 'hibi-journal' ),
			'timeline.with_photos'     => __( 'With photos', 'hibi-journal' ),
			'timeline.none'            => __( 'No matching entries.', 'hibi-journal' ),

			// Search.
			'search.title'             => __( 'Search', 'hibi-journal' ),
			'search.placeholder'       => __( 'Search your journal', 'hibi-journal' ),
			/* translators: %d: number of results. */
			'search.count'             => __( '%d result(s)', 'hibi-journal' ),
			/* translators: %d: number of results shown so far. */
			'search.count_more'        => __( '%d+ results', 'hibi-journal' ),

			// Settings.
			'settings.title'           => __( 'Settings', 'hibi-journal' ),
			'settings.day_start'       => __( 'Start of the day', 'hibi-journal' ),
			'settings.day_start_hint'  => __( 'Entries written before this time belong to the previous day.', 'hibi-journal' ),
			'settings.reminder'        => __( 'Reminder', 'hibi-journal' ),
			'settings.reminder_hint'   => __( 'One notification, only on days you have not written by this time.', 'hibi-journal' ),
			'settings.notify'          => __( 'Send reminders', 'hibi-journal' ),
			'settings.time'            => __( 'Time', 'hibi-journal' ),
			'settings.test'            => __( 'Send a test notification', 'hibi-journal' ),
			'settings.stop_device'     => __( 'Stop notifications on this device', 'hibi-journal' ),
			'settings.iphone'          => __( 'On iPhone, notifications arrive only in the app added to the Home Screen from Safari (iOS 16.4 or later).', 'hibi-journal' ),
			'settings.push_failed'     => __( 'Notifications could not be set up.', 'hibi-journal' ),
			'settings.test_sent'       => __( 'A test notification was sent.', 'hibi-journal' ),
			'settings.test_none'       => __( 'There is no device to send to.', 'hibi-journal' ),
			'settings.test_failed'     => __( 'The test notification could not be sent.', 'hibi-journal' ),
			'push.unsupported'         => __( 'This browser does not support notifications.', 'hibi-journal' ),
			'push.insecure'            => __( 'Notifications work only when the site is opened over https.', 'hibi-journal' ),
			'push.denied'              => __( 'Notifications are blocked. Allow them in the browser’s site settings.', 'hibi-journal' ),
			'push.off'                 => __( 'This device does not receive notifications.', 'hibi-journal' ),
			'push.on'                  => __( 'This device receives notifications.', 'hibi-journal' ),
			'settings.weather'         => __( 'Weather', 'hibi-journal' ),
			'settings.weather_auto'    => __( "Record the day's weather automatically", 'hibi-journal' ),
			/* translators: %s: place name. */
			'settings.place'           => __( 'Place: %s', 'hibi-journal' ),
			'settings.place_unset'     => __( 'not set', 'hibi-journal' ),
			'settings.city'            => __( 'City', 'hibi-journal' ),
			'settings.city_example'    => __( 'City name (e.g. Toronto)', 'hibi-journal' ),
			'settings.find'            => __( 'Find', 'hibi-journal' ),
			'settings.here'            => __( 'Current location', 'hibi-journal' ),
			'settings.here_unavailable' => __( 'This device cannot share its location.', 'hibi-journal' ),
			'settings.here_getting'    => __( 'Getting your location…', 'hibi-journal' ),
			'settings.here_failed'     => __( 'Your location could not be found. Search by city name instead.', 'hibi-journal' ),
			'settings.weather_source'  => __( 'Weather comes from Open-Meteo (open-meteo.com), fetched through this server.', 'hibi-journal' ),
			'settings.display'         => __( 'Display', 'hibi-journal' ),
			'settings.font_size'       => __( 'Text size', 'hibi-journal' ),
			'settings.size_s'          => __( 'Small', 'hibi-journal' ),
			'settings.size_m'          => __( 'Medium', 'hibi-journal' ),
			'settings.size_l'          => __( 'Large', 'hibi-journal' ),
			'settings.font_family'     => __( 'Typeface for reading', 'hibi-journal' ),
			'settings.sans'            => __( 'Sans-serif', 'hibi-journal' ),
			'settings.serif'           => __( 'Serif', 'hibi-journal' ),
			'settings.color'           => __( 'Colours', 'hibi-journal' ),
			'settings.color_auto'      => __( 'Match the device', 'hibi-journal' ),
			'settings.color_light'     => __( 'Light', 'hibi-journal' ),
			'settings.color_dark'      => __( 'Dark', 'hibi-journal' ),
			'settings.prompts'         => __( 'Show a question on empty days', 'hibi-journal' ),
			'settings.install'         => __( 'Add to the Home Screen', 'hibi-journal' ),
			'settings.install_ios'     => __( 'iPhone and iPad: open this site in Safari, tap the Share button, then “Add to Home Screen”.', 'hibi-journal' ),
			'settings.install_android' => __( 'Android: open the browser menu and choose “Install app” or “Add to Home screen”.', 'hibi-journal' ),
			'settings.install_button'  => __( 'Install the app', 'hibi-journal' ),
			'settings.data'            => __( 'Your data', 'hibi-journal' ),
			'settings.export'          => __( 'Download everything (ZIP)', 'hibi-journal' ),
			'settings.export_hint'     => __( 'Every entry as Markdown and JSON, with photos. Keep a copy somewhere safe.', 'hibi-journal' ),
			'settings.import'          => __( 'Import a Hibi ZIP or JSON file', 'hibi-journal' ),
			'settings.import_hint'     => __( 'Days that already have an entry are skipped.', 'hibi-journal' ),
			/* translators: 1: number of entries imported, 2: number skipped. */
			'settings.import_done'     => __( 'Imported %1$d, skipped %2$d.', 'hibi-journal' ),
			'settings.import_failed'   => __( 'The file could not be imported.', 'hibi-journal' ),
			'settings.logout'          => __( 'Log out', 'hibi-journal' ),
			'settings.logout_unsent'   => __( 'Some changes have not been sent yet. Log out anyway? They stay on this device and are sent the next time you log in here.', 'hibi-journal' ),
			'settings.saved'           => __( 'Saved.', 'hibi-journal' ),

			// Weather labels by WMO code group.
			'weather.clear'            => __( 'Clear', 'hibi-journal' ),
			'weather.partly'           => __( 'Partly cloudy', 'hibi-journal' ),
			'weather.cloudy'           => __( 'Cloudy', 'hibi-journal' ),
			'weather.fog'              => __( 'Fog', 'hibi-journal' ),
			'weather.drizzle'          => __( 'Drizzle', 'hibi-journal' ),
			'weather.rain'             => __( 'Rain', 'hibi-journal' ),
			'weather.snow'             => __( 'Snow', 'hibi-journal' ),
			'weather.thunder'          => __( 'Thunderstorm', 'hibi-journal' ),
		);
	}

	/**
	 * Questions offered on an empty day, one per day.
	 *
	 * @return string[]
	 */
	public static function prompts() {
		return array(
			__( 'What made you smile today?', 'hibi-journal' ),
			__( 'What took most of your time today?', 'hibi-journal' ),
			__( 'Who did you talk to today?', 'hibi-journal' ),
			__( 'What did you eat today that you want to remember?', 'hibi-journal' ),
			__( 'What went better than expected?', 'hibi-journal' ),
			__( 'What would you do differently if you had today again?', 'hibi-journal' ),
			__( 'What did you learn today?', 'hibi-journal' ),
			__( 'What are you looking forward to tomorrow?', 'hibi-journal' ),
			__( 'What tired you out today?', 'hibi-journal' ),
			__( 'What is one small thing you are grateful for?', 'hibi-journal' ),
			__( 'What did you notice on the way somewhere today?', 'hibi-journal' ),
			__( 'What did the children say or do today?', 'hibi-journal' ),
			__( 'What was the weather like, and how did it feel?', 'hibi-journal' ),
			__( 'What did you finish today?', 'hibi-journal' ),
			__( 'What is on your mind right now?', 'hibi-journal' ),
			__( 'What did you read, watch or listen to?', 'hibi-journal' ),
			__( 'What made you laugh?', 'hibi-journal' ),
			__( 'Was there a moment you felt calm today?', 'hibi-journal' ),
			__( 'What worried you, and how big does it feel now?', 'hibi-journal' ),
			__( 'What did you do for your body today?', 'hibi-journal' ),
			__( 'What surprised you today?', 'hibi-journal' ),
			__( 'Who helped you, or whom did you help?', 'hibi-journal' ),
			__( 'What do you want to remember about this week?', 'hibi-journal' ),
			__( 'What did you put off, and why?', 'hibi-journal' ),
			__( 'Where were you at three o’clock this afternoon?', 'hibi-journal' ),
			__( 'What sound, smell or colour stood out today?', 'hibi-journal' ),
			__( 'What would you tell yourself a year from now?', 'hibi-journal' ),
			__( 'What was the best part of the evening?', 'hibi-journal' ),
			__( 'What is something you want to try soon?', 'hibi-journal' ),
			__( 'Describe today in three words, then explain one.', 'hibi-journal' ),
		);
	}
}
