<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Configuration container.
 *
 * Resolution order (later wins):
 *   1. app/config/config.php
 *   2. .env file in the project root
 *   3. real environment variables
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    private static string $root = '';

    public static function load(string $projectRoot): void
    {
        self::$root = $projectRoot;

        // 1. Shipped defaults — the structure every install shares.
        $defaultsFile = QURAN_APP . '/config/defaults.php';
        if (!is_file($defaultsFile)) {
            throw new \RuntimeException('Missing app/config/defaults.php.');
        }
        $config = require $defaultsFile;
        if (!is_array($config)) {
            throw new \RuntimeException('app/config/defaults.php must return an array.');
        }

        // 2. Optional local PHP config (legacy installs, one-off overrides).
        $configFile = QURAN_APP . '/config/config.php';
        if (is_file($configFile)) {
            $local = require $configFile;
            if (is_array($local)) {
                $config = self::merge($config, $local);
            }
        }

        self::$items = $config;

        // 3. .env in the project root, then real environment variables.
        self::loadDotEnv($projectRoot . '/.env');
        self::applyEnvironment();
    }

    /**
     * Recursive merge where the second array wins, but lists (e.g. the
     * translation ids) are replaced rather than concatenated.
     *
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Dot notation access: Config::get('database.host').
     *
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $ref      = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return self::$items;
    }

    public static function isProduction(): bool
    {
        return self::get('app.environment', 'production') !== 'local';
    }

    public static function debug(): bool
    {
        return (bool) self::get('app.debug', false) && !self::isProduction();
    }

    public static function root(): string
    {
        return self::$root;
    }

    private static function loadDotEnv(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }
            // Strip matching quotes and inline comments for unquoted values.
            if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end   = strrpos($value, $quote);
                $value = $end !== false ? substr($value, 1, $end - 1) : $value;
            } else {
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = substr($value, 0, $hash);
                }
                $value = trim($value);
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
            }
            if (!isset($_SERVER[$name])) {
                $_SERVER[$name] = $value;
            }
        }
    }

    private static function applyEnvironment(): void
    {
        $map = [
            // Application
            'APP_NAME'        => ['app.name', 'string'],
            'APP_URL'         => ['app.url', 'string'],
            'APP_ENV'         => ['app.environment', 'string'],
            'APP_DEBUG'       => ['app.debug', 'bool'],
            'APP_TIMEZONE'    => ['app.timezone', 'string'],
            'APP_LOCALE'      => ['app.locale', 'string'],
            'APP_ADMIN_EMAIL' => ['app.admin_email', 'string'],
            'APP_BASE'        => ['app.base', 'string'],

            // Database
            'DB_HOST'         => ['database.host', 'string'],
            'DB_PORT'         => ['database.port', 'int'],
            'DB_DATABASE'     => ['database.database', 'string'],
            'DB_USERNAME'     => ['database.username', 'string'],
            'DB_PASSWORD'     => ['database.password', 'string'],
            'DB_CHARSET'      => ['database.charset', 'string'],

            // Session / cookies
            'SESSION_NAME'          => ['session.name', 'string'],
            'SESSION_LIFETIME'      => ['session.lifetime', 'int'],
            'SESSION_IDLE_TIMEOUT'  => ['session.idle_timeout', 'int'],
            'SESSION_COOKIE_DOMAIN' => ['session.cookie_domain', 'string'],
            'SESSION_COOKIE_SECURE' => ['session.cookie_secure', 'string'],
            'SESSION_SAME_SITE'     => ['session.same_site', 'string'],

            // Security
            'SECURITY_TRUST_PROXY'  => ['security.trust_proxy_headers', 'bool'],
            'SECURITY_HSTS_MAX_AGE' => ['security.hsts_max_age', 'int'],
            'SECURITY_CORS_ORIGINS' => ['security.cors_allowed_origins', 'csv'],

            // Quran API
            'QURAN_API_BASE_URL'   => ['quran_api.base_url', 'string'],
            'QURAN_API_TIMEOUT'    => ['quran_api.timeout', 'int'],
            'QURAN_API_RETRIES'    => ['quran_api.retries', 'int'],
            'QURAN_API_USER_AGENT' => ['quran_api.user_agent', 'string'],
            'CURL_CA_BUNDLE'       => ['quran_api.cainfo', 'string'],

            // Content
            'CONTENT_TRANSLATIONS'        => ['content.translations', 'csv'],
            'CONTENT_DEFAULT_TRANSLATION' => ['content.default_translation', 'string'],
            'CONTENT_AUDIO_BASE'          => ['content.audio_base', 'string'],

            // Mail
            'MAIL_TRANSPORT'   => ['mail.transport', 'string'],
            'MAIL_FROM'        => ['mail.from_email', 'string'],
            'MAIL_FROM_NAME'   => ['mail.from_name', 'string'],
            'SMTP_HOST'        => ['mail.smtp.host', 'string'],
            'SMTP_PORT'        => ['mail.smtp.port', 'int'],
            'SMTP_USERNAME'    => ['mail.smtp.username', 'string'],
            'SMTP_PASSWORD'    => ['mail.smtp.password', 'string'],
            'SMTP_ENCRYPTION'  => ['mail.smtp.encryption', 'string'],

            // Maintenance
            'SYNC_TOKEN'            => ['maintenance.sync_token', 'string'],
            'SYNC_COOLDOWN_HOURS'   => ['maintenance.sync_cooldown_hours', 'int'],
        ];

        foreach ($map as $env => [$key, $type]) {
            $raw = getenv($env);
            // Empty strings are only meaningful for credentials, everything else
            // keeps the default instead of being blanked out.
            if ($raw === false || ($raw === '' && !in_array($key, ['database.password', 'mail.smtp.password'], true))) {
                continue;
            }
            if ($type === 'bool') {
                $raw = in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
            } elseif ($type === 'int') {
                $raw = (int) $raw;
            } elseif ($type === 'csv') {
                $raw = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($v) => $v !== ''));
            }
            self::set($key, $raw);
        }
    }
}
