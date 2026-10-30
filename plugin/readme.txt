=== Hibi Journal ===
Contributors: yoshiromoriyama
Tags: journal, diary, private, writing, pwa
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A private daily journal for WordPress: one page per day, autosave, offline writing, photos, search and a daily reminder.

== Description ==

Hibi Journal adds a private journal to any WordPress site. Open yoursite.com/journal/ and today's page is ready to type: no editor screens, no publish button. It works with any theme, can be added to a phone's home screen, and keeps working offline.

A theme that declares `add_theme_support( 'hibi-app' )` gives the journal the front page of the site instead of /journal/.

**What it does**

* One entry per day and per user, always private. Other users never see another person's journal.
* The day starts at 4:00 by default, so writing after midnight still belongs to the day you lived.
* Entries are stored as plain Markdown text, readable by any tool in years to come.
* Mood (five steps), tags, and the day's weather from Open-Meteo.
* Conflict detection: if two devices change the same day, nothing is overwritten; the writer chooses.
* Thinned revisions: at most one every ten minutes, plus one when you leave the page.
* Photos are stored in a folder the web server refuses to serve and are delivered only to their owner. Every photo is re-encoded, which removes location (GPS) data.
* Search across all entries, a calendar, a timeline and "on this day".
* A daily reminder notification (Web Push), only on days you have not written yet.
* Export everything as a ZIP (JSON, Markdown per day, photos) and import it again.
* Installable as an app (PWA), with offline writing that is sent when the connection is back.
* Japanese input friendly: autosave never interrupts an IME composition.
* WP-CLI: `wp hibi export`, `wp hibi import`, `wp hibi send-reminders`.

**Source code**

The app's JavaScript in `assets/app/` is built from the TypeScript source included in the `app/` folder of this plugin (Preact and Vite). The PHP libraries in `vendor-prefixed/` are minishlink/web-push and its dependencies, moved under the `Hibi\Vendor` namespace with Strauss so they cannot clash with other plugins.

**Privacy**

Entries are saved as private posts. Photos are kept outside the public media library. Nothing is sent to third parties except the weather lookup described below. When "Add where I am" is turned on, the browser's position is saved with the day's entry on this site only; no address lookup service is used. A place can be removed from an entry at any time.

== External services ==

When weather is turned on in the settings, the server asks Open-Meteo for the day's weather and, when you search for a place, for matching place names.

* Weather: https://api.open-meteo.com/v1/forecast and https://archive-api.open-meteo.com/v1/archive — sent: latitude and longitude rounded to two decimals, the date and the site's time zone.
* Place search: https://geocoding-api.open-meteo.com/v1/search — sent: the place name you typed and a language code.

The browser never contacts Open-Meteo directly. Open-Meteo terms: https://open-meteo.com/en/terms — privacy: https://open-meteo.com/en/terms#privacy

Reminder notifications are delivered through the push service of the user's browser (for example Google Firebase Cloud Messaging for Chrome, Mozilla Push Service for Firefox, Apple Push Notification service for Safari). The notification text is encrypted end to end; the push service sees only the encrypted payload and the device address the browser created.

== Installation ==

1. Install and activate the plugin.
2. Open yoursite.com/journal/ and start writing. Logged-out visitors are sent to the login form first.
3. Optional: to make the journal the front page, use a theme that declares `add_theme_support( 'hibi-app' )`.
4. For reminders, add a server cron job that runs every five minutes:
   `*/5 * * * * wp --path=/path/to/wordpress hibi send-reminders --quiet`
   WP-Cron runs the same job as a backup.

Reminders need the PHP extensions OpenSSL and either GMP or BCMath. HEIC photos need Imagick with HEIC support.

== Frequently Asked Questions ==

= Is the journal encrypted? =

No. Entries are private posts in the WordPress database. Use HTTPS, keep backups, and consider a site-wide login requirement.

= What happens to my entries if I deactivate or delete the plugin? =

Nothing is deleted. Entries stay in the database as private posts, and photos stay in the uploads folder.

= Can I change the address /journal/? =

Yes, with the `hibi_app_slug` filter, for example `add_filter( 'hibi_app_slug', fn() => 'diary' );`.

= Can other people on the site see my journal? =

No. Each user has their own journal, and entries are private posts that only their author can read through the app.

= Can I move my journal to another site? =

Yes. Download the ZIP from the settings screen (or run `wp hibi export`) and import it on the other site.

== Credits ==

The app bundles Preact (MIT, https://github.com/preactjs/preact), @preact/signals (MIT, https://github.com/preactjs/signals), marked (MIT, https://github.com/markedjs/marked) and DOMPurify (Apache-2.0 or MPL-2.0, https://github.com/cure53/DOMPurify). The icons were made for this plugin and are released under the GPL v2 or later.

== Changelog ==

= 0.1.0 =
* First version.
