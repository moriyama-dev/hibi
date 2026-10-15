<?php
/**
 * Every text the app shows, translated on the server and handed to the
 * browser with the bootstrap data. The app never contains UI text itself.
 *
 * Placeholders follow sprintf: %s, %d, and %1$s / %2$s for several values.
 *
 * @package Hibi
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array<string,string> Key => translated text.
 */
function hibi_theme_strings() {
	return array(
		// Navigation.
		'nav.main'                 => __( 'Main', 'hibi' ),
		'nav.today'                => __( 'Today', 'hibi' ),
		'nav.review'               => __( 'Look back', 'hibi' ),
		'nav.search'               => __( 'Search', 'hibi' ),
		'nav.settings'             => __( 'Settings', 'hibi' ),
		'review.calendar'          => __( 'Calendar', 'hibi' ),
		'review.timeline'          => __( 'Timeline', 'hibi' ),
		'review.otd'               => __( 'On this day', 'hibi' ),
		'not_found'                => __( 'Page not found', 'hibi' ),
		'back_to_today'            => __( "Back to today's entry", 'hibi' ),

		// Common.
		'common.loading'           => __( 'Loading…', 'hibi' ),
		'common.load_failed'       => __( 'Could not load.', 'hibi' ),
		'common.close'             => __( 'Close', 'hibi' ),
		'common.more'              => __( 'Show more', 'hibi' ),
		'common.searching'         => __( 'Searching…', 'hibi' ),
		'common.no_results'        => __( 'Nothing found.', 'hibi' ),
		'common.search_failed'     => __( 'Search failed.', 'hibi' ),
		'common.sending'           => __( 'Sending…', 'hibi' ),

		// Save status.
		'save.saved'               => __( 'Saved', 'hibi' ),
		'save.dirty'               => __( 'Waiting to save', 'hibi' ),
		'save.saving'              => __( 'Saving…', 'hibi' ),
		'save.offline'             => __( 'Offline (will retry)', 'hibi' ),
		'save.error'               => __( 'Not saved (will retry)', 'hibi' ),
		'save.conflict'            => __( 'Changed on another device', 'hibi' ),

		// Moods.
		'mood.group'               => __( "Today's mood", 'hibi' ),
		'mood.1'                   => __( 'Awful', 'hibi' ),
		'mood.2'                   => __( 'Bad', 'hibi' ),
		'mood.3'                   => __( 'Okay', 'hibi' ),
		'mood.4'                   => __( 'Good', 'hibi' ),
		'mood.5'                   => __( 'Great', 'hibi' ),
		/* translators: %s: mood name, e.g. "Good". */
		'mood.label'               => __( 'Mood: %s', 'hibi' ),

		// Day screen.
		'day.prev'                 => __( 'Previous day', 'hibi' ),
		'day.next'                 => __( 'Next day', 'hibi' ),
		'day.back_today'           => __( 'Go to today', 'hibi' ),
		/* translators: %d: number of days in a row with an entry. */
		'day.streak'               => __( '%d-day streak', 'hibi' ),
		'day.body'                 => __( 'Entry', 'hibi' ),
		'day.placeholder_today'    => __( 'How was your day?', 'hibi' ),
		'day.placeholder_other'    => __( 'Write about this day', 'hibi' ),
		'day.edit'                 => __( 'Edit', 'hibi' ),
		'day.write_this'           => __( 'Write about this day', 'hibi' ),
		'day.load_failed_offline'  => __( "This day's entry could not be loaded. You may be offline.", 'hibi' ),
		'day.write_anyway'         => __( 'Write anyway (sent when you are back online)', 'hibi' ),
		'day.read_empty'           => __( 'Nothing was written on this day.', 'hibi' ),
		'day.tools'                => __( 'Writing tools', 'hibi' ),
		'day.time'                 => __( 'Time', 'hibi' ),
		'day.tags'                 => __( 'Tags', 'hibi' ),
		/* translators: %s: number of characters, already formatted. */
		'day.chars'                => __( '%s chars', 'hibi' ),
		/* translators: %s: number of characters, already formatted. */
		'day.chars_label'          => __( '%s characters', 'hibi' ),
		'day.prompt_hint'          => __( 'Tap to use this question', 'hibi' ),

		// Conflict.
		'conflict.title'           => __( 'This entry was changed on another device.', 'hibi' ),
		'conflict.body'            => __( 'Choose which version to keep. Saving is paused until you choose, and neither version is lost.', 'hibi' ),
		'conflict.mine'            => __( "Keep this device's version", 'hibi' ),
		'conflict.theirs'          => __( 'Keep the other version', 'hibi' ),
		'conflict.both'            => __( 'Keep both, joined', 'hibi' ),

		// Sync notice.
		'sync.conflicts'           => __( 'Days changed on another device:', 'hibi' ),
		/* translators: %d: number of days with unsent changes. */
		'sync.pending'             => __( '%d day(s) waiting to be sent. They will be sent when you are back online.', 'hibi' ),

		// Photos.
		'photo.button'             => __( 'Photo', 'hibi' ),
		/* translators: %d: number of photos. */
		'photo.list'               => __( 'Photos: %d', 'hibi' ),
		'photo.enlarge'            => __( 'Enlarge photo', 'hibi' ),
		'photo.viewer'             => __( 'Photo', 'hibi' ),
		'photo.original'           => __( 'Original size', 'hibi' ),
		'photo.delete'             => __( 'Delete', 'hibi' ),
		'photo.confirm_delete'     => __( 'Delete this photo? This cannot be undone.', 'hibi' ),
		'photo.delete_failed'      => __( 'The photo could not be deleted. Check your connection.', 'hibi' ),
		'photo.add_failed'         => __( 'The photo could not be added.', 'hibi' ),

		// Tags.
		'tag.list'                 => __( 'Tags', 'hibi' ),
		/* translators: %s: tag name. */
		'tag.remove'               => __( 'Remove %s', 'hibi' ),
		'tag.add'                  => __( 'Add a tag', 'hibi' ),

		// Calendar.
		'cal.prev_month'           => __( 'Previous month', 'hibi' ),
		'cal.next_month'           => __( 'Next month', 'hibi' ),
		/* translators: %s: date, e.g. "October 14". */
		'cal.written'              => __( '%s, written', 'hibi' ),
		/* translators: 1: date, e.g. "October 14", 2: mood name. */
		'cal.written_mood'         => __( '%1$s, written, mood: %2$s', 'hibi' ),
		'stats.days'               => __( 'Days written', 'hibi' ),
		'stats.chars'              => __( 'Characters', 'hibi' ),
		'stats.longest'            => __( 'Longest streak', 'hibi' ),
		/* translators: %s: year, already formatted. */
		'heat.label'               => __( 'Entries in %s', 'hibi' ),
		/* translators: %d: number of days. */
		'heat.days'                => __( '%d days', 'hibi' ),

		// On this day.
		'otd.one'                  => __( 'A year ago today', 'hibi' ),
		/* translators: %d: number of years. */
		'otd.many'                 => __( '%d years ago today', 'hibi' ),
		/* translators: %s: month and day, e.g. "October 14". */
		'otd.title'                => __( 'Entries on %s', 'hibi' ),
		'otd.empty'                => __( 'No entries on this day in past years yet.', 'hibi' ),
		/* translators: %s: year, already formatted. */
		'otd.heading_one'          => __( 'A year ago (%s)', 'hibi' ),
		/* translators: 1: number of years, 2: year, already formatted. */
		'otd.heading_many'         => __( '%1$d years ago (%2$s)', 'hibi' ),

		// Timeline.
		'timeline.title'           => __( 'Timeline', 'hibi' ),
		'timeline.by_tag'          => __( 'Filter by tag', 'hibi' ),
		'timeline.all_tags'        => __( 'All tags', 'hibi' ),
		'timeline.by_mood'         => __( 'Filter by mood', 'hibi' ),
		'timeline.all_moods'       => __( 'All moods', 'hibi' ),
		'timeline.with_photos'     => __( 'With photos', 'hibi' ),
		'timeline.none'            => __( 'No matching entries.', 'hibi' ),

		// Search.
		'search.title'             => __( 'Search', 'hibi' ),
		'search.placeholder'       => __( 'Search your journal', 'hibi' ),
		/* translators: %d: number of results. */
		'search.count'             => __( '%d result(s)', 'hibi' ),
		/* translators: %d: number of results shown so far. */
		'search.count_more'        => __( '%d+ results', 'hibi' ),

		// Settings.
		'settings.title'           => __( 'Settings', 'hibi' ),
		'settings.day_start'       => __( 'Start of the day', 'hibi' ),
		'settings.day_start_hint'  => __( 'Entries written before this time belong to the previous day.', 'hibi' ),
		'settings.reminder'        => __( 'Reminder', 'hibi' ),
		'settings.reminder_hint'   => __( 'One notification, only on days you have not written by this time.', 'hibi' ),
		'settings.notify'          => __( 'Send reminders', 'hibi' ),
		'settings.time'            => __( 'Time', 'hibi' ),
		'settings.test'            => __( 'Send a test notification', 'hibi' ),
		'settings.stop_device'     => __( 'Stop notifications on this device', 'hibi' ),
		'settings.iphone'          => __( 'On iPhone, notifications arrive only in the app added to the Home Screen from Safari (iOS 16.4 or later).', 'hibi' ),
		'settings.push_failed'     => __( 'Notifications could not be set up.', 'hibi' ),
		'settings.test_sent'       => __( 'A test notification was sent.', 'hibi' ),
		'settings.test_none'       => __( 'There is no device to send to.', 'hibi' ),
		'settings.test_failed'     => __( 'The test notification could not be sent.', 'hibi' ),
		'push.unsupported'         => __( 'This browser does not support notifications.', 'hibi' ),
		'push.insecure'            => __( 'Notifications work only when the site is opened over https.', 'hibi' ),
		'push.denied'              => __( 'Notifications are blocked. Allow them in the browser’s site settings.', 'hibi' ),
		'push.off'                 => __( 'This device does not receive notifications.', 'hibi' ),
		'push.on'                  => __( 'This device receives notifications.', 'hibi' ),
		'settings.weather'         => __( 'Weather', 'hibi' ),
		'settings.weather_auto'    => __( "Record the day's weather automatically", 'hibi' ),
		/* translators: %s: place name. */
		'settings.place'           => __( 'Place: %s', 'hibi' ),
		'settings.place_unset'     => __( 'not set', 'hibi' ),
		'settings.city'            => __( 'City', 'hibi' ),
		'settings.city_example'    => __( 'City name (e.g. Toronto)', 'hibi' ),
		'settings.find'            => __( 'Find', 'hibi' ),
		'settings.here'            => __( 'Current location', 'hibi' ),
		'settings.here_unavailable' => __( 'This device cannot share its location.', 'hibi' ),
		'settings.here_getting'    => __( 'Getting your location…', 'hibi' ),
		'settings.here_failed'     => __( 'Your location could not be found. Search by city name instead.', 'hibi' ),
		'settings.weather_source'  => __( 'Weather comes from Open-Meteo (open-meteo.com), fetched through this server.', 'hibi' ),
		'settings.display'         => __( 'Display', 'hibi' ),
		'settings.font_size'       => __( 'Text size', 'hibi' ),
		'settings.size_s'          => __( 'Small', 'hibi' ),
		'settings.size_m'          => __( 'Medium', 'hibi' ),
		'settings.size_l'          => __( 'Large', 'hibi' ),
		'settings.font_family'     => __( 'Typeface for reading', 'hibi' ),
		'settings.sans'            => __( 'Sans-serif', 'hibi' ),
		'settings.serif'           => __( 'Serif', 'hibi' ),
		'settings.color'           => __( 'Colours', 'hibi' ),
		'settings.color_auto'      => __( 'Match the device', 'hibi' ),
		'settings.color_light'     => __( 'Light', 'hibi' ),
		'settings.color_dark'      => __( 'Dark', 'hibi' ),
		'settings.prompts'         => __( 'Show a question on empty days', 'hibi' ),
		'settings.install'         => __( 'Add to the Home Screen', 'hibi' ),
		'settings.install_ios'     => __( 'iPhone and iPad: open this site in Safari, tap the Share button, then “Add to Home Screen”.', 'hibi' ),
		'settings.install_android' => __( 'Android: open the browser menu and choose “Install app” or “Add to Home screen”.', 'hibi' ),
		'settings.install_button'  => __( 'Install the app', 'hibi' ),
		'settings.data'            => __( 'Your data', 'hibi' ),
		'settings.export'          => __( 'Download everything (ZIP)', 'hibi' ),
		'settings.export_hint'     => __( 'Every entry as Markdown and JSON, with photos. Keep a copy somewhere safe.', 'hibi' ),
		'settings.import'          => __( 'Import a Hibi ZIP or JSON file', 'hibi' ),
		'settings.import_hint'     => __( 'Days that already have an entry are skipped.', 'hibi' ),
		/* translators: 1: number of entries imported, 2: number skipped. */
		'settings.import_done'     => __( 'Imported %1$d, skipped %2$d.', 'hibi' ),
		'settings.import_failed'   => __( 'The file could not be imported.', 'hibi' ),
		'settings.logout'          => __( 'Log out', 'hibi' ),
		'settings.logout_unsent'   => __( 'Some changes have not been sent yet. Log out anyway? They stay on this device and are sent the next time you log in here.', 'hibi' ),
		'settings.saved'           => __( 'Saved.', 'hibi' ),

		// Weather labels by WMO code group.
		'weather.clear'            => __( 'Clear', 'hibi' ),
		'weather.partly'           => __( 'Partly cloudy', 'hibi' ),
		'weather.cloudy'           => __( 'Cloudy', 'hibi' ),
		'weather.fog'              => __( 'Fog', 'hibi' ),
		'weather.drizzle'          => __( 'Drizzle', 'hibi' ),
		'weather.rain'             => __( 'Rain', 'hibi' ),
		'weather.snow'             => __( 'Snow', 'hibi' ),
		'weather.thunder'          => __( 'Thunderstorm', 'hibi' ),
	);
}

