<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Repositories\ChapterRepository;
use Quran\Repositories\VerseRepository;
use Quran\Support\Config;
use Quran\Support\Database;
use Quran\Support\Session;

/**
 * Deployment smoke test. Never exposes credentials or environment details
 * beyond what a hoster would already know.
 */
final class HealthController
{
    public function index(): array
    {
        $dbOk = Database::ping();

        $verses   = 0;
        $chapters = 0;
        $lastSync = null;

        if ($dbOk) {
            $verses   = VerseRepository::count();
            $chapters = ChapterRepository::count();
            $lastSync = Database::value('SELECT finished_at FROM sync_runs WHERE status = ? ORDER BY id DESC LIMIT 1', ['success']);
        }

        $checks = [
            'php_version'   => PHP_VERSION,
            'php_ok'        => PHP_VERSION_ID >= 80100,
            'pdo_mysql'     => extension_loaded('pdo_mysql'),
            'mbstring'      => extension_loaded('mbstring'),
            'curl'          => extension_loaded('curl'),
            'openssl'       => extension_loaded('openssl'),
            'json'          => extension_loaded('json'),
            'database'      => $dbOk,
            'https'         => Session::isHttps(),
            'debug_mode'    => Config::debug(),
        ];

        $contentReady = $chapters === 114 && $verses >= 6236;

        return [
            'status'         => $dbOk && $checks['php_ok'] ? ($contentReady ? 'ok' : 'needs_content_sync') : 'error',
            'content_ready'  => $contentReady,
            'chapters'       => $chapters,
            'verses'         => $verses,
            'last_sync'      => $lastSync,
            'checks'         => $checks,
            'time_utc'       => gmdate('c'),
        ];
    }
}
