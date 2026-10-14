# Progress

All times are America/Toronto.

## Phase 0 — Setup and survey (2026-10-14)

### Environment

| | |
|---|---|
| WordPress | 7.1, single site |
| PHP | 8.3.6 (extensions present: openssl, mbstring, gd, exif, zip; missing: gmp, bcmath, imagick) |
| Database | MySQL 8.0.46 |
| Web server | Apache 2.4.58 |
| Node / npm | 24.19.0 / 11.17.0 (nvm) |
| composer | not installed |
| Site timezone | changed from Asia/Tokyo to America/Toronto |

### Takumi Private Gate integration

- Front end: `template_redirect` calls `auth_redirect()` for visitors who are not logged in and not on the IP whitelist.
- REST: `rest_authentication_errors` rejects unauthenticated requests.
- There is no filter to exclude a URL. None is needed: every Hibi request (app shell, REST, manifest, service worker) is made by a logged-in user with cookies, so the gate stays as it is.
- The service worker script must be served through WordPress (not as a static theme file) to get a root `/` scope. When logged out, a service worker update fetch is redirected and fails, which keeps the installed worker in place. This is acceptable.

### Done

- Repository at `~/projects/hibi` with `plugin/` and `theme/`.
- `plugin/` symlinked to `wp-content/plugins/hibi-journal`, `theme/` to `wp-content/themes/hibi`. Both are detected by WordPress (inactive).
- Plugin and theme headers (version 0.1.0).
- `hibi-journal` and `hibi` are unused on WordPress.org (plugins and themes).

### Open items

- Install composer and the PHP extensions gmp (or bcmath) and imagick. These need root.
- Takumi PWA (separate project) also registers a service worker. Both must not be active on the same site.

## Phase 1 — Plugin foundation (2026-10-14)

### Done

- Post type `hibi_entry` (always private, one per author per day, dated local noon) and taxonomy `hibi_tag`.
- Day rules: the journal day starts at the user's `day_start_hour` (default 4:00), evaluated on the wall clock in the site timezone, so DST transition days keep the same boundary.
- Saving runs under a MySQL named lock per author and date. Six simultaneous first saves produced one entry (one 200, five 409).
- Optimistic locking with `_hibi_version`; a stale `base_version` returns 409 with the server copy.
- Markdown is stored verbatim (kses is bypassed for this post type only and restored afterwards; the reader escapes before rendering).
- Revisions: at most one per 10 minutes, plus one when the page is left; 50 kept.
- REST `hibi/v1`: `bootstrap`, `entries/{date}` (GET, PUT), `settings` (GET, PUT), `stats`. Responses are `Cache-Control: private, no-store`.
- Nonce refresh lives at `admin-ajax.php?action=hibi_nonce` instead of `GET /nonce`: once the REST nonce has expired, WordPress treats REST requests as logged out, so a REST route could not hand out the replacement.
- `wp hibi seed --days=<n>` writes into a dedicated user `hibi-seed`; `wp hibi seed --clean` removes the user and every entry.
- PHPUnit suite (`tests/php`) and `bin/install-wp-tests.sh`, which installs a separate WordPress core and the test library under `~/.cache/hibi-wp-tests/` and refuses to use the site database.

### Measurements (2,552 entries over 3,000 days, HTTP on the VM)

| Request | Time |
|---|---|
| `GET bootstrap` | 37 ms |
| `GET stats` | 35 ms |
| `GET entries/{date}` | 12 ms |
| `wp hibi seed --days=3000` | 259 s |
| `wp hibi seed --clean` | 207 s |

### Checked over HTTP

- Logged out: 401 (from the access gate). Invalid nonce: 403 `rest_cookie_invalid_nonce`.
- Another user's date returns `exists: false`; writing it creates that user's own entry and leaves the owner's untouched.
- Future date: 400. Invalid date or mood: 400.

### Packages installed

- composer 2.7.1, php8.3-gmp, php8.3-bcmath, php8.3-imagick (HEIC supported), php8.3-intl.
- Dev: phpunit/phpunit 9.6, yoast/phpunit-polyfills 2.

### Open items

- PHPUnit: 44 tests pass against the separate test database `wp_hibi_test`.
- The site uses plain permalinks and Apache has `AllowOverride None`, so `/wp-json/` and `.htaccess` files do not work on the VM. This affects the app URLs (`/d/YYYY-MM-DD`) and the photo folder protection.

## Phase 2 — Today screen (2026-10-14)

### Done

