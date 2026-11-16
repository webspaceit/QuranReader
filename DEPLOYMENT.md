# Deployment walkthrough (cPanel shared hosting)

This is the step-by-step version for a typical cPanel host (GoDaddy, Hostinger, Namecheap,
HostGator, cPanel/DirectAdmin resellers). It assumes no SSH access is required, and marks the
optional SSH steps. Budget 30–45 minutes.

If your host uses Plesk or DirectAdmin the screens differ but the sequence is identical:
database → import SQL → upload files → configure → import content → HTTPS → test.

---

## 0. What you need

- Domain or subdomain already pointing at the hosting account
- cPanel login
- The project archive (`quran-project.zip`) or the folder itself
- PHP 8.1+ selected for the domain (cPanel → **Select PHP Version** / **MultiPHP Manager**)
- 100 MB of disk space (the database is ~35 MB with four translations)

Check the PHP version first: cPanel → **Select PHP Version**. If it offers 8.2 or 8.3, choose it.
Enable the extensions `pdo_mysql`, `mbstring`, `curl`, `openssl`, `json` (they are on by default on
almost every host — if not, tick them).

---

## 1. Create the database

1. cPanel → **MySQL® Databases**.
2. *Create New Database*: `quran` → cPanel creates `cpuser_quran`.
3. *MySQL Users* → *Add New User*: `quranuser` + a long password (use the generator; copy it).
4. *Add User To Database* → select the user and the database → **All Privileges** → Make Changes.

Write down the three values:

| Setting | Example |
|---|---|
| Database | `cpuser_quran` |
| User | `cpuser_quranuser` |
| Password | `…` |
| Host | `localhost` (some hosts use an IP or `mysql.yourhost.com` — check the panel) |

---

## 2. Import the schema

1. cPanel → **phpMyAdmin**.
2. Left sidebar → select `cpuser_quran`.
3. **Import** tab → *Choose File* → `database/schema.sql`.
4. If phpMyAdmin refuses because the file contains `CREATE DATABASE`, open the file in a text editor
   and delete these two statements (the database already exists and is selected). Then import again.
5. Confirm 15 tables: `bookmarks, chapters, highlights, juzs, notes, password_resets, rate_limits,
   reading_progress, reciters, sync_runs, translations, user_settings, users, verse_translations,
   verses`.

Character set must be `utf8mb4` and collation `utf8mb4_unicode_ci` for every table — the SQL file sets
this explicitly.

---

## 3. Upload the files

### Recommended layout (application outside the web root)

Create these folders in your account home (`/home/cpuser/`, shown as *Home* in File Manager):

```
/home/cpuser/app/
/home/cpuser/bin/
/home/cpuser/database/
/home/cpuser/storage/
/home/cpuser/storage/logs/
/home/cpuser/storage/cache/
```

Upload:

- the **contents of** `public_html/` → `/home/cpuser/public_html/`
  (so `/home/cpuser/public_html/index.php` exists — do not create a nested folder)
- the whole `app/` folder → `/home/cpuser/app/`
- `bin/`, `database/` → `/home/cpuser/`
- `README.md`, `SECURITY.md`, `.env.example` → `/home/cpuser/` (optional; you will create `.env` there)

Using File Manager: upload the zip, then *Extract*, then move folders into place. Using SFTP is
faster and preserves permissions.

### Alternative: everything inside public_html

If you cannot write outside `public_html`, upload the entire project into `public_html/` so that
`public_html/index.php` and `public_html/app/` sit together. The `.htaccess` in the project and in
each internal folder blocks web access to `app/`, `bin/`, `database/`, `storage/`, `.env` and
`_bootstrap.php`. **Verify** after upload:

```
https://your-domain.com/app/config/config.php   → 403 or 404, never the file contents
https://your-domain.com/.env                    → 403 or 404
```

---

## 4. Permissions

In File Manager, select the folders and use *Permissions*:

| Path | Mode |
|---|---|
| `public_html/` and files inside | 755 / 644 |
| `app/`, `bin/`, `database/` | 755, files 644 |
| `storage/`, `storage/logs/`, `storage/cache/` | 755 (use 775 if logs stay empty) |
| `.env` | 600 if your host allows it (it holds the DB password) |

