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
