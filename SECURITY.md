# Security documentation

This document explains **what is protected, how, and what is deliberately out of scope** for
Version 1. It is written so the site owner can verify each control and answer questions from users.

The design assumption is hostile: every request may be forged, every field may contain an attack,
and the database may be exposed if the host is misconfigured. The code therefore trusts nothing
from the client and keeps all private data behind PHP.

---

## 1. Architecture and trust boundaries

```
Browser (untrusted)
   │  HTTPS, session cookie, CSRF token
   ▼
public_html/            ← the only web-served directory
   │  includes app/_bootstrap.php
   ▼
app/ (PHP 8.1+)         ← controllers, validation, auth, rate limits
   │  PDO prepared statements only
   ▼
MySQL (utf8mb4)         ← users, bookmarks, progress, settings, content cache
```

- The browser never talks to MySQL. There is no database driver, connection string or credential in
  any JavaScript file, HTML page or API response.
- The public content API is contacted **only** by the importer (`bin/import-quran.php`) or the
  token-protected cron endpoint. Visitors are served from MySQL, so a third-party outage cannot take
  the reader down.
- `app/`, `bin/`, `database/` and `storage/` are outside the web root in the recommended layout, and
  each carries a deny-all `.htaccess` in case the project is uploaded inside `public_html`.

---

## 2. Authentication and passwords

| Control | Implementation |
|---|---|
| Hashing | `password_hash($password, PASSWORD_DEFAULT)` — bcrypt today, Argon2id on builds that default to it. Cost is PHP's current default and is raised automatically. |
| Verification | `password_verify()`, followed by `password_needs_rehash()` → transparent upgrade on next login. |
| Plain text | Never stored, never logged, never emailed. The logger redacts keys containing `password`, `token`, `secret`, `authorization`, `csrf`, `api_key`. |
| Password policy | Minimum 8 characters, maximum 200 (prevents hash-cost abuse), confirmation field on register/reset. |
| Timing attacks | Unknown emails still run a real `password_verify()` against a per-request dummy hash, so response time does not reveal account existence. |
| Enumeration | Login returns *“Incorrect email or password.”*; registration returns *“That email address cannot be used. Try signing in instead.”*; forgot-password always returns the same confirmation. |
| Reset tokens | 32 random bytes (`random_bytes`) → 64-char hex in the email; only its SHA-256 hash is stored. Single use, 60-minute expiry, all older tokens invalidated when a new one is issued and after a successful reset. |
| Brute force | Fixed-window limiter in MySQL: 6 attempts / 15 min per email+IP, 25 / 15 min per IP, 5 registrations / hour per IP, 3 reset requests / 15 min per email, 10 / hour per IP. |
| Password change | Requires the current password, is rate limited to 5 / hour, and old reset tokens are cleared. |

Reset links are bearer tokens. Treat an email inbox as sensitive: whoever reads the link can set a
new password until it expires or is used.

---

## 3. Sessions

Configured in `app/src/Support/Session.php`, applied before any output:

- `session.use_strict_mode=1` — the server refuses session ids it never issued (fixation defence).
- `session.use_only_cookies=1`, `HttpOnly=1` — no ids in URLs, no JavaScript access.
- `SameSite=Lax` by default; `None` is possible but requires HTTPS.
- `Secure` automatically whenever HTTPS is detected (`cookie_secure => 'auto'`).
- Id rotated every 15 minutes and, critically, on login (privilege change) and logout.
- 3-hour idle timeout; the cookie itself can live 30 days.
- On logout the cookie is deleted and `session_destroy()` is called.

Session data lives server-side. The cookie only contains the id — never the email, the user id or
any preference.

**CSRF.** Every state-changing request must present the per-session token, read from the
`X-CSRF-Token` header or the `_csrf` field, compared with `hash_equals()`. Origin (or Referer) is
also checked against the request host. The token is rotated on login and logout. `GET` endpoints are
read-only by construction; `/logout.php` is POST-only so a link cannot sign a visitor out.

---

## 4. Database access

- One PDO wrapper (`app/src/Support/Database.php`), `PDO::ATTR_EMULATE_PREPARES => false`, exceptions
  on, `utf8mb4`.
- Every query uses bound parameters. There is no string concatenation of user input anywhere in the
  code base — a `grep` for `query(` outside that wrapper returns nothing.
