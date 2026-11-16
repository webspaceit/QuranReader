<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_bootstrap.php';

Quran\Http\Api::run('POST', [Quran\Http\Controllers\AuthController::class, 'updateProfile'], ['auth' => true]);
