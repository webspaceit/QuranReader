<?php
declare(strict_types=1);

namespace Quran\Http;

use Quran\Auth\Auth;
use Quran\Support\Csrf;
use Quran\Support\Logger;
use Quran\Support\SecurityHeaders;

/**
 * JSON API entry point.
 *
 * Every endpoint file is a thin wrapper:
 *
 *     Api::run('POST', [AuthController::class, 'login'], ['auth' => false]);
 *
 * Responsibilities handled here, once, for every endpoint:
 *   - HTTP method enforcement
 *   - CSRF verification for state-changing requests
 *   - authentication gate
 *   - consistent JSON envelope and error mapping
 *   - no caching of private responses
 */
final class Api
{
    /**
     * @param callable|array{0:class-string,1:string} $handler
     * @param array{auth?:bool,csrf?:bool} $options
     */
    public static function run(string $method, $handler, array $options = []): void
    {
        if (!defined('QURAN_CONTEXT')) {
            define('QURAN_CONTEXT', 'api');
        }

        SecurityHeaders::sendCors();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex');

        try {
            if (Request::method() === 'OPTIONS') {
                http_response_code(204);
                return;
            }

            if (Request::method() !== strtoupper($method)) {
                header('Allow: ' . strtoupper($method));
                throw ApiException::methodNotAllowed();
            }

            $requiresCsrf = $options['csrf'] ?? (strtoupper($method) === 'POST');
            if ($requiresCsrf && !Csrf::verifyRequest()) {
                throw new ApiException(
                    'Your session expired. Please refresh the page and try again.',
                    419,
                    'csrf_failed'
                );
            }

            if (!empty($options['auth']) && !Auth::check()) {
                throw ApiException::unauthorized();
            }

            $result = is_callable($handler)
                ? $handler()
                : call_user_func([new $handler[0](), $handler[1]]);

            self::respond(['ok' => true, 'data' => $result === null ? [] : $result]);
        } catch (ApiException $e) {
            self::respondError($e->status(), $e->errorCode(), $e->getMessage(), $e->details());
        } catch (\Throwable $e) {
            Logger::error('API failure: ' . $e->getMessage(), [
                'route' => $_SERVER['REQUEST_URI'] ?? '',
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            self::respondError(500, 'server_error', 'Something went wrong. Please try again.');
        }
    }

    /** @param array<string,mixed> $payload */
    public static function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string,mixed> $details */
    public static function respondError(int $status, string $code, string $message, array $details = []): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }
        self::respond(['ok' => false, 'error' => $error], $status);
    }
}
