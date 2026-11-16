<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Last-resort error renderer. Logs the full detail, shows a generic message.
 */
final class ErrorHandler
{
    public static function handle(\Throwable $e): void
    {
        Logger::error('Unhandled ' . get_class($e) . ': ' . $e->getMessage(), [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (headers_sent()) {
            echo self::debugBlock($e);
            return;
        }

        $isApi = defined('QURAN_CONTEXT') && QURAN_CONTEXT === 'api';

        if ($isApi || is_ajax()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            $payload = [
                'ok'    => false,
                'error' => [
                    'code'    => 'server_error',
                    'message' => 'Something went wrong on our side. Please try again.',
                ],
            ];
            if (Config::debug()) {
                $payload['error']['debug'] = $e->getMessage();
            }
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Server error</title>';
        echo '<style>body{font-family:system-ui,sans-serif;background:#FAF8F2;color:#1F2937;margin:0;padding:3rem 1.25rem;text-align:center}'
            . 'h1{font-size:1.35rem;margin:0 0 .75rem}p{color:#4B5563;max-width:34rem;margin:0 auto 1rem}'
            . 'a{color:#0F5132}code{background:#EFEAE0;padding:.15rem .35rem;border-radius:4px;font-size:.85em}</style>';
        echo '</head><body><h1>Something went wrong</h1>';
        echo '<p>We could not complete this request. Please try again in a moment.</p>';
        echo '<p><a href="' . e(u('/')) . '">Back to the Quran reader</a></p>';
        echo self::debugBlock($e);
        echo '</body></html>';
    }

    private static function debugBlock(\Throwable $e): string
    {
        if (!Config::debug()) {
            return '';
        }

        $out  = '<pre style="text-align:left;max-width:60rem;margin:2rem auto;background:#111827;color:#F9FAFB;padding:1rem;border-radius:8px;overflow:auto;font-size:.8rem">';
        $out .= e(get_class($e) . ': ' . $e->getMessage()) . "\n";
        $out .= e($e->getFile() . ':' . $e->getLine()) . "\n\n";
        $out .= e($e->getTraceAsString());
        $out .= '</pre>';

        return $out;
    }
}
