<?php
declare(strict_types=1);

namespace Quran\Quran;

use Quran\Http\HttpClientInterface;
use Quran\Support\Config;

/**
 * Client for the public Quran content API (alquran.cloud).
 *
 * Only the importer talks to the API. Visitors are served from MySQL, so page
 * views never depend on a third-party service being reachable.
 */
final class QuranApiClient
{
    private HttpClientInterface $http;

    private string $baseUrl;

    public function __construct(HttpClientInterface $http, ?string $baseUrl = null)
    {
        $this->http    = $http;
        $this->baseUrl = rtrim($baseUrl ?? (string) Config::get('quran_api.base_url', 'https://api.alquran.cloud/v1'), '/');
    }

    /**
     * Chapter list (114 entries).
     *
     * @return array<int,array<string,mixed>>
     */
    public function chapters(): array
    {
        $payload = $this->getJson('/surah');
        $data    = $payload['data'] ?? null;

        if (!is_array($data) || $data === []) {
            throw new \RuntimeException('Chapter list was empty or malformed.');
        }

        return array_values($data);
    }

    /**
     * Translation editions catalog.
     *
     * @return array<string,array<string,mixed>> keyed by identifier
     */
    public function translationCatalog(): array
    {
        $payload = $this->getJson('/edition?format=text&type=translation');
        $data    = $payload['data'] ?? null;
        $out     = [];

        if (!is_array($data)) {
            return $out;
        }

        foreach ($data as $edition) {
            if (!is_array($edition) || !isset($edition['identifier'])) {
                continue;
            }
            $out[(string) $edition['identifier']] = $edition;
        }

        return $out;
    }

    /**
     * Arabic text plus one or more translation editions for a single surah.
     *
     * @param array<int,string> $translations
     * @return array{arabic:array<int,array<string,mixed>>,translations:array<string,array<int,array<string,mixed>>>}
     */
    public function surah(int $number, array $translations = []): array
    {
        if ($number < 1 || $number > 114) {
            throw new \InvalidArgumentException('Surah number out of range: ' . $number);
        }

        $editions = array_merge(['quran-uthmani'], $translations);
        $path     = '/surah/' . $number . '/editions/' . implode(',', array_map('rawurlencode', $editions));
        $payload  = $this->getJson($path);
        $data     = $payload['data'] ?? null;

        if (!is_array($data)) {
            throw new \RuntimeException('Unexpected response for surah ' . $number . '.');
        }

        $arabic       = [];
        $translated   = [];

        foreach ($data as $edition) {
            if (!is_array($edition)) {
                continue;
            }
            $identifier = (string) ($edition['edition']['identifier'] ?? '');
            $ayahs      = $edition['ayahs'] ?? [];
            if (!is_array($ayahs)) {
                continue;
            }

            if ($identifier === 'quran-uthmani') {
                $arabic = array_values($ayahs);
                continue;
            }
            if ($identifier !== '') {
                $translated[$identifier] = array_values($ayahs);
            }
        }

        if ($arabic === []) {
            throw new \RuntimeException('Arabic text missing for surah ' . $number . '.');
        }

        return ['arabic' => $arabic, 'translations' => $translated];
    }

    /**
     * Optional enrichment from the Quran.com v4 API: revelation order,
     * bismillah flag and the simplified transliterated name.
     *
     * Failure here is not fatal - the importer keeps working without it.
     *
     * @return array<int,array<string,mixed>> keyed by chapter id
     */
    public function chapterEnrichment(): array
    {
        try {
            $response = $this->http->get('https://api.quran.com/api/v4/chapters?language=en');
        } catch (\Throwable $e) {
            return [];
        }

        if ($response['status'] !== 200 || $response['body'] === '') {
            return [];
        }

        $decoded = json_decode($response['body'], true);
        $list    = is_array($decoded) ? ($decoded['chapters'] ?? null) : null;
        if (!is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $chapter) {
            if (!isset($chapter['id'])) {
                continue;
            }
            $out[(int) $chapter['id']] = $chapter;
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function getJson(string $path): array
    {
        $url      = $this->baseUrl . $path;
        $response = $this->http->get($url);

        if ($response['status'] !== 200) {
            throw new \RuntimeException(sprintf(
                'Quran API request failed (%s): HTTP %d %s',
                $url,
                $response['status'],
                $response['error'] !== '' ? '- ' . $response['error'] : ''
            ));
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Quran API returned invalid JSON for ' . $url);
        }

        // alquran.cloud always answers 200 with a status/code envelope.
        $status = strtoupper((string) ($decoded['status'] ?? 'OK'));
        if ($status !== 'OK') {
            throw new \RuntimeException('Quran API error for ' . $url . ': ' . (string) ($decoded['status'] ?? 'unknown'));
        }

        return $decoded;
    }
}
