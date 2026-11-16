<?php
/**
 * Cron entry point: refresh Quran content from the API.
 *
 * Called by a hosting cron job or manually:
 *   curl -X POST "https://example.com/api/cron/sync.php?token=YOUR_TOKEN"
 *
 * Authentication is the shared token in the configuration, so CSRF does not apply.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_bootstrap.php';

Quran\Http\Api::run('POST', [Quran\Http\Controllers\CronController::class, 'sync'], ['csrf' => false]);
