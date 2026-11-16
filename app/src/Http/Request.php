<?php
declare(strict_types=1);

namespace Quran\Http;

/**
 * Request input helper. Accepts form-encoded, multipart and JSON bodies.
 */
final class Request
{
    /** @var array<string,mixed>|null */
    private static ?array $body = null;

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$body === null) {
            self::$body = [];
            $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

            if (strpos($contentType, 'application/json') !== false) {
                $raw     = file_get_contents('php://input');
                $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
                if (is_array($decoded)) {
                    self::$body = $decoded;
                }
            }
        }

        return array_merge($_GET, self::$body, $_POST);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function input(string $key, $default = null)
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    /** True when the key was sent at all, even with a null value. */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::input($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function ip(): string
    {
        return client_ip();
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
