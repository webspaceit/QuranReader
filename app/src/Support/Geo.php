<?php
declare(strict_types=1);

namespace Quran\Support;

final class Geo
{
    /** @var array<int,string>|null */
    private static ?array $cidrs = null;

    private static ?string $cachedCountry = null;

    private static array $ipCache = [];

    /**
     * Best-effort ISO-3166 country code for the current visitor.
     */
    public static function country(): string
    {
        if (self::$cachedCountry !== null) {
            return self::$cachedCountry;
        }

        $result = self::detectCountry();
        self::$cachedCountry = $result;
        return $result;
    }

    private static function detectCountry(): string
    {
        // Honour explicit server/proxy country headers first.
        foreach ([
            'HTTP_CF_IPCOUNTRY',
            'CF_IPCOUNTRY',
            'HTTP_X_COUNTRY_CODE',
            'HTTP_X_FORWARDED_COUNTRY',
            'HTTP_X_COUNTRY',
            'HTTP_X_GEO_COUNTRY',
            'GEOIP_COUNTRY_CODE',
            'GEOIP_COUNTRY_CODE_V6',
            'MM_COUNTRY_CODE',
            'HTTP_X_CLIENT_COUNTRY',
            'COUNTRY_CODE',
            'VISITOR_COUNTRY',
        ] as $key) {
            $value = $_SERVER[$key] ?? null;
            if (is_string($value)) {
                $code = strtoupper(trim($value));
                if (preg_match('/^[A-Z]{2}$/', $code) === 1 && $code !== 'XX' && $code !== 'T1') {
                    return $code;
                }
            }
        }

        // Environment overrides (useful for tests / CLI).
        foreach (['VISITOR_COUNTRY', 'COUNTRY_CODE', 'GEOIP_COUNTRY_CODE'] as $key) {
            $value = getenv($key);
            if (is_string($value)) {
                $code = strtoupper(trim($value));
                if (preg_match('/^[A-Z]{2}$/', $code) === 1 && $code !== 'XX' && $code !== 'T1') {
                    return $code;
                }
            }
        }

        // Cookies.
        foreach (['country', 'visitor_country', 'geo_country', 'quran_country'] as $key) {
            $value = $_COOKIE[$key] ?? null;
            if (is_string($value)) {
                $code = strtoupper(trim($value));
                if (preg_match('/^[A-Z]{2}$/', $code) === 1 && $code !== 'XX' && $code !== 'T1') {
                    return $code;
                }
            }
        }

        // Query string (handy for previews / tests).
        foreach (['country', 'cc', '_country', 'geo'] as $key) {
            $value = $_GET[$key] ?? null;
            if (is_string($value)) {
                $code = strtoupper(trim($value));
                if (preg_match('/^[A-Z]{2}$/', $code) === 1 && $code !== 'XX' && $code !== 'T1') {
                    return $code;
                }
            }
        }

        // Accept-Language: Bengali speakers default to the Bengali edition.
        $acceptLanguage = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($acceptLanguage !== '' && preg_match('/\bbn(?:-[A-Za-z]{2})?\b/i', $acceptLanguage) === 1) {
            return 'BD';
        }

        // IP-range fallback (bundled Bangladesh CIDR list).
        $ip = function_exists('client_ip') ? client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '' && self::isBangladeshIp($ip)) {
            return 'BD';
        }

        return '';
    }

    public static function isBangladeshIp(string $ip): bool
    {
        if (array_key_exists($ip, self::$ipCache)) {
            return self::$ipCache[$ip];
        }

        if (self::$cidrs === null) {
            self::$cidrs = [];
            $file = (defined('QURAN_APP') ? QURAN_APP : '') . '/data/bd-cidr.txt';
            if ($file !== '' && is_file($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line !== '' && strpos($line, '/') !== false) {
                        self::$cidrs[] = $line;
                    }
                }
            }
        }

        $result = false;
        foreach (self::$cidrs as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                $result = true;
                break;
            }
        }
        self::$ipCache[$ip] = $result;
        return $result;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, ':') !== false || strpos($ip, ':') !== false) {
            return self::ipv6InCidr($ip, $cidr);
        }

        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return false;
        }
        [$subnet, $bits] = $parts;
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private static function ipv6InCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return false;
        }
        [$subnet, $bits] = $parts;
        $bits = (int) $bits;
        if ($bits < 0 || $bits > 128) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== 16 || strlen($subnetBin) !== 16) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = 0xFF << (8 - $rem);
        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}
