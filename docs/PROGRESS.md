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
