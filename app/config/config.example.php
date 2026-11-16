<?php
/**
 * Quran Website - configuration template.
 *
 * SETUP
 * -----
 * 1. Copy this file to `config.php` in the same directory.
 * 2. Fill in the database credentials from your hosting control panel.
 * 3. Set `app.url` to the public URL of the site.
 * 4. Set a random `maintenance.sync_token` if you want to trigger content
 *    syncs over HTTP:
 *      php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
 *
 * SECURITY
 * --------
 * - `config.php` contains secrets. Keep it OUTSIDE the web root (this directory
 *   is outside `public_html/` in the recommended layout) and never commit it.
 * - Every value can also be supplied through an environment variable (see the
 *   `env` key of each option) or through a `.env` file in the project root,
 *   which takes precedence over the value written here.
 * - Never paste these credentials into JavaScript or a public repository.
 */

return [

    'app' => [
        'name'        => 'Quran Reader',
        'url'         => 'https://example.com',   // no trailing slash
        'environment' => 'production',            // production | local
        'debug'       => false,                   // ALWAYS false in production
        'timezone'    => 'UTC',
        'locale'      => 'en',
    ],

    'database' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'quran_website',            // env: DB_DATABASE
        'username' => 'quran_user',               // env: DB_USERNAME
        'password' => '',                         // env: DB_PASSWORD
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'            => 'quran_session',
        'lifetime'        => 60 * 60 * 24 * 30,   // seconds (remember-me window)
        'idle_timeout'    => 60 * 60 * 3,         // log out after 3h of inactivity
        'cookie_path'     => '/',
        'cookie_domain'   => '',                  // e.g. .example.com (optional)
        'cookie_secure'   => 'auto',              // auto | true | false
        'same_site'       => 'Lax',               // Lax | Strict | None (None requires HTTPS)
    ],

    'security' => [
        // Leave empty for a same-origin deployment. Only list origins if the
        // frontend is served from a different domain.
        'cors_allowed_origins' => [],
        // Set to true when the site runs behind a trusted reverse proxy that
        // sets X-Forwarded-For, so client IPs are detected correctly.
        'trust_proxy_headers'  => false,
        // HSTS is only sent when the request is already HTTPS.
        'hsts_max_age'         => 31536000,
    ],

    'quran_api' => [
        // alquran.cloud: no key required, public, well documented.
        //   https://alquran.cloud/api
        'provider'  => 'alquran.cloud',
        'base_url'  => 'https://api.alquran.cloud/v1',
        'timeout'   => 25,
        'retries'   => 2,
        'user_agent'=> 'QuranWebsite/1.0 (+https://example.com)',
        // Path to a CA bundle if your host cannot verify TLS certificates.
        // Leave empty to use the system default.
        'cainfo'    => '',                        // env: CURL_CA_BUNDLE
    ],

    'content' => [
        // Translation editions to import, in menu order.
        // Identifiers come from alquran.cloud/edition?type=translation
        'translations' => [
            'en.sahih',        // Saheeh International  (default, English)
            'en.pickthall',    // Pickthall
            'en.yusufali',     // Abdullah Yusuf Ali
            'en.hilali',       // Hilali & Khan
        ],
        'default_translation' => 'en.sahih',

        // Recitations available in the player. Audio is streamed from the
        // Islamic Network CDN (per-verse MP3 files).
        'reciters' => [
            'ar.alafasy'    => ['name' => 'مشاري راشد العفاسي', 'english_name' => 'Mishary Rashid Alafasy', 'style' => 'Murattal', 'bitrate' => 128, 'default' => true],
            'ar.husary'     => ['name' => 'محمود خليل الحصري',   'english_name' => 'Mahmoud Khalil Al-Husary', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.minshawi'   => ['name' => 'محمد صديق المنشاوي',  'english_name' => 'Mohamed Siddiq Al-Minshawi', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.mahermuaiqly' => ['name' => 'ماهر المعيقلي',     'english_name' => 'Maher Al Muaiqly', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.shaatree'   => ['name' => 'أبو بكر الشاطري',      'english_name' => 'Abu Bakr Ash-Shaatree', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.hudhaify'   => ['name' => 'علي الحذيفي',          'english_name' => 'Ali Al-Hudhaify', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.ahmedajamy' => ['name' => 'أحمد بن علي العجمي',   'english_name' => 'Ahmed ibn Ali al-Ajamy', 'style' => 'Murattal', 'bitrate' => 128],
        ],
        'audio_base' => 'https://cdn.islamic.network/quran/audio',
    ],

    'mail' => [
        // mail = PHP mail() (works on most shared hosts)
        // smtp = authenticated SMTP (recommended when mail() is blocked)
        // log  = write messages to storage/logs/mail.log (development only)
        'transport'   => 'mail',                  // env: MAIL_TRANSPORT
        'from_email'  => 'no-reply@example.com',
        'from_name'   => 'Quran Reader',
        'smtp' => [
            'host'       => 'smtp.example.com',
            'port'       => 587,                  // 587 = STARTTLS, 465 = implicit TLS
            'username'   => '',
            'password'   => '',
            'encryption' => 'tls',                // tls | ssl | none
        ],
    ],

    'maintenance' => [
        // Token required by the web-based sync endpoint
        // (POST /api/cron/sync.php?token=...). Generate a random string.
        'sync_token' => '',                       // env: SYNC_TOKEN
        // Minimum number of hours between two full content syncs triggered
        // by the cron endpoint. Prevents accidental hammering of the API.
        'sync_cooldown_hours' => 24,
    ],

];
