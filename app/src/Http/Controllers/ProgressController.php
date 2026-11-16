<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Auth\Auth;
use Quran\Http\ApiException;
use Quran\Http\Request;
use Quran\Repositories\ProgressRepository;
use Quran\Repositories\VerseRepository;
use Quran\Support\RateLimiter;

final class ProgressController
{
    public function get(): array
    {
        $userId = $this->userId();
        $row    = ProgressRepository::get($userId);

        if ($row === null) {
            return ['progress' => null];
        }

        return [
            'progress' => [
                'surah'      => (int) $row['surah_number'],
                'verse'      => (int) $row['verse_number'],
                'verse_key'  => $row['surah_number'] . ':' . $row['verse_number'],
                'surah_name' => (string) ($row['name_simple'] ?? ''),
                'updated_at' => (string) $row['updated_at'],
            ],
        ];
    }

    public function save(): array
    {
        $userId = $this->userId();
        $bucket = RateLimiter::hit('progress_write', (string) $userId, 240, 60);
        if (!$bucket['allowed']) {
            throw ApiException::tooManyRequests($bucket['retry_after']);
        }

        $surah = Request::int('surah');
        $verse = Request::int('verse');

        if ($surah < 1 || $surah > 114) {
            throw ApiException::invalid(['surah' => 'Surah must be between 1 and 114.']);
        }
        if ($verse < 1 || $verse > 300) {
            throw ApiException::invalid(['verse' => 'Verse number is out of range.']);
        }
        if (!VerseRepository::exists($surah, $verse)) {
            throw ApiException::invalid(['verse' => 'That verse does not exist.']);
        }

        ProgressRepository::save($userId, $surah, $verse);

        return ['saved' => true, 'surah' => $surah, 'verse' => $verse];
    }

    private function userId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw ApiException::unauthorized();
        }
        return $id;
    }
}