Do not use 777. If logs cannot be written at 755, ask the host which group PHP runs as, or use 775.

---

## 5. Configure

1. In `/home/cpuser/`, copy `.env.example` to `.env` (File Manager: rename a copy, or
   `cp .env.example .env` over SSH).
2. Edit `.env` — this is the only file that needs your details:

```ini
APP_NAME="Your Site Name"
APP_URL="https://your-domain.com"
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_DATABASE=cpuser_quran
DB_USERNAME=cpuser_quranuser
DB_PASSWORD="the-password-from-step-1"

MAIL_TRANSPORT=mail
MAIL_FROM="no-reply@your-domain.com"
MAIL_FROM_NAME="Your Site Name"

SYNC_TOKEN=paste-a-random-48-character-string-here
SYNC_COOLDOWN_HOURS=24
```

Generate the token locally:

```bash
php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
```

Or use any password generator in the panel — 48+ random characters.

3. `app/config/config.php` is optional. Leave it out entirely; `app/config/defaults.php` supplies the
   structure and `.env` overrides it. Real environment variables (host panel, Docker, Passenger)
   override `.env`, which suits staging/production pairs.

---

## 6. Import the Quran content

The site is empty until this runs.

### Option A — cPanel Terminal / SSH (recommended)

cPanel → **Terminal** (or SSH):

```bash
cd /home/cpuser
php bin/import-quran.php
```

Expected output ends with:

```
Summary
-------
Chapters:     114
Verses:       6236
Translations: 24944 rows
Juz:          30
Reciters:     7
Done.
```

It takes 3–8 minutes. If your host kills long CLI jobs, run it in batches:

```bash
php bin/import-quran.php --surah=1,2,3
php bin/import-quran.php --surah=4,5,6
# … the script is idempotent, repeat or resume as needed
php bin/import-quran.php --skip-chapters   # finish any remaining surahs
```

### Option B — Cron job, run once

cPanel → **Cron Jobs** → *Add New Cron Job*:

- Common settings: *Once per day* (temporary)
- Command: `php /home/cpuser/bin/import-quran.php`

Save, wait for the next minute, then check `https://your-domain.com/api/health.php` for
`"content_ready":true`. Delete the cron entry afterwards, or change it to monthly.

### Option C — Web request (no shell at all)

```bash
curl -X POST "https://your-domain.com/api/cron/sync.php?token=YOUR_SYNC_TOKEN"
```

Keep the connection open; the response arrives when the import finishes. If the host times out PHP
requests after 30–60 seconds, this option will not complete — use B, or ask the host to allow a
longer `max_execution_time` for CLI.

### Verify

Open `https://your-domain.com/api/health.php`. You want:

```json
{ "ok": true, "data": { "status": "ok", "content_ready": true, "chapters": 114, "verses": 6236 } }
```

---

## 7. HTTPS and the domain

