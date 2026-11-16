<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Very small file logger. One file per day under storage/logs.
 * Sensitive keys are redacted before anything is written.
 */
final class Logger
{
    private const REDACT = ['password', 'password_hash', 'token', 'secret', 'authorization', 'csrf', '_csrf', 'api_key'];

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /** @param array<string,mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        $dir = QURAN_STORAGE . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            return;
        }

        $line = sprintf(
            "[%s] %s %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode(self::redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $file = $dir . '/app-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        if (is_file($file) && filesize($file) > 5 * 1024 * 1024) {
            @rename($file, $dir . '/app-' . date('Y-m-d') . '-' . time() . '.log');
        }
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::redact($value);
                continue;
            }
            if (is_string($key)) {
                foreach (self::REDACT as $needle) {
                    if (stripos($key, $needle) !== false) {
                        $data[$key] = '[redacted]';
                        break;
                    }
                }
            }
        }
        return $data;
    }
}