/**
 * Questions offered on an empty day, one per day.
 *
 * @return string[]
 */
function hibi_theme_prompts() {
	return array(
		__( 'What made you smile today?', 'hibi' ),
		__( 'What took most of your time today?', 'hibi' ),
		__( 'Who did you talk to today?', 'hibi' ),
		__( 'What did you eat today that you want to remember?', 'hibi' ),
		__( 'What went better than expected?', 'hibi' ),
		__( 'What would you do differently if you had today again?', 'hibi' ),
		__( 'What did you learn today?', 'hibi' ),
		__( 'What are you looking forward to tomorrow?', 'hibi' ),
		__( 'What tired you out today?', 'hibi' ),
		__( 'What is one small thing you are grateful for?', 'hibi' ),
		__( 'What did you notice on the way somewhere today?', 'hibi' ),
		__( 'What did the children say or do today?', 'hibi' ),
		__( 'What was the weather like, and how did it feel?', 'hibi' ),
		__( 'What did you finish today?', 'hibi' ),
		__( 'What is on your mind right now?', 'hibi' ),
		__( 'What did you read, watch or listen to?', 'hibi' ),
		__( 'What made you laugh?', 'hibi' ),
		__( 'Was there a moment you felt calm today?', 'hibi' ),
		__( 'What worried you, and how big does it feel now?', 'hibi' ),
		__( 'What did you do for your body today?', 'hibi' ),
		__( 'What surprised you today?', 'hibi' ),
		__( 'Who helped you, or whom did you help?', 'hibi' ),
		__( 'What do you want to remember about this week?', 'hibi' ),
		__( 'What did you put off, and why?', 'hibi' ),
		__( 'Where were you at three o’clock this afternoon?', 'hibi' ),
		__( 'What sound, smell or colour stood out today?', 'hibi' ),
		__( 'What would you tell yourself a year from now?', 'hibi' ),
		__( 'What was the best part of the evening?', 'hibi' ),
		__( 'What is something you want to try soon?', 'hibi' ),
		__( 'Describe today in three words, then explain one.', 'hibi' ),
	);
}
