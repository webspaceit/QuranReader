<?php
declare(strict_types=1);

use Quran\Support\Config;
use Quran\Support\Csrf;

/**
 * HTML-escape a value for output. Always use this for any dynamic text.
 *
 * @param mixed $value
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL path prefix of the installation, e.g. "" for a domain root or
 * "/quran" when the site lives in a sub-directory. Works on shared hosting
 * where the web root differs from the project root.
 */
function base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (PHP_SAPI === 'cli' || empty($_SERVER['DOCUMENT_ROOT'])) {
        return $cached = '';
    }

    $public  = realpath(QURAN_ROOT . '/public_html');
    $docRoot = realpath((string) $_SERVER['DOCUMENT_ROOT']);

    if ($public !== false && $docRoot !== false && strpos($public, $docRoot) === 0) {
        $sub = str_replace('\\', '/', substr($public, strlen($docRoot)));
        return $cached = rtrim($sub, '/');
    }

    // Fallback: derive from the running script (works for /index.php style URLs).
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir    = rtrim(dirname($script), '/');
    if (substr($dir, -3) === '/api') {
        $dir = rtrim(substr($dir, 0, -4), '/');
    }
    return $cached = ($dir === '.' ? '' : $dir);
}

/** Internal link path (relative to the site root). */
function u(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/**
 * Accept only an on-site path, e.g. "/bookmarks.php?page=2".
 *
 * Used for post-login redirects: absolute URLs, protocol-relative URLs
 * ("//evil.test") and backslash variants ("/\evil.test") are all rejected so
 * the login form cannot be turned into an open redirect.
 */
function safe_redirect_path(?string $candidate, string $fallback = 'account.php'): string
{
    $candidate = trim((string) $candidate);
    if ($candidate === '' || $candidate[0] !== '/') {
        return $fallback;
    }

    // "//host" and "/\host" are treated as absolute by browsers.
    $second = $candidate[1] ?? '';
    if ($second === '/' || $second === '\\') {
        return $fallback;
    }

    // No control characters, newlines or backslashes anywhere in the path.
    if (strpos($candidate, chr(92)) !== false || preg_match('~[\x00-\x1F\x7F]~', $candidate) === 1) {
        return $fallback;
    }

    return $candidate;
}

/** Absolute URL, used in emails, canonical tags and share links. */
function absolute_url(string $path = ''): string
{
    $base = trim((string) Config::get('app.url', ''));
    if ($base === '' || $base === 'https://example.com') {
        $scheme = Quran\Support\Session::isHttps() ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base   = $scheme . '://' . $host . base_path();
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Inline SVG that references a symbol from views/partials/icons.php.
 *
 * The sprite is the reason a 286-verse surah page stays small: an icon costs
 * ~80 bytes here instead of the ~300 bytes of a duplicated path. Only 24x24
 * symbols are supported; the logo is inlined separately.
 */
function icon(string $name, string $class = 'ico', int $size = 18): string
{
    $dimensions = $size > 0 ? ' width="' . $size . '" height="' . $size . '"' : '';
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24"' . $dimensions
        . ' aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"/></svg>';
}

/** Versioned asset URL for cache busting. */
function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $file = QURAN_ROOT . '/public_html/' . $path;
    $ver  = is_file($file) ? (string) filemtime($file) : '1';
    return u($path) . '?v=' . $ver;
}

/** Link to a surah, optionally anchored on a verse. */
function surah_url(int $surah, ?int $verse = null): string
{
    $url = u('surah.php?n=' . $surah);
    return $verse === null ? $url : $url . '#verse-' . $verse;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Redirect and stop. */
function redirect(string $path, int $status = 302): void
{
    header('Location: ' . (strpos($path, 'http') === 0 ? $path : u($path)), true, $status);
    exit;
}

/**
 * Read configuration with dot notation (thin wrapper for templates).
 *
 * @param mixed $default
 * @return mixed
 */
function config(string $key, $default = null)
{
    return Config::get($key, $default);
}

/** True when the current request is an XMLHttpRequest / fetch call. */
function is_ajax(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept        = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

/** Client IP address (honours X-Forwarded-For only when explicitly trusted). */
function client_ip(): string
{
    if (Config::get('security.trust_proxy_headers', false)) {
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Locale-aware short date for "saved on" labels. */
function format_date(string $sqlDate): string
{
    $ts = strtotime($sqlDate);
    return $ts !== false ? date('j M Y', $ts) : $sqlDate;
}

/**
 * Render a view template with shared data.
 *
 * @param array<string,mixed> $data
 */
function view(string $template, array $data = []): string
{
    $file = QURAN_APP . '/views/' . $template . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('View not found: ' . $template);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

/**
 * Render a view inside the main layout.
 *
 * @param array<string,mixed> $data
 */
function render_page(string $template, array $data = []): void
{
    $data['content'] = view($template, $data);
    echo view('layout', $data);
}
