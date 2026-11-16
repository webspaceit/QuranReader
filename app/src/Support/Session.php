<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Hardened PHP session handling.
 *
 * - cookies are HttpOnly + SameSite, and Secure whenever HTTPS is in use
 * - session ids are rotated periodically and after privilege changes
 * - idle sessions expire
 * - PHP's strict mode rejects ids the server never issued
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $lifetime = (int) Config::get('session.lifetime', 2592000);
        $name     = (string) Config::get('session.name', 'quran_session');
        $sameSite = (string) Config::get('session.same_site', 'Lax');
        $domain   = (string) Config::get('session.cookie_domain', '');

        $secureOption = Config::get('session.cookie_secure', 'auto');
        $secure       = $secureOption === 'auto' ? self::isHttps() : (bool) $secureOption;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) max($lifetime, 1440));

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => (string) Config::get('session.cookie_path', '/'),
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ]);
        } else {
            session_set_cookie_params($lifetime, (string) Config::get('session.cookie_path', '/'), $domain, $secure, true);
        }

        if ($name !== '') {
            session_name($name);
        }

        session_start();
        self::$started = true;

        self::enforceIdleTimeout();
        self::rotate();
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        if ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            && Config::get('security.trust_proxy_headers', false)) {
            return true;
        }

        return false;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_rotated_at'] = time();
        }
    }

    /** Flash data survives exactly one redirect. */
    public static function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value === null ? null : (string) $value;
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    private static function enforceIdleTimeout(): void
    {
        $timeout = (int) Config::get('session.idle_timeout', 10800);
        $last    = (int) ($_SESSION['_last_activity'] ?? 0);

        if ($timeout > 0 && $last > 0 && (time() - $last) > $timeout) {
            $hadUser = isset($_SESSION['user_id']);
            session_unset();
            session_regenerate_id(true);
            $_SESSION['_expired'] = $hadUser ? 1 : 0;
        }

        $_SESSION['_last_activity'] = time();
    }

    private static function rotate(): void
    {
        $now      = time();
        $lastRot  = (int) ($_SESSION['_rotated_at'] ?? 0);
        $interval = 900; // 15 minutes

        if ($lastRot === 0) {
            $_SESSION['_rotated_at'] = $now;
            return;
        }
        if ($now - $lastRot > $interval) {
            session_regenerate_id(true);
            $_SESSION['_rotated_at'] = $now;
        }
    }
}
