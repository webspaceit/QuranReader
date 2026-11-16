<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Response hardening. Sent on every HTML/API response.
 *
 * The Content-Security-Policy is deliberately strict (no inline scripts,
 * no third-party scripts). Audio media is allowed from the recitation CDN
 * that the configuration points at.
 */
final class SecurityHeaders
{
    public static function send(): void
    {
        if (headers_sent()) {
            return;
        }

        // Do not advertise the PHP version to clients.
        header_remove('X-Powered-By');

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');

        header(self::contentSecurityPolicy());

        if (Session::isHttps()) {
            $maxAge = (int) Config::get('security.hsts_max_age', 31536000);
            if ($maxAge > 0) {
                header('Strict-Transport-Security: max-age=' . $maxAge . '; includeSubDomains');
            }
        }
    }

    public static function contentSecurityPolicy(): string
    {
        $media = "'self'";
        $audioBase = (string) Config::get('content.audio_base', 'https://cdn.islamic.network/quran/audio');
        $host = parse_url($audioBase, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $media .= ' https://' . $host;
        }

        $origins = Config::get('security.cors_allowed_origins', []);
        $connect = "'self'";
        if (is_array($origins)) {
            foreach ($origins as $origin) {
                $origin = rtrim((string) $origin, '/');
                if ($origin !== '' && $origin !== "'self'") {
                    $connect .= ' ' . $origin;
                }
            }
        }

        $policy = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'", // inline style attributes drive font size / progress bars
            "img-src 'self' data:",
            "font-src 'self'",
            "media-src " . $media,
            "connect-src " . $connect,
            "manifest-src 'self'",
        ];

        return 'Content-Security-Policy: ' . implode('; ', $policy);
    }

    /** Only echo CORS headers when a cross-origin frontend is configured. */
    public static function sendCors(): void
    {
        $origins = Config::get('security.cors_allowed_origins', []);
        if (!is_array($origins) || $origins === []) {
            return;
        }

        $requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($requestOrigin === '') {
            return;
        }

        $allowed = array_map(static fn ($o) => rtrim((string) $o, '/'), $origins);
        if (!in_array(rtrim($requestOrigin, '/'), $allowed, true)) {
            return;
        }

        header('Access-Control-Allow-Origin: ' . $requestOrigin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Accept');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Vary: Origin');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
