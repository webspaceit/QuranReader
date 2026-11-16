<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Auth\Auth;
use Quran\Http\ApiException;
use Quran\Http\Request;
use Quran\Repositories\BookmarkRepository;
use Quran\Repositories\TranslationRepository;
use Quran\Repositories\VerseRepository;
use Quran\Support\RateLimiter;

/**
 * Bookmark endpoints. All of them require a signed-in user; guest bookmarks
 * live in the browser and are merged through `import`.
 */
final class BookmarkController
{
    public function list(): array
    {
        $userId = $this->userId();
        $this->throttle('bookmarks_read', (string) $userId, 300, 60);

        $translationId = TranslationRepository::resolve(Request::string('translation_id'));

        $rows = BookmarkRepository::forUser($userId, $translationId);
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id'          => (int) $row['id'],
                'surah'       => (int) $row['surah_number'],
                'verse'       => (int) $row['verse_number'],
                'verse_key'   => $row['surah_number'] . ':' . $row['verse_number'],
                'surah_name'  => (string) ($row['name_simple'] ?? ''),
                'arabic'      => (string) ($row['text_uthmani'] ?? ''),
                'translation' => (string) ($row['translation_text'] ?? ''),
                'note'        => $row['note'],
                'created_at'  => (string) $row['created_at'],
            ];
        }

        return ['items' => $items, 'total' => count($items), 'translation_id' => $translationId];
    }

    public function add(): array
    {
        $userId = $this->userId();
        $this->throttle('bookmarks_write', (string) $userId, 120, 60);

        $surah = Request::int('surah');
        $verse = Request::int('verse');
        $this->assertVerse($surah, $verse);

        $note = trim(Request::string('note'));
        if (mb_strlen($note) > 1000) {
            throw ApiException::invalid(['note' => 'Keep notes under 1000 characters.']);
        }
        $note = $note === '' ? null : $note;

        $existing = BookmarkRepository::exists($userId, $surah, $verse);
        if ($existing) {
            if ($note !== null) {
                BookmarkRepository::add($userId, $surah, $verse, $note);
            }
            return ['saved' => true, 'already_saved' => true, 'surah' => $surah, 'verse' => $verse, 'note' => $note];
        }

        if (BookmarkRepository::countForUser($userId) >= 2000) {
            throw new ApiException('You have reached the bookmark limit (2000).', 422, 'limit_reached');
        }

        BookmarkRepository::add($userId, $surah, $verse, $note);

        return ['saved' => true, 'already_saved' => false, 'surah' => $surah, 'verse' => $verse, 'note' => $note];
    }

    public function remove(): array
    {
        $userId = $this->userId();
        $this->throttle('bookmarks_write', (string) $userId, 120, 60);

        $id    = Request::int('id');
        $surah = Request::int('surah');
        $verse = Request::int('verse');

        if ($id > 0) {
            $removed = BookmarkRepository::removeById($userId, $id);
        } elseif ($surah > 0 && $verse > 0) {
            $removed = BookmarkRepository::remove($userId, $surah, $verse);
        } else {
            throw ApiException::invalid(['verse' => 'Provide a bookmark id or a surah and verse number.']);
        }

        return ['removed' => $removed];
    }

    /**
     * Merge bookmarks collected while browsing as a guest.
     */
    public function import(): array
    {
        $userId = $this->userId();
        $this->throttle('bookmarks_write', (string) $userId, 120, 60);

        $items = Request::input('items', []);
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items   = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($items)) {
            throw ApiException::invalid(['items' => 'Bookmarks must be a list.']);
        }

        $clean = [];
        foreach (array_slice($items, 0, 500) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $surah = (int) ($item['surah'] ?? 0);
            $verse = (int) ($item['verse'] ?? 0);
            if ($surah >= 1 && $surah <= 114 && $verse >= 1 && $verse <= 300) {
                $clean[] = ['surah' => $surah, 'verse' => $verse];
            }
        }

        $imported = BookmarkRepository::importMany($userId, $clean);

        return ['imported' => $imported, 'total' => BookmarkRepository::countForUser($userId)];
    }

    private function userId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw ApiException::unauthorized();
        }
        return $id;
    }

    private function assertVerse(int $surah, int $verse): void
    {
        if ($surah < 1 || $surah > 114) {
            throw ApiException::invalid(['surah' => 'Surah must be between 1 and 114.']);
        }
        if ($verse < 1 || $verse > 300) {
            throw ApiException::invalid(['verse' => 'Verse number is out of range.']);
        }
        if (!VerseRepository::exists($surah, $verse)) {
            throw ApiException::invalid(['verse' => 'That verse does not exist.']);
        }
    }

    private function throttle(string $bucket, string $identifier, int $max, int $window): void
    {
        $result = RateLimiter::hit($bucket, $identifier, $max, $window);
        if (!$result['allowed']) {
            throw ApiException::tooManyRequests($result['retry_after']);
        }
    }
}