1. cPanel → **Domains**: confirm the domain's document root is `public_html`.
2. cPanel → **SSL/TLS Status** → *Run AutoSSL* (or **Let's Encrypt SSL** → Issue).
3. Wait for the certificate to appear, then enable **Force HTTPS Redirect** if the panel offers it.
   Otherwise edit `public_html/.htaccess` and add at the top:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteCond %{HTTP:X-Forwarded-Proto} !https
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
```

4. Reload the site — the padlock must appear and `http://` must redirect.

If the certificate is issued but the browser still warns, wait 10–15 minutes for DNS/propagation, then
clear the cache.

---

## 8. Post-deployment test checklist

Run through this on a phone **and** a desktop browser.

**Content**
- [ ] Home page lists 114 surahs and 30 juz; no layout overflow on a 360 px-wide screen
- [ ] `surah.php?n=1` shows 7 verses with the basmala as verse 1
- [ ] `surah.php?n=2` shows the basmala once as a header, then “Alif, Lam, Meem.”
- [ ] `surah.php?n=9` shows no basmala header
- [ ] Switching translation changes the text and the attribution line
- [ ] Font size buttons A−/A/A+/A++ change the Arabic size and survive a reload
- [ ] Dark mode toggles and survives a reload
- [ ] `page.php?n=1` and `page.php?n=604` render; next/previous work
- [ ] `juz.php` lists 30 juz; opening one shows verses grouped by surah

**Search**
- [ ] “mercy” returns English matches with surah name, verse number and preview
- [ ] “الرحمن” returns Arabic matches (diacritics ignored)
- [ ] Typing in the search box updates results without a page reload
- [ ] Filters (surah, juz) narrow the results

**Audio**
- [ ] “Play surah” starts reciting and highlights verse 1
- [ ] The current verse scrolls into view and follows along
- [ ] Previous/next verse buttons and the reciter selector work
- [ ] Audio keeps playing while scrolling; pause/resume works
- [ ] Playing a verse from the middle continues to the end of the surah

**Accounts**
- [ ] Register → redirected to the account page, header shows the name
- [ ] Log out → log in again
- [ ] Bookmark a verse; reload; the icon is filled and the header count increased
- [ ] `bookmarks.php` lists it; removing it works
- [ ] Bookmark as a guest, then sign in → the “Import” banner appears and imports them
- [ ] “Continue reading” on the home page points at the last verse read
- [ ] Forgot password → email arrives → link opens the reset page → new password works
- [ ] Change password from the account page, then sign in with the new one
- [ ] Delete the account → data is gone and the session ends

**Security spot checks**
- [ ] `https://your-domain.com/app/config/config.php` → 403/404
- [ ] `https://your-domain.com/.env` → 403/404
- [ ] `https://your-domain.com/api/bookmarks/list.php` while signed out → `{"ok":false,...}` 401
- [ ] Six wrong logins in a row → “Too many attempts”
- [ ] `/api/health.php` shows `"debug_mode":false` and `"https":true`

---

## 9. Scheduled jobs

cPanel → **Cron Jobs**:

| When | Command |
|---|---|
| Monthly, 03:15 | `php /home/cpuser/bin/import-quran.php >> /home/cpuser/storage/logs/import.log 2>&1` |
| Weekly, Sunday 04:00 | `curl -s -X POST "https://your-domain.com/api/cron/maintenance.php?token=YOUR_TOKEN" > /dev/null` |
| Daily, 02:30 | `mysqldump -u cpuser_quranuser -p'PASSWORD' cpuser_quran > /home/cpuser/backups/quran-$(date +\%F).sql` |

Create `/home/cpuser/backups/` first. Escape `%` as `\%` in crontab. Download a backup occasionally
and confirm it restores into a local database — an untested backup is not a backup.

---

## 10. Updating the site later

1. Upload changed files, keeping `.env` and `storage/` untouched.
2. If `database/schema.sql` changed, apply only the new statements (compare tables) — never
   re-import the whole file over live data.
3. Content refresh: run the importer (see §9). It only upserts.
4. If you change `.env`, no cache needs clearing; PHP reads it per request.
5. After an update, re-run the checklist in §8 at least for the reader, login and bookmarks.

---

## 11. Rollback

1. Restore the previous `public_html/` and `app/` from your local copy or host backup.
2. Restore the database from the last `mysqldump` (`mysql -u user -p db < backup.sql`).
3. If the rollback includes a config change, re-check `/api/health.php`.

Keep a copy of the working `.env` outside the site; it is the one file that cannot be regenerated
from the repository.

---

## 12. Host-specific notes

**LiteSpeed / CloudLinux (most cPanel hosts).** `.htaccess` directives used here are supported.
If pages return 500 immediately after upload, temporarily rename `public_html/.htaccess` to see
whether a directive is blocked, then re-enable it section by section.

**Hostinger hPanel.** PHP version and extensions are under *Advanced → PHP Configuration*; the
document root is `public_html`. The Terminal is under *Advanced → SSH Access*.

**GoDaddy cPanel.** Uses the same steps; AutoSSL is under *Security → SSL/TLS Status*. Their
`php` CLI may be version-specific — if `php -v` reports an old version, use
`/usr/local/bin/php8.2 bin/import-quran.php` style paths.

**No outbound HTTPS from PHP.** Some locked-down hosts block outbound connections. The importer will
fail with a curl error; the reader will still work, but you must import content elsewhere: run the
importer on a local machine against a copy of the database, then export/import the `chapters`,
`verses`, `translations`, `verse_translations`, `juzs` and `reciters` tables.

**Very low `max_execution_time`.** Prefer the CLI importer; the web cron endpoint will be cut off.