- Theme `hibi` (classic theme) serves one shell for `/`, `/d/YYYY-MM-DD/`, `/calendar/`, `/timeline/`, `/search/`, `/on-this-day/` and `/settings/`; other URLs get the shell with a 404 status. Block styles and emoji scripts are dropped from the shell.
- The shell embeds the bootstrap data (including today's entry), so the first paint makes no API call.
- Front end: TypeScript, Preact, Preact signals, Vite. Built files are committed in `theme/assets/dist` (JS 14.2 KB gzip, CSS 1.9 KB gzip).
- Editor: an uncontrolled, self-sizing textarea. Autosave 1.5 s after typing stops and at least every 10 s; an immediate keepalive save on `visibilitychange` (hidden), `pagehide` and in-app navigation.
- IME: no autosave, character count or value change between `compositionstart` and `compositionend`. These listeners are registered with `addEventListener`, because Preact would bind `onCompositionEnd` to an event named "CompositionEnd".
- Mood (five buttons, tap again to clear), time stamp insertion, character count, Ctrl/Cmd+B and +I, previous and next day (buttons, Alt+Left/Right, horizontal swipe).
- Conflict banner: keep this device's text, keep the other, or join both. Saving pauses until a choice is made; nothing is discarded.
- Save status with `aria-live`: saved, waiting, saving, offline, error, conflict. Failed saves retry with backoff and on `online`.
- Toolbar follows the on-screen keyboard through `visualViewport`; bottom tabs hide while the keyboard is open. Left sidebar from 900 px.
- Login (only while the active theme declares `hibi-app`): redirect to `/` after login unless a specific page was requested, no admin bar on the front end, "Remember me" ticked by default and kept for one year.

### Site changes

- Permalinks set to `/%postname%/`; Apache `AllowOverride All` for `/var/www/` (previous config kept as `/etc/apache2/apache2.conf.bak-20261014`).
- Active theme switched to `hibi`. Switch back with `wp theme activate takumi-web-services`.

### Tests

- Playwright, desktop and mobile (Pixel 7) projects: 16 of 16 pass. Covers reload persistence, IME composition, mood, time stamp, save on leaving, day navigation, conflict resolution and the embedded first paint. The tests use a dedicated user `hibi-e2e`, deleted afterwards.
- Login over HTTP: redirect to `/`, `wordpress_logged_in` cookie expires in one year, "Remember me" pre-ticked.

### Packages installed

- Dev: vite 6, typescript 5, @preact/preset-vite 2, @playwright/test 1 (Chromium headless shell and its system libraries).
- Runtime (bundled): preact 10, @preact/signals 2.

### Open items

- Takumi PWA is active on the same site and prints its manifest, icons, script and stylesheet into the Hibi shell. This conflicts with the Hibi service worker planned for phase 4.

## Phase 3 — Looking back (2026-10-14)

### Done

- Reading view for past days: Markdown via marked and DOMPurify, loaded on demand (24.5 KB gzip, not part of the first load). `&` and `<` are escaped before parsing, so typed HTML shows as text and a line starting with `<` cannot swallow the lines after it. Only the entry's own photos (`hibi-photo:<id>`) render as images; links open in a new tab with `noopener noreferrer`.
- Past days open for reading; "Edit" switches to the editor.
- Calendar (month grid: dot colour is the mood, size is the length), month totals, year heat map.
- Timeline with infinite scroll and filters (tag, mood, has photo).
- Search: every term must match (space or full-width space separates terms), results highlighted from server-side segments, 300 ms debounce, query kept in the URL, Ctrl/Cmd+K from anywhere. On MySQL 8 the comparison uses `utf8mb4_0900_as_ci`: hiragana finds katakana and case is ignored, but は and ば stay different. Elsewhere the column collation is used.
- On this day: a card on today's page (loaded after the editor is ready) and a full page.
- Tags: editor with suggestions from the writer's own tags (`GET tags`).
- Photos: resized to 2560 px and re-encoded in the browser; on the server re-encoded again with Imagick (orientation applied, metadata stripped, sizes 2560/1200/400) or GD as a fallback. Stored in `uploads/hibi-private/{user}/{YYYY}/{MM}/` under unguessable names, with `.htaccess` (`Require all denied` and `Deny from all`) and `index.php`. Served by `?hibi_photo=<id>&size=` after a login and ownership check (`Cache-Control: private, max-age=31536000, immutable`). Not a REST route: an `<img>` request carries the cookie but no REST nonce. Hidden from the media library. Deleting an entry deletes its photos.
- REST: `entries` (list), `calendar`, `year`, `on-this-day`, `search`, `tags`, `entries/{date}/photos` (POST), `photos/{id}` (DELETE).

### Measurements (2,536 entries over 3,000 days, HTTP on the VM)

| Request | Time |
|---|---|
| `search?q=コーヒー` | 25 ms |
| `search?q=こーひー` (finds コーヒー) | 26 ms |
| `search` with no match | 14 ms |
| `entries?limit=20` | 44 ms |
| `entries?tag=子供` | 19 ms |
| `calendar` | 12 ms |
| `year` | 15 ms |
| `on-this-day` | 13 ms |

First load: JS 19.7 KB + CSS 3.4 KB gzip.

### Tests

- PHPUnit: 67 tests pass (photos: GPS removed, orientation applied, capture time kept, size caps, owner-only delete, entry deletion; queries; search terms, highlighting, kana, voiced kana, LIKE wildcards, paging; per-author isolation).
- Playwright: 30 of 30 pass on desktop and mobile. New: reading view and escaping, calendar, timeline filter, search with Ctrl/Cmd+K, on this day, photo upload (GPS gone, 401 logged out, 403 for the file URL, delete), tags.

## Phase 4 — App install, offline and reminders (2026-10-14)

### Done

- Manifest at `/manifest.webmanifest` (linked with `crossorigin="use-credentials"`) and service worker at `/hibi-sw.js` with `Service-Worker-Allowed: /`. Both are answered in `parse_request`, before the access gate, because neither contains journal data and the browser must be able to update the worker while logged out.
- Icons (192, 512, maskable 512, Apple touch 180, favicon) drawn from shapes by `bin/make-icons.php`.
- Service worker: app screens network-first with a 4 s timeout, falling back to the last shell (only pages marked `X-Hibi-Shell: 1` are stored, never a login form); built assets and icons cache-first, precached per build; API GETs network-first with a 300-entry cache; photo thumbnails cache-first, 300 entries. The worker fetches the shell itself on activation, because the page that installed it never passed through it.
- Unsent edits are written to IndexedDB (`hibi-<user id>`) on every change, before any request. Drafts of the open day are sent by its editor; others are sent in the background on start-up, on `online` and when the app becomes visible. A notice lists days still waiting or in conflict.
- A 409 whose server copy already matches the unsent fields is treated as saved (a save sent while the page was closing can reach the server after the page is gone).
- The "leave this page?" prompt was removed: with drafts on the device it only got in the way.
- Offline, a day that was never loaded can still be written ("write anyway"); the conflict banner handles any clash later.
- Web Push: VAPID keys created on activation (`hibi_vapid_keys`, not autoloaded); `minishlink/web-push` 9 bundled in `plugin/vendor`. Subscriptions in user meta (up to 10 devices, HTTPS endpoints only). Reminders: once a day, after the reminder time, only if the day is still empty; a reminder time before the day start hour belongs to the evening before. `wp hibi send-reminders` for the server cron, and a five-minute WP-Cron event as a backup, with a lock so both cannot send twice. Endpoints that answer 404/410 are removed.
- Weather from Open-Meteo through the server (`GET weather`), cached for an hour; dates older than 90 days use the archive API. Place chosen in the settings by name search (`GET places`, Open-Meteo geocoding; Japanese queries are searched in Japanese) or the current position. Today's weather is added once, after the editor is ready.
- Settings screen (first part): reminder on/off, time, test notification, iPhone note; weather on/off and place; day start hour.

### VM setup

- Takumi PWA deactivated while Hibi is developed (settings kept).
- `crontab` for yoshi: `*/5 * * * * /usr/local/bin/wp --path=/var/www/html hibi send-reminders --quiet >> ~/logs/hibi-reminders.log 2>&1`. First run at 18:25 without errors.

### Tests

- PHPUnit: 77 tests pass. New: VAPID keys, subscription validation, encrypted delivery to a local stand-in push service (aes128gcm, VAPID header, removal on 410), reminder timing (before/after the time, once a day, not when written, after-midnight times), cron schedule, weather (mocked HTTP, cache, archive URL, failure), weather place validation.
- Playwright: 40 of 40 pass on desktop and mobile with full Chromium and `--unsafely-treat-insecure-origin-as-secure` (the VM serves plain http). New: manifest and service worker scope, writing offline and reloading offline, background sending of another day, offline conflict, weather place.
- "Leaving the page saves immediately" failed once in about fifty runs: the keepalive save did not reach the server within 5 s. The text was not lost (the draft is sent on the next visit), and the test now checks that guarantee.

### Not verified

- Real push delivery to a phone, and the service worker on a phone: both need HTTPS, which the VM does not have.
