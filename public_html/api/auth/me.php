<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_bootstrap.php';

Quran\Http\Api::run('GET', [Quran\Http\Controllers\AuthController::class, 'me'], ['auth' => true]);