- `LIMIT`/`OFFSET` cannot be bound portably on MySQL, so they are cast with `(int)` and clamped to
  safe ranges before being placed in the SQL string.
- `LIKE` search escapes `\`, `%` and `_` so a wildcard cannot be injected by the visitor.
- Identifiers (column names, sort orders, translation/reciter ids) are never taken from the request:
  only enumerated values from configuration or the database are accepted.
- Foreign keys use `ON DELETE CASCADE`, so deleting an account removes its bookmarks, progress and
  settings in one statement.
- The DB user only needs `SELECT, INSERT, UPDATE, DELETE` on this schema. `CREATE`, `DROP` and
  `GRANT` are not required at runtime.

---

## 5. Input validation and output encoding

- `Quran\Support\Validator` reads only the fields a controller asks for; unknown fields are ignored
  (no mass assignment).
- Types are explicit: integers are range-checked (`surah` 1–114, `verse` 1–≈300, page 1–604), emails
  go through `FILTER_VALIDATE_EMAIL`, enums are checked against allow-lists
  (`theme`, `arabic_font_size`, `translation_id`, `reciter_id`).
- Bookmark imports validate every pair against the `verses` table, cap at 500 items and ignore
  duplicates, so a crafted payload cannot create rows for non-existent verses.
- All HTML output goes through `e()` (`htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE`).
  Templates never echo a request value without it.
- JSON responses use `json_encode` with `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`; the client
  inserts values with `textContent` or an explicit `escapeHtml()` helper.

---

## 6. HTTP hardening

Set in `app/src/Support/SecurityHeaders.php` on every response (and statically in `.htaccess`):

```
Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none';
  frame-ancestors 'self'; form-action 'self'; script-src 'self';
  style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';
  media-src 'self' https://cdn.islamic.network; connect-src 'self'
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()
Cross-Origin-Opener-Policy: same-origin
Strict-Transport-Security: max-age=31536000; includeSubDomains   (HTTPS only)
```

- `script-src 'self'` means **no inline scripts, no third-party scripts, no `eval`**. All behaviour
  lives in `assets/js/*.js` and is attached with `addEventListener`.
- `'unsafe-inline'` is granted to styles only, because the reader sets inline `style` attributes for
  the progress bar and font size. No user-controlled value ever reaches a `style` attribute.
- `media-src` is limited to the site plus the configured audio CDN host.
- CORS headers are **not** sent unless `security.cors_allowed_origins` is configured; same-origin
  deployments get none.
- `no-store, private` on PHP responses, so authenticated pages are never cached by proxies.

---

## 7. Application-level authorisation

- Every personal endpoint requires a session (`Api::run(..., ['auth' => true])`) and derives the user
  id from the session — never from a request parameter.
- Repository methods always filter by `user_id = ?`, so one account cannot read or modify another's
  bookmarks, progress or settings (defence in depth: the API never accepts a user id at all).
- Deleting a bookmark accepts either its id or a surah/verse pair; both are scoped by `user_id`.
- Highlights and notes (`/api/marks/*`) behave the same way: the verse is validated against
  `verses`, colours are checked against a fixed allowlist, notes are capped at 1,000 characters,
  and every read/write is filtered by the session's `user_id`. There is no endpoint that accepts a
  user id, and no public sharing of notes.
- Account deletion requires the password and cascades to all personal rows (bookmarks, notes,
  highlights, progress, settings).

---

## 8. Secrets and configuration

- Credentials live in `app/config/config.php` or `.env`, both outside `public_html`, both listed in
  `.gitignore`, both blocked by `.htaccess` deny rules.
- Environment variables (`DB_PASSWORD`, `SYNC_TOKEN`, `MAIL_*`, `CURL_CA_BUNDLE`, …) override file
  values, for hosts that inject configuration.
- No secret is ever written into HTML or JSON. `api/health.php` reports booleans and counts only.
- The cron endpoint requires `maintenance.sync_token` with `hash_equals()`; when the token is empty
  the endpoint returns 403 instead of running unauthenticated. It is also rate limited (6/hour/IP)
  and has a configurable cooldown.
- `app.debug` must be `false` in production; it is the only switch that can expose stack traces.

---

## 9. Denial of service and resource limits

- Rate limits on login, registration, reset, password change, search, bookmarks, progress, settings
  and cron — all in MySQL, so they survive PHP restarts and work on shared hosting without Redis.
- Search is capped at 30 rows per request and 120 requests/minute/IP; page offsets are clamped.
- Bookmark imports are capped at 500 items, accounts at 2,000 bookmarks.
- Surah pages are cached by the browser and compressed by `mod_deflate`; the CSS/JS/fonts are
  versioned for long-lived caching.
- The importer is CLI or token-protected only, with a cooldown, so it cannot be used to amplify
  traffic against the upstream API.

---

## 10. Known limitations (V1)

Being explicit about these is part of the security posture:

1. **No email verification.** Anyone can register with an address they do not control. This is a
   product decision for a frictionless reader; add verification before enabling any email-based
   feature beyond password reset.
2. **No 2FA.** Password-only authentication.
3. **No admin panel.** Account and content operations are done with `bin/user.php` and
   `bin/import-quran.php`, i.e. by someone with server access. There is no web-exposed admin surface
   to attack.
4. **No CAPTCHA.** Rate limiting is the only automated-abuse control. If spam registrations appear,
   add a CAPTCHA or invite-only registration.
5. **Shared-hosting trust.** The host's PHP process and database are outside this application's
   control. Use a unique database password, keep the panel account secured, and prefer the layout
   that puts `app/` outside the web root.
6. **No certificate pinning or SRI** — all assets are first-party and served from the same origin,
   so there is nothing to pin.
7. **Password reset depends on email.** If the host cannot send mail, an administrator must issue a
   link with `php bin/user.php reset-link`.
8. **The offline copy is private to the browser, not encrypted at rest.** Cached surah pages contain
   the signed-in reader's own bookmarks and notes, stored by the browser's Cache API. Anyone with
   access to that device profile can read them. Offer “Remove download” on shared devices; the
   service worker never caches other users' data, and the cache is cleared on sign-out only if the
   reader removes it explicitly.
9. **Service worker scope.** `sw.js` is served from the site root and intercepts same-origin GET
   requests only; recitation audio (`.mp3`) is passed straight through to the CDN and never cached.

---

## 11. Hardening checklist before going live

- [ ] `app.environment = production`, `app.debug = false`
- [ ] `app.url` uses `https://` and matches the real domain
- [ ] Database password is unique and long; DB user has only DML privileges
- [ ] `maintenance.sync_token` is set to a long random value (or the cron endpoints left disabled)
- [ ] HTTPS redirect enabled and certificate valid; `/api/health.php` shows `"https":true`
- [ ] `app/`, `bin/`, `database/`, `storage/` are outside the web root (or `.htaccess` deny rules are
      proven to work — request `/app/config/config.php` and expect 403/404)
- [ ] `storage/` is writable by PHP but not browsable
- [ ] `mail.from_email` belongs to your domain; a test reset email arrives
- [ ] Nightly `mysqldump` backup confirmed restorable
- [ ] Log rotation or the weekly maintenance cron is scheduled
- [ ] PHP is 8.1+ and `display_errors` is off in the host's php.ini as well

Verification commands after deployment:

```bash
curl -sI https://your-domain.com/ | grep -Ei 'strict-transport|content-security|x-content|x-frame'
curl -s  https://your-domain.com/api/health.php
curl -sI https://your-domain.com/app/config/config.php   # expect 403 or 404
curl -sI https://your-domain.com/.env                     # expect 403 or 404
```

---

## 12. If something goes wrong

1. **Rotate** the database password, `maintenance.sync_token` and any SMTP credentials.
2. **Invalidate sessions** by changing `session.name` in the config (or deleting the session files);
   then force a password reset for affected accounts with `php bin/user.php reset-password`.
3. **Check** `storage/logs/app-*.log` for `Unhandled`, `Login throttled`, `Cron endpoint called with
   an invalid token` and database errors.
4. **Restore** from the last database backup if data was altered, then review the access log for the
   affected endpoints.
5. **Report** the incident to users if personal data (email, bookmarks) may have been exposed — the
   privacy notice on the About page already states what is stored.

Please do not test attacks against a live site you do not own. For your own deployment, a staging
copy with the same configuration is the right place to probe.
