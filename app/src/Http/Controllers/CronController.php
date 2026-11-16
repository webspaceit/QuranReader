<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Http\ApiException;
use Quran\Http\CurlHttpClient;
use Quran\Http\Request;
use Quran\Quran\QuranApiClient;
use Quran\Quran\QuranSync;
use Quran\Support\Config;
use Quran\Support\Database;
use Quran\Support\Logger;
use Quran\Support\RateLimiter;

/**
 * Token-protected endpoints for hosting cron jobs.
 *
 * Useful when the host allows cron jobs but not shell access:
 *   curl -X POST "https://example.com/api/cron/sync.php?token=YOUR_TOKEN"
 *
 * Configure the token in app/config/config.php (maintenance.sync_token).
 */
final class CronController
{
    public function sync(): array
    {
        $this->authorize();
        $this->throttle();

        $cooldown = max(1, (int) Config::get('maintenance.sync_cooldown_hours', 24));
        $last     = Database::value(
            'SELECT finished_at FROM sync_runs WHERE status = ? ORDER BY id DESC LIMIT 1',
            ['success']
        );

        if (is_string($last) && $last !== '') {
            $age = time() - (int) strtotime($last . ' UTC');
            if ($age < $cooldown * 3600) {
                return [
                    'skipped'      => true,
                    'reason'       => 'Last successful sync was less than ' . $cooldown . ' hours ago.',
                    'last_success' => $last,
                ];
            }
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $sync  = new QuranSync(new QuranApiClient(new CurlHttpClient()));
        $stats = $sync->run();

        return ['skipped' => false, 'stats' => $stats];
    }

    public function maintenance(): array
    {
        $this->authorize();
        $this->throttle();

        $rateLimits = Database::execute('DELETE FROM rate_limits WHERE window_start < ?', [time() - 604800]);
        $resets     = Database::execute('DELETE FROM password_resets WHERE expires_at < (UTC_TIMESTAMP() - INTERVAL 7 DAY)');
        $runs       = Database::execute('DELETE FROM sync_runs WHERE started_at < (UTC_TIMESTAMP() - INTERVAL 90 DAY)');

        return [
            'rate_limits_removed' => $rateLimits,
            'reset_tokens_removed'=> $resets,
            'sync_runs_removed'   => $runs,
        ];
    }

    private function authorize(): void
    {
        $expected = (string) Config::get('maintenance.sync_token', '');
        if ($expected === '') {
            throw new ApiException(
                'Cron endpoints are disabled. Set maintenance.sync_token in the configuration file.',
                403,
                'cron_disabled'
            );
        }

        $provided = Request::string('token');
        if ($provided === '') {
            $provided = (string) ($_SERVER['HTTP_X_SYNC_TOKEN'] ?? '');
        }

        if (!hash_equals($expected, $provided)) {
            Logger::warning('Cron endpoint called with an invalid token', ['ip' => Request::ip()]);
            throw ApiException::forbidden();
        }
    }

    private function throttle(): void
    {
        $bucket = RateLimiter::hit('cron', Request::ip(), 6, 3600);
        if (!$bucket['allowed']) {
            throw ApiException::tooManyRequests($bucket['retry_after']);
        }
    }
}
