# Quran Reader — Version 1

A complete, self-hosted Quran website: Arabic reader (Uthmani script), trusted translations,
search, verse-by-verse recitation, bookmarks, reading progress, dark mode and user accounts.

- **Backend:** PHP 8.1+ (no framework, no Composer, no build step)
- **Database:** MySQL 5.7+ / MariaDB 10.2+ (InnoDB, utf8mb4)
- **Frontend:** server-rendered HTML + vanilla CSS/JS (progressive enhancement)
- **Hosting:** standard shared hosting (cPanel, Plesk, DirectAdmin), no Node.js anywhere

---

## Table of contents

1. [What you get](#1-what-you-get)
2. [Requirements](#2-requirements)
3. [Project structure](#3-project-structure)
4. [Quick start (local)](#4-quick-start-local)
5. [Deployment on shared hosting](#5-deployment-on-shared-hosting)
6. [Content import](#6-content-import)
7. [Configuration reference](#7-configuration-reference)
8. [API reference](#8-api-reference)
9. [How the main features work](#9-how-the-main-features-work)
10. [Security summary](#10-security-summary)
11. [Maintenance and backups](#11-maintenance-and-backups)
12. [Troubleshooting](#12-troubleshooting)
13. [Data sources and attribution](#13-data-sources-and-attribution)
14. [Licences](#14-licences)

---

## 1. What you get

| Feature | Status | Notes |
|---|---|---|
| All 114 surahs, Arabic + English names | ✅ | Names, revelation place, verse count, Mushaf pages |
| Uthmani Arabic text, RTL | ✅ | Bundled Amiri Quran webfont, adjustable size |
| Super admin dashboard | ✅ | Owner-only `/admin.php`: footer tagline, copyright holder and year, optional footer line — stored in the database and applied site-wide |
| Quran typeface | ✅ | Amiri Quran (default), Scheherazade New, Noto Naskh Arabic, Noto Nastaliq Urdu — all self-hosted OFL faces, picked in Settings with a preview of each |
| Multiple translations | ✅ | 4 English + 2 Bengali editions imported by default; add more in config |
| Search (Arabic + translation, surah name) | ✅ | Diacritic-insensitive Arabic search, search-as-you-type, matched terms highlighted, header surah picker |
| Audio recitation | ✅ | 7 reciters, verse-by-verse continuous playback, highlight, prev/next |
| User accounts | ✅ | Register, login, logout, password reset by email |
| Bookmarks | ✅ | MySQL for members, `localStorage` for guests, merge on sign-in; grouped per surah as a collapsible tree |
| Highlights + private notes | ✅ | 5 colours, one note per verse, per-account or on-device, listed on the bookmarks page |
| Installable app + offline reading | ✅ | Web app manifest, service worker, one-click download of all 114 surahs (~60 MB) |
| Reading progress | ✅ | "Continue reading" on the home page, synced when signed in |
| Settings | ✅ | Translation, reciter, Arabic font size, theme — editable from the account dashboard and from settings.php |
| Dark / light / system theme | ✅ | No flash on load |
| Juz list + Juz pages | ✅ | 30 juz, computed from imported verse metadata |
| Mushaf page view | ✅ | Flow layout per Madinah Mushaf page, with page navigation |
| Copy / share a verse | ✅ | Clipboard + Web Share API fallback |
| Account page | ✅ | Profile, password change, account deletion |
| Security hardening | ✅ | Prepared statements, CSRF, rate limits, CSP, secure cookies |
| Ads / trackers | ✅ none | Deliberately omitted in V1 |

Not included in V1 (kept as future work): tafsir, word-by-word grammar, social features,
an admin dashboard, offline recitation audio, and fuzzy/stemmed search ranking beyond the
relevance ordering described in §9.

The site is a progressive web app: it installs to the home screen and, after one download, every
surah is readable offline. Needs HTTPS (or `localhost`) for service workers to register; on plain
HTTP the app still works, it just cannot install or cache.

---

## 2. Requirements

**Server**

- PHP **8.1 or newer** with `pdo_mysql`, `mbstring`, `curl`, `openssl`, `json`
- MySQL 5.7+ or MariaDB 10.2+ (utf8mb4 support)
- Apache with `mod_rewrite` optional (plain `.php` URLs are used, so it is not required)
- HTTPS certificate (Let's Encrypt is fine)
- Ability to run a cron job (optional but recommended)

**No** Node.js, Composer, Redis, Memcached, Imagick or shell access is required at runtime.
The only shell commands in this document are optional content-import commands.

**Outbound HTTPS** is needed once, to import Quran content from the public API
(either from the command line or through the token-protected cron endpoint).

---

## 3. Project structure

```
quran-project/
├── public_html/                 ← web root (the only publicly served folder)
│   ├── index.php                home
│   ├── surah.php                reader          (?n=2)
│   ├── search.php               search          (?q=mercy)
│   ├── juz.php                  juz list / juz   (?n=3)
│   ├── page.php                 Mushaf page      (?n=42)
│   ├── bookmarks.php            bookmarks + highlights/notes
│   ├── settings.php             preferences + offline download
│   ├── about.php                about + attribution + privacy
│   ├── account.php              profile / password / delete
│   ├── login.php  register.php  forgot-password.php  reset-password.php  logout.php
│   ├── 404.php
│   ├── offline.php              offline fallback (self-contained, no app code)
│   ├── manifest.webmanifest     web app manifest
│   ├── sw.js                    service worker (shell, pages, API caches)
│   ├── _bootstrap.php           locates and boots the app (blocked by .htaccess)
│   ├── .htaccess                hardening, compression, caching, PWA MIME types
│   ├── api/                     JSON endpoints (see §8)
│   └── assets/                  css, js, fonts, icons
├── app/                         ← application code (must stay outside the web root)
│   ├── bootstrap.php            autoload, config, errors, session, headers
│   ├── autoload.php  helpers.php
│   ├── config/
│   │   ├── config.example.php   template to copy
│   │   └── config.php           your credentials (git-ignored, never committed)
│   ├── src/
│   │   ├── Auth/                session authentication, password reset
│   │   ├── Http/                API dispatcher, request/response, controllers
│   │   ├── Quran/               content API client + importer
│   │   ├── Repositories/        all SQL (prepared statements)
│   │   ├── Support/             Config, Database, Csrf, RateLimiter, Mailer, Text…
│   │   └── Web/                 server-side page rendering
│   └── views/                   PHP templates (layout, pages, emails)
├── bin/
│   ├── import-quran.php         content importer / refresher
│   └── user.php                 account utility (create, reset link, stats)
├── database/schema.sql          full MySQL schema
├── storage/logs/                application + mail logs (web access denied)
├── .env.example                 environment template
├── .gitignore
├── README.md  SECURITY.md  DEPLOYMENT.md
```

Only `public_html/` is exposed. `app/`, `bin/`, `database/` and `storage/` sit one level above it,
and each of those folders also contains an `.htaccess` deny-all as a second line of defence.

---

## Super admin

The owner account reaches `admin.php` (footer + copyright). Two ways to nominate it:

```bash
php bin/user.php promote owner@example.com   # sets the is_admin flag
php bin/user.php demote  owner@example.com   # takes it away
```

or set `APP_ADMIN_EMAIL=owner@example.com` in `.env` — that account is treated as
owner even before the flag exists, which is handy on shared hosting where you
only have FTP. Everyone else gets a 403, and the admin link never appears in
their footer.

## 4. Quick start (local)

Tested with WAMP/XAMPP on Windows and with `php -S` on Linux/macOS.

```bash
# 1. Create the database and tables
mysql -u root -p -e "source database/schema.sql"
# 2. Create your configuration — .env is the only file you need to edit
cp .env.example .env
#    → set DB_DATABASE/DB_USERNAME/DB_PASSWORD and APP_URL=http://localhost:8080

# 4. Import the Quran content (3–8 minutes, needs outbound HTTPS)
php bin/import-quran.php

# 5. Serve it
php -S localhost:8080 -t public_html
```

Upgrading an existing database instead of creating one? Apply only what is missing:

```bash
php bin/migrate.php          # adds new columns, idempotent, safe to re-run
php bin/migrate.php status   # report only, changes nothing
```

### Automated checks (optional, development only)

`qa/` holds the test harness used to validate a build. It is not needed in
production — never upload it, and it is blocked by its own `.htaccess` if you do.

```bash
php qa/schema-test.php     # imports database/schema.sql into a throwaway DB
php qa/run-tests.php       # 110 end-to-end checks: pages, search, auth, bookmarks, limits
```

`run-tests.php` drives the real front controllers through `php-cgi`, so it needs a
working `app/config/config.php` and an imported database. It creates and deletes its
own test accounts.

Open <http://localhost:8080>. Check `/api/health.php` — it should report
`"status":"ok"` and `"content_ready":true`.

On WAMP/XAMPP, put the project in `www/` (or `htdocs/`) and open
`http://localhost/quran-project/public_html/`. The app detects the sub-directory automatically.

**First run checklist**

1. `php bin/user.php create you@example.com "your-password" "Your Name"` creates an account.
2. Register through the UI and confirm bookmarks and progress persist after a reload.
3. Confirm `/api/health.php` shows `content_ready: true`.
4. Before going live, set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.

---

## 5. Deployment on shared hosting

The short version; a cPanel-specific walkthrough with screenshots-in-words is in
[DEPLOYMENT.md](DEPLOYMENT.md).

### 5.1 Create the database

1. cPanel → **MySQL® Databases** → create a database, e.g. `cpuser_quran`.
2. Create a user with a strong password and **add it to the database** with *All Privileges*.
3. Note the full names — cPanel prefixes them (`cpuser_quran`, `cpuser_quranuser`).

### 5.2 Import the schema

- cPanel → **phpMyAdmin** → select the database → **Import** → choose `database/schema.sql`.
- If the file's `CREATE DATABASE`/`USE` lines conflict with the cPanel database name,
  delete those two statements before importing (the database is already selected).
- Verify 15 tables exist, including `users`, `verses`, `verse_translations`.

### 5.3 Upload the files

Recommended layout — application code outside the web root:

```
/home/cpuser/
├── public_html/           ← contents of the project's public_html/
├── app/                   ← the project's app/ folder
├── bin/
├── database/
└── storage/
```

Upload `public_html/*` into `public_html/`, then upload `app/`, `bin/`, `database/` and `storage/`
as siblings of `public_html`. File Manager or SFTP both work.

> If your host only lets you write inside `public_html`, upload the whole project into it.
> `.htaccess` files (in the project root and in each internal folder) block web access to
> `app/`, `bin/`, `database/`, `storage/`, `.env` and `_bootstrap.php`. The recommended layout is
> still safer, because a misconfigured server cannot serve files that are not under the web root.

Set permissions: directories `755`, files `644`. `storage/` must be writable by PHP (`755` is
enough on most hosts; use `775` if the logs stay empty).

### 5.4 Configure

Create `.env` in the project root from the template and edit it — that is the whole configuration:

```bash
cp .env.example .env
```

```ini
APP_NAME="Quran Reader"
APP_URL="https://your-domain.com"
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_DATABASE=cpuser_quran
DB_USERNAME=cpuser_quranuser
DB_PASSWORD="your-database-password"

MAIL_TRANSPORT=mail
MAIL_FROM="no-reply@your-domain.com"

SYNC_TOKEN=a-long-random-string
```

`app/config/config.php` is **optional** — keep it only if you prefer PHP over `.env` or need one-off
overrides. Precedence, later wins: `app/config/defaults.php` → `app/config/config.php` → `.env` →
real environment variables.

### 5.5 Import the content

Pick whichever your host allows:

**A. SSH / terminal (best)**

```bash
cd /home/cpuser
php bin/import-quran.php
```

**B. cPanel → Cron Jobs, one-off run**

Set the command to `php /home/cpuser/bin/import-quran.php` and run it once
("Run now" if your panel offers it).

**C. Web request (no shell)**

```bash
curl -X POST "https://your-domain.com/api/cron/sync.php?token=YOUR_SYNC_TOKEN"
```

This runs the same importer, protected by the token, with a cooldown
(`maintenance.sync_cooldown_hours`, default 24h). Keep the request open until it finishes —
it can take several minutes. If your host kills long requests, use option A or B.

### 5.6 Point the domain and enable HTTPS

1. Add the domain/subdomain in cPanel and point the document root at `public_html`.
2. Issue a certificate (cPanel → **SSL/TLS Status** → *Run AutoSSL*, or Let's Encrypt).
3. Force HTTPS — either enable *Force HTTPS Redirect* in the panel, or uncomment the redirect
   block at the top of `public_html/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
```

4. Reload the site. `http://` must redirect to `https://`; the session cookie is only marked
   `Secure` on HTTPS, so this matters for security.

### 5.7 Verify

- `https://your-domain.com/api/health.php` → `"status":"ok"`, `"content_ready":true`,
  `"debug_mode":false`, `"https":true`.
- Home page shows 114 surahs; open Al-Baqarah, switch translation, adjust font size.
- Search “mercy” and “الرحمن”.
- Play a surah; verse highlighting and prev/next work while scrolling.
- Register an account, bookmark a verse, reload, confirm `Continue reading`.
- Request a password reset; confirm the email arrives (see §7 for mail settings).
- Highlight a verse, add a note, reload: both persist. Check the bookmarks page lists them.
- Settings → “Download all surahs”; then switch the browser offline and open a surah you had not
  visited. The page must render, and an uncached page must show the “You are offline” fallback.
- Test on a phone: nothing should overflow horizontally.

### 5.8 Schedule maintenance (optional)

| Purpose | Command | Suggested schedule |
|---|---|---|
| Refresh Quran content | `php /home/cpuser/bin/import-quran.php` | monthly |
| Prune tokens/logs (web) | `curl -s -X POST "https://your-domain.com/api/cron/maintenance.php?token=…"` | weekly |
| Database backup | `mysqldump -u USER -p'PASS' DB > /home/cpuser/backup-$(date +\%F).sql` | daily |

Cron entries need absolute paths. Quote paths that contain spaces.

---

## 6. Content import

The Quran text is **not** stored in the repository. It is fetched from the public
[Al Quran Cloud API](https://alquran.cloud/api) and cached in MySQL, so:

- the site serves readers, search and juz pages from the database (fast, no external calls),
- the API is contacted only by the importer,
- no Quran text has to be hardcoded or committed anywhere.

`bin/import-quran.php` is idempotent — re-run it any time.

```bash
php bin/import-quran.php                    # everything
php bin/import-quran.php --surah=2,36       # a subset (fast smoke test)
php bin/import-quran.php --skip-chapters    # keep existing chapter metadata
php bin/import-quran.php --translations=en.sahih,en.yusufali
```

What it writes:

| Table | Content |
|---|---|
| `chapters` | 114 rows: Arabic/Latin names, revelation place and order, verse count, page range |
| `verses` | 6,236 rows: Uthmani text, global ayah number (used for audio), juz, hizb, page, sajda flag |
| `translations` | one row per edition with author, language and direction |
| `verse_translations` | one row per verse per edition |
| `reciters` | audio CDN base per reciter, from `content.reciters` in the config |
| `juzs` | 30 rows derived from verse metadata |

The importer also strips the duplicated basmala prefix that some editions repeat inside verse 1
and stores the flag in `chapters.bismillah_pre`, so the reader shows the basmala once, as a header.

The bundled set is `en.sahih`, `en.pickthall`, `en.yusufali`, `en.hilali`, `bn.bengali` (Muhiuddin Khan) and `bn.hoque` (Zohurul Hoque). Non-English editions are first-class: the reader emits the edition's `lang` and `dir` on the translation paragraph, the picker shows the edition name and language, and search indexes whatever script the edition uses.

**Adding a translation:** add its identifier from
`https://api.alquran.cloud/v1/edition?format=text&type=translation` to `content.translations`, then
re-run the importer. Machine translation is never used.

**Adding a reciter:** add an entry to `content.reciters`. Audio files are streamed per verse from
`<content.audio_base>/<bitrate>/<reciter-id>/<global-ayah>.mp3`. Verify a URL before listing a new
reciter: some editions are only published at one bitrate.

---

## 7. Configuration reference

Configuration is an array merged from `app/config/defaults.php`, the optional
`app/config/config.php`, `.env` and finally real environment variables. In practice you only edit
`.env`; every key below has an environment name (`DB_*`, `APP_*`, `SESSION_*`, `SECURITY_*`,
`QURAN_API_*`, `CONTENT_*`, `MAIL_*`, `SMTP_*`, `SYNC_*`). Lists use comma-separated values, e.g.
`CONTENT_TRANSLATIONS=en.sahih,bn.bengali`.

| Key | Default | Meaning |
|---|---|---|
| `app.name` | `Quran Reader` | Shown in the header, title and emails |
| `app.url` | `https://example.com` | Public base URL, used for canonical links and emails |
| `app.environment` | `production` | `local` enables verbose errors |
| `app.debug` | `false` | Must be `false` on a public site |
| `app.timezone` | `UTC` | PHP timezone; database times are stored in UTC |
| `database.*` | — | PDO connection settings |
| `session.lifetime` | 30 days | Cookie lifetime |
| `session.idle_timeout` | 3 hours | Inactivity logout |
| `session.cookie_secure` | `auto` | `Secure` flag whenever HTTPS is detected |
| `session.same_site` | `Lax` | `None` requires HTTPS |
| `security.cors_allowed_origins` | `[]` | Only set when a different-domain frontend calls the API |
| `security.trust_proxy_headers` | `false` | Set `true` behind a trusted reverse proxy |
| `quran_api.base_url` | Al Quran Cloud | Content API base URL |
| `quran_api.cainfo` | `''` | Path to `cacert.pem` when TLS verification fails |
| `content.translations` | 4 English + 2 Bengali | Editions to import and offer |
| `content.default_translation` | `en.sahih` | Default selection for new visitors |
| `content.reciters` | 7 reciters | Player options |
| `mail.transport` | `mail` | `mail`, `smtp` or `log` |
| `mail.from_email` / `from_name` | — | Envelope sender; use a domain you control |
| `mail.smtp.*` | — | Host, port, user, password, `tls`/`ssl`/`none` |
| `maintenance.sync_token` | `''` | Enables `/api/cron/*` when non-empty |
| `maintenance.sync_cooldown_hours` | 24 | Minimum gap between web-triggered syncs |

**Mail:** `mail()` works on most shared hosts — make sure `mail.from_email` uses your domain,
otherwise messages are marked as spam. If `mail()` is blocked, switch to SMTP:

```php
'mail' => [
    'transport' => 'smtp',
    'from_email' => 'no-reply@your-domain.com',
    'from_name' => 'Quran Reader',
    'smtp' => ['host' => 'smtp.your-domain.com', 'port' => 587, 'username' => '…', 'password' => '…', 'encryption' => 'tls'],
],
```

During local development use `'transport' => 'log'`; messages are written to `storage/logs/mail.log`,
which is how you copy a password-reset link without an SMTP server.

---

## 8. API reference

All endpoints return JSON:

```json
{ "ok": true, "data": { } }
{ "ok": false, "error": { "code": "validation_error", "message": "…", "details": { "fields": { } } } }
```

Authentication uses the PHP session cookie. State-changing requests (`POST`) must send the CSRF
token from `<meta name="csrf-token">` in either the `X-CSRF-Token` header or the `_csrf` field.
Request bodies may be JSON (`Content-Type: application/json`) or form-encoded.

### Authentication

| Method | Endpoint | Body | Notes |
|---|---|---|---|
| POST | `/api/auth/register.php` | `email`, `password`, `password_confirmation`, `full_name?` | Signs the user in, 5/hour per IP |
| POST | `/api/auth/login.php` | `email`, `password` | 6 attempts / 15 min per email+IP, 25 per IP |
| POST | `/api/auth/logout.php` | — | Rotates session id and CSRF token |
| POST | `/api/auth/forgot-password.php` | `email` | Always the same response; 3/15 min per email |
| POST | `/api/auth/reset-password.php` | `token`, `password`, `password_confirmation` | Single use, 60-minute expiry |
| GET | `/api/auth/me.php` | — | Current user, settings, progress, bookmark count |

### Bookmarks (auth required)

| Method | Endpoint | Body / query |
|---|---|---|
| GET | `/api/bookmarks/list.php` | `?translation_id=en.sahih` |
| POST | `/api/bookmarks/add.php` | `surah`, `verse` |
| POST | `/api/bookmarks/remove.php` | `id` **or** `surah` + `verse` |
| POST | `/api/bookmarks/import.php` | `items: [{surah, verse}, …]` (max 500) |

### Highlights and notes (auth required)

| Method | Endpoint | Body / query |
|---|---|---|
| GET | `/api/marks/list.php` | `?translation_id=en.sahih` → one entry per verse, plus totals |
| POST | `/api/marks/save.php` | `surah`, `verse`, and `color` and/or `note` (partial update; `""` clears) |
| POST | `/api/marks/remove.php` | `surah`, `verse`, `scope` = `all` (default), `color` or `note` |
| POST | `/api/marks/import.php` | `items: [{surah, verse, color?, note?}, …]` (max 500) |

Colours are `yellow`, `green`, `blue`, `pink`, `purple`. Limits: 5000 highlights, 2000 notes,
1000 characters per note. `save` returns the verse's resulting `mark` (or `null` when everything
was cleared).

### Progress and settings (auth required)

| Method | Endpoint | Body |
|---|---|---|
| GET | `/api/progress/get.php` | — |
| POST | `/api/progress/save.php` | `surah`, `verse` |
| GET | `/api/settings/get.php` | — |
| POST | `/api/settings/save.php` | any of `translation_id`, `reciter_id`, `arabic_font_size`, `theme` |

### Account (auth required)

| Method | Endpoint | Body |
|---|---|---|
| POST | `/api/account/update.php` | `full_name` |
| POST | `/api/account/password.php` | `current_password`, `password`, `password_confirmation` |
| POST | `/api/account/delete.php` | `password` |

### Public content

| Method | Endpoint | Query |
|---|---|---|
| GET | `/api/quran/search.php` | `q`, `translation_id?`, `surah?`, `juz?`, `page?`, `limit?` (≤30), `offset?` |
| GET | `/api/quran/chapters.php` | — (114 chapters, used by the quick-jump UI) |
| GET | `/api/health.php` | — (deployment smoke test) |

Search results carry `arabic`, `translation`, `preview` (plain text) plus `arabic_html` and
`preview_html` — the same strings with every match wrapped in `<mark>` and all text escaped. Use the
HTML fields when rendering results with `innerHTML`; use the plain fields otherwise.

### Cron (token required)

| Method | Endpoint | Notes |
|---|---|---|
| POST | `/api/cron/sync.php?token=…` | Imports/refreshes content, respects the cooldown |
| POST | `/api/cron/maintenance.php?token=…` | Prunes rate limits, expired reset tokens, old sync runs |

### Example

```bash
# Search in English
curl -s "https://your-domain.com/api/quran/search.php?q=mercy&limit=3" | head -c 400

# Register (store cookies, reuse the CSRF token from the page)
curl -s -c jar.txt -b jar.txt -X POST \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $TOKEN" \
  -d '{"email":"test@example.com","password":"a-long-password","password_confirmation":"a-long-password"}' \
  "https://your-domain.com/api/auth/register.php"
```

---

## 9. How the main features work

**Reader.** `surah.php?n=2` renders the whole surah on the server: verses, translation, verse
numbers, bookmark state and the audio URL for the selected reciter. No JavaScript is required to
read. `reader.js` adds the player, bookmark toggles, copy/share and progress tracking.

**Theme and font size.** `theme.js` runs in `<head>` and applies the stored preference before first
paint, so there is no flash. Preferences are stored in `localStorage` for guests and in
`user_settings` for members.

**Guest data.** Bookmarks live in `localStorage` under `quran.bookmarks`, reading position under
`quran.progress`. After signing in, a banner offers to import them; the import endpoint validates
every surah/verse pair against `verses` before inserting, then the local copy is cleared.

**Search.** Both the Arabic text and the selected translation are stored twice: exactly as
published, and in a normalised form (`verses.text_plain`, `verse_translations.text_plain`) with
diacritics, tatweel and alef/ya/ta-marbuta variants folded. The query is normalised the same way and
matched with `LIKE`. 6,236 verses is small enough that this stays fast without a search server; the
trade-off is that only substring matches are found (no fuzzy ranking). Surah names are matched in
both scripts.

Results are ordered by relevance — exact translation match, then verses whose translation starts
with the term, then the highest number of occurrences, then canonical surah/verse order so paging
stays stable. Matched terms are highlighted with `<mark>`: `Text::highlight()` folds Arabic per
character and maps the match back onto the voweled text, so searching `الرحمن` highlights
`ٱلرَّحْمَٰنِ` without losing its diacritics. The same pre-escaped fragments (`arabic_html`,
`preview_html`) ship in the search API, so the live re-render keeps identical highlighting.

The home-page box also offers search-as-you-type (`assets/js/typeahead.js`): an ARIA combobox that
queries the same endpoint and lists surah and verse suggestions, navigable with the arrow keys.

**Audio.** Every verse has a stable global ayah number (1–6236) stored in `verses.id`, which maps
directly to the CDN file `<audio_base>/<bitrate>/<reciter>/<number>.mp3`. One `<audio>` element per
page handles the whole surah, so playback continues while scrolling; “Play surah” simply queues
verse 1 and advances on `ended`.

**Reading progress.** While scrolling, the last verse above 42% of the viewport is recorded
(at most every 4 seconds, plus a `sendBeacon` flush when the tab is hidden). Members write to
`reading_progress`, guests to `localStorage`; the home page “Continue reading” button reflects it.

**Mushaf page view.** Verses are grouped by the `page` column of the imported edition and rendered
as continuous justified Arabic text with surah dividers and Arabic-Indic verse markers.

**Highlights and notes.** Each verse can carry one colour and one private note. The colour is
written to `data-highlight` on the verse, so highlighting works before any JavaScript runs and is
themed by CSS only; notes are rendered into the page for members, and hydrated from
`localStorage` (`quran.marks`) for guests. `assets/js/marks.js` owns the colour popover and the
inline note editor, and every change is a partial update (`color` and/or `note`) so the client never
has to read-modify-write. Writes made while offline are stored locally and offered for import by
the guest banner on the next online visit. The bookmarks page groups everything by surah — one collapsible node per surah with a count, verses inside it newest first — so 36:5 and 36:2 live under a single "Ya-Sin" node.

**Offline reading.** `sw.js` caches the app shell at install time and serves pages network-first
with a cache fallback; `offline.php` is the fallback when a page was never cached. Settings offers
“Download all surahs”, which fetches the 114 rendered surah pages (with the current translation)
into the pages cache, ~60 MB, resumable and cancellable. Assets carry `?v=<filemtime>`, so the
worker falls back to an `ignoreSearch` lookup — that is what lets the precached shell load under a
versioned URL. Everything user-specific (bookmarks, notes, reading position) is in the same cached
HTML, so the offline copy is private to that browser: “Remove download” clears it.

---

## 10. Security summary

Full detail, threat model and review checklist: [SECURITY.md](SECURITY.md).

- **Passwords:** `password_hash()` with PHP's default algorithm (bcrypt or Argon2id), verified with
  `password_verify()`; automatic rehash when the default changes. Never logged, never emailed.
- **Sessions:** native PHP sessions, `HttpOnly` + `SameSite=Lax` cookies, `Secure` under HTTPS,
  strict mode, id rotation every 15 minutes and on login/logout, 3-hour idle timeout.
- **SQL:** every query is a prepared statement through one PDO wrapper with emulation disabled.
  `LIMIT`/`OFFSET` are cast to integers before interpolation.
- **CSRF:** per-session token required on every POST (header or form field), plus an Origin/Referer
  same-host check.
- **Rate limits:** login, registration, password reset, search and write endpoints, stored in MySQL,
  keyed by a SHA-256 hash of the identifier instead of the raw email/IP.
- **Enumeration:** login, forgot-password and registration return generic messages and constant-time
  work.
- **Headers:** strict CSP (no inline scripts, no third-party scripts), `nosniff`, `SAMEORIGIN`,
  `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS.
- **Output:** every dynamic value is escaped with `e()`; no `eval`, no dynamic SQL, no user-supplied
  file paths.
- **Config:** credentials live in `app/config/config.php` or `.env` outside the web root, with
  `.htaccess` deny rules protecting both if the project is uploaded inside `public_html`.

---

## 11. Maintenance and backups

- **Backups:** `mysqldump` nightly plus a copy of `public_html/assets/fonts` (everything else is
  reproducible from this repository and the content importer).
- **Content refresh:** re-run the importer monthly; it only upserts.
- **Logs:** `storage/logs/app-YYYY-MM-DD.log` (rotated at 5 MB) and `php-error.log`. Nothing
  sensitive is written: passwords and tokens are redacted by the logger.
- **Cleanup:** `POST /api/cron/maintenance.php?token=…` prunes rate-limit rows older than a week,
  expired reset tokens and sync logs older than 90 days.
- **Upgrades:** PHP 8.1 → 8.3/8.4 is supported. If you upgrade PHP, re-check `/api/health.php` and
  the login flow (hash rehashing happens automatically).
- **Service worker:** after changing the shell (CSS/JS file list, cache strategy), bump
  `CACHE_VERSION` in `public_html/sw.js`. Old caches are dropped on activation and readers are
  asked to reload; their offline pack is re-downloaded on demand. `qa/make-icons.php` re-renders
  the PNG icons from scratch if the artwork changes.

---

## 12. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| “Application files not found” | `app/` is not next to `public_html/`. Upload it, or set `QURAN_APP_DIR`. |
| “Missing configuration file” | Restore `app/config/defaults.php`, then create `.env` from `.env.example`. |
| “Could not connect to the database” | Wrong credentials, or the DB user is not attached to the database. |
| Surah page says “Content is not ready yet” | Import did not run or failed. Run `php bin/import-quran.php` and read the errors. |
| Import fails with a TLS error | Set `quran_api.cainfo` to a `cacert.pem` path (or `CURL_CA_BUNDLE`). |
| Import stops partway | Some hosts limit CLI runtime. Re-run with `--surah=` batches; the importer is idempotent. |
| 500 on every page | Check `storage/logs/php-error.log`. Usually a permission problem on `storage/` or a PHP version below 8.1. |
| Password reset email never arrives | Set `mail.from_email` to your domain, check spam, or configure SMTP. Use `php bin/user.php reset-link` as a fallback. |
| Audio does not play | The CDN may be blocked on your network; check the browser console for the `.mp3` request. |
| Arabic shows boxes | The bundled Amiri Quran webfont failed to load; confirm `public_html/assets/fonts/*.woff2` exists. |
| `debug_mode: true` in health check | Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`. |

---

## 13. Data sources and attribution

- **Quran text, translations, page and juz metadata:** [Al Quran Cloud API](https://alquran.cloud/api),
  Uthmani edition. Imported once and cached in MySQL.
- **Translations:** Saheeh International, Pickthall, Yusuf Ali, Hilali & Khan (English). Attribution
  is shown in the reader, on every search result and on the About page. No machine translation is
  used anywhere.
- **Recitations:** streamed from the [Islamic Network](https://islamic.network) CDN
  (`cdn.islamic.network`), 128 kbps MP3 per verse.
- **Optional enrichment:** the importer reads chapter metadata (revelation order, bismillah flag,
  page ranges) from the public Quran.com v4 endpoint when it is reachable. Failure is non-fatal.

Please keep the attribution blocks intact — they are a condition of using these public services,
and removing them would also misrepresent the source of the text.

---

## 14. Licences

- **Project code:** yours to use and modify for this project.
- **Amiri / Amiri Quran fonts:** SIL Open Font License 1.1 —
  see `public_html/assets/fonts/OFL-Amiri.txt`. Bundled locally so the site needs no external font
  CDN.
- **Quran text and translations:** belong to their publishers; consult the API provider's terms
  before redistributing them outside this website.
"# QuranReader" 
