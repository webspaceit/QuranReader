<?php
declare(strict_types=1);

namespace Quran\Http;

use Quran\Support\Config;
use Quran\Support\Logger;

/**
 * cURL based HTTP client used by the content importer.
 *
 * TLS verification is always on. If a host has a broken CA store, point
 * `quran_api.cainfo` (or the CURL_CA_BUNDLE env var) at a cacert.pem file.
 */
final class CurlHttpClient implements HttpClientInterface
{
    private int $timeout;

    private int $retries;

    private string $userAgent;

    public function __construct(?int $timeout = null, ?int $retries = null, ?string $userAgent = null)
    {
        $this->timeout   = $timeout ?? (int) Config::get('quran_api.timeout', 25);
        $this->retries   = $retries ?? (int) Config::get('quran_api.retries', 2);
        $this->userAgent = $userAgent ?? (string) Config::get('quran_api.user_agent', 'QuranWebsite/1.0');
    }

    public function get(string $url): array
    {
        $attempt = 0;
        $last    = ['status' => 0, 'body' => '', 'error' => 'not attempted'];

        while ($attempt <= $this->retries) {
            $last = $this->request($url);

            if ($last['error'] === '' && $last['status'] >= 200 && $last['status'] < 300) {
                return $last;
            }

            // 4xx responses are not worth retrying (except 429).
            if ($last['status'] >= 400 && $last['status'] < 500 && $last['status'] !== 429) {
                return $last;
            }

            $attempt++;
            if ($attempt <= $this->retries) {
                $delay = 400000 * $attempt; // 0.4s, 0.8s
                usleep($delay);
            }
        }

        Logger::warning('HTTP request failed', ['url' => $url, 'status' => $last['status'], 'error' => $last['error']]);

        return $last;
    }

    /** @return array{status:int,body:string,error:string} */
    private function request(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'error' => 'curl_init failed'];
        }

        $options = [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 3,
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => $this->timeout,
            CURLOPT_USERAGENT       => $this->userAgent,
            CURLOPT_ENCODING        => '',
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_HTTPHEADER      => ['Accept: application/json'],
        ];

        $caInfo = (string) Config::get('quran_api.cainfo', '');
        if ($caInfo !== '' && is_file($caInfo)) {
            $options[CURLOPT_CAINFO] = $caInfo;
        }

        curl_setopt_array($ch, $options);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);

        return [
            'status' => $status,
            'body'   => is_string($body) ? $body : '',
            'error'  => $error,
        ];
    }
}
