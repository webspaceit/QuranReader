<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Synchroniser-token CSRF protection.
 *
 * Every state-changing request (POST) must present the token either as the
 * `_csrf` form field or in the `X-CSRF-Token` header. The token is bound to
 * the PHP session, so it cannot be predicted by a third-party site.
 * Origin/Referer are additionally inspected as defence in depth.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    /** Issue a fresh token (called after login/logout). */
    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    public static function validate(?string $token): bool
    {
        $expected = Session::get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /** Token taken from the request header or POST body. */
    public static function fromRequest(): ?string
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($header) && $header !== '') {
            return $header;
        }
        $field = $_POST['_csrf'] ?? null;
        return is_string($field) ? $field : null;
    }

    /**
     * Full check for a state-changing request: token + same-origin.
     */
    public static function verifyRequest(): bool
    {
        if (!self::validate(self::fromRequest())) {
            return false;
        }
        return self::isSameOrigin();
    }

    /**
     * Reject cross-site POSTs even if a token leaked. When neither Origin nor
     * Referer is sent (some privacy tools), the token check alone applies.
     */
    private static function isSameOrigin(): bool
    {
        $host    = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $origin  = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        $source = $origin !== '' ? $origin : $referer;
        if ($source === '') {
            return true;
        }

        $sourceHost = parse_url($source, PHP_URL_HOST);
        if (!is_string($sourceHost) || $sourceHost === '') {
            return false;
        }

        // Normalise the request host (strip port).
        $requestHost = $host;
        if (strpos($requestHost, ':') !== false) {
            $requestHost = explode(':', $requestHost)[0];
        }

        return strcasecmp($sourceHost, $requestHost) === 0;
    }
}
