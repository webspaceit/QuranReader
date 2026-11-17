<?php
/**
 * Built-in defaults.
 *
 * Every value here can be overridden by .env (see .env.example), and by real
 * environment variables after that. The structure — which translations and
 * reciters exist, how sessions are shaped, how mail is sent — lives here so a
 * fresh deploy only needs a .env file, not a hand-written PHP config.
 *
 * app/config/config.php is still honoured when present, for older installs and
 * for one-off local overrides; it is applied *before* .env, so .env wins.
 */

return [

    'app' => [
        'name'        => 'Quran Reader',
        'url'         => 'http://localhost',
        'environment' => 'production',
        'debug'       => false,
        'timezone'    => 'UTC',
        'locale'      => 'en',
        // Optional: an email that is always treated as the super admin, even
        // before the is_admin flag is set. env: APP_ADMIN_EMAIL
        'admin_email' => '',
        // null = detect from the filesystem; '/' = domain root; '/sub' = prefix.
        'base'        => null,
    ],

    'database' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'quran',
        'username' => '',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'          => 'quran_session',
        'lifetime'      => 60 * 60 * 24 * 30,
        'idle_timeout'  => 60 * 60 * 3,
        'cookie_path'   => '/',
        'cookie_domain' => '',
        'cookie_secure' => 'auto',
        'same_site'     => 'Lax',
    ],

    'security' => [
        'cors_allowed_origins' => [],
        'trust_proxy_headers'  => false,
        'hsts_max_age'         => 31536000,
    ],

    'quran_api' => [
        'provider'   => 'alquran.cloud',
        'base_url'   => 'https://api.alquran.cloud/v1',
        'timeout'    => 25,
        'retries'    => 2,
        'user_agent' => 'QuranWebsite/1.0',
        'cainfo'     => '',
    ],

    'content' => [
        'translations' => [
            'en.sahih',
            'en.pickthall',
            'en.yusufali',
            'en.hilali',
            'bn.bengali',
            'bn.hoque',
        ],
        'default_translation' => 'en.sahih',
        'reciters' => [
            'ar.alafasy'      => ['name' => 'مشاري راشد العفاسي', 'english_name' => 'Mishary Rashid Alafasy', 'style' => 'Murattal', 'bitrate' => 128, 'default' => true],
            'ar.husary'       => ['name' => 'محمود خليل الحصري', 'english_name' => 'Mahmoud Khalil Al-Husary', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.minshawi'     => ['name' => 'محمد صديق المنشاوي', 'english_name' => 'Mohamed Siddiq Al-Minshawi', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.mahermuaiqly' => ['name' => 'ماهر المعيقلي', 'english_name' => 'Maher Al Muaiqly', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.shaatree'     => ['name' => 'أبو بكر الشاطري', 'english_name' => 'Abu Bakr Ash-Shaatree', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.hudhaify'     => ['name' => 'علي الحذيفي', 'english_name' => 'Ali Al-Hudhaify', 'style' => 'Murattal', 'bitrate' => 128],
            'ar.ahmedajamy'   => ['name' => 'أحمد بن علي العجمي', 'english_name' => 'Ahmed ibn Ali al-Ajamy', 'style' => 'Murattal', 'bitrate' => 128],
        ],
        'audio_base' => 'https://cdn.islamic.network/quran/audio',
    ],

    'mail' => [
        'transport'  => 'log',
        'from_email' => 'no-reply@localhost',
        'from_name'  => 'Quran Reader',
        'smtp' => [
            'host'       => '',
            'port'       => 587,
            'username'   => '',
            'password'   => '',
            'encryption' => 'tls',
        ],
    ],

    'maintenance' => [
        'sync_token'           => '',
        'sync_cooldown_hours'  => 24,
    ],

];
