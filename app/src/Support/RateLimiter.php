<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Fixed-window rate limiter backed by MySQL.
 *
 * Shared hosting rarely offers Redis, so counters live in the database. The
 * UPDATE ... ON DUPLICATE KEY form makes each hit atomic.
 */
final class RateLimiter
{
    /**
     * Count one hit and report whether the caller is still within the limit.
     *
     * @return array{allowed:bool,hits:int,limit:int,retry_after:int}
     */
    public static function hit(string $bucket, string $identifier, int $max, int $windowSeconds): array
    {
        $window = max(1, $windowSeconds);
        $slot   = (int) floor(time() / $window) * $window;
        $key    = hash('sha256', strtolower($identifier));

        try {
            Database::execute(
                'INSERT INTO rate_limits (bucket, identifier, window_start, hits)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE hits = hits + 1',
                [$bucket, $key, $slot]
            );

            $hits = (int) Database::value(
                'SELECT hits FROM rate_limits WHERE bucket = ? AND identifier = ? AND window_start = ?',
                [$bucket, $key, $slot]
            );

            self::cleanupOccasionally();
        } catch (\Throwable $e) {
            // Never block authentication because of a monitoring failure.
            Logger::warning('Rate limiter unavailable: ' . $e->getMessage());
            return ['allowed' => true, 'hits' => 0, 'limit' => $max, 'retry_after' => 0];
        }

        return [
            'allowed'     => $hits <= $max,
            'hits'        => $hits,
            'limit'       => $max,
            'retry_after' => max(0, $slot + $window - time()),
        ];
    }

    /** Check without counting (useful for read-only endpoints). */
    public static function tooMany(string $bucket, string $identifier, int $max, int $windowSeconds): bool
    {
        $window = max(1, $windowSeconds);
        $slot   = (int) floor(time() / $window) * $window;
        $key    = hash('sha256', strtolower($identifier));

        $hits = (int) Database::value(
            'SELECT hits FROM rate_limits WHERE bucket = ? AND identifier = ? AND window_start = ?',
            [$bucket, $key, $slot]
        );

        return $hits > $max;
    }

    public static function clear(string $bucket, string $identifier): void
    {
        Database::execute(
            'DELETE FROM rate_limits WHERE bucket = ? AND identifier = ?',
            [$bucket, hash('sha256', strtolower($identifier))]
        );
    }

    private static function cleanupOccasionally(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        Database::execute('DELETE FROM rate_limits WHERE window_start < ?', [time() - 172800]);
    }
}
