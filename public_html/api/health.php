<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';

Quran\Http\Api::run('GET', [Quran\Http\Controllers\HealthController::class, 'index']);
