<?php
/**
 * Cron entry point: prune expired tokens, rate-limit rows and old sync logs.
 *
 *   curl -X POST "https://example.com/api/cron/maintenance.php?token=YOUR_TOKEN"
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_bootstrap.php';

Quran\Http\Api::run('POST', [Quran\Http\Controllers\CronController::class, 'maintenance'], ['csrf' => false]);
