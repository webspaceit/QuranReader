<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Auth\Auth;
use Quran\Http\ApiException;
use Quran\Http\Request;
use Quran\Repositories\MarkRepository;
use Quran\Repositories\TranslationRepository;
use Quran\Repositories\VerseRepository;
use Quran\Support\RateLimiter;

/**
 * Highlights and notes. Every endpoint needs a signed-in user; guest marks live
 * in the browser and are merged through `import`.
 *
 * `save` is a partial update: send `color` to set or clear the highlight, and
 * `note` to set or clear the note. Keys that are not sent are left untouched,
 * so the reader never has to read-modify-write.
 */
final class MarkController
{
    public function list(): array
    {
        $userId = $this->userId();
        $this->throttle('marks_read', (string) $userId, 300, 60);

        $translationId = TranslationRepository::resolve(Request::string('translation_id'));
        $items         = MarkRepository::groupRows(MarkRepository::forUser($userId, $translationId));

        return [
            'items'          => $items,
            'total'          => count($items),
            'highlights'     => MarkRepository::countHighlights($userId),
            'notes'          => MarkRepository::countNotes($userId),
            'colors'         => MarkRepository::COLORS,
            'translation_id' => $translationId,
        ];
    }

    public function save(): array
    {
        $userId = $this->userId();
        $this->throttle('marks_write', (string) $userId, 120, 60);

        [$surah, $verse] = $this->target();

        $colorSent = Request::has('color');
        $noteSent  = Request::has('note');

        if (!$colorSent && !$noteSent) {
            throw ApiException::invalid(['color' => 'Send a color, a note, or both.']);
        }

        if ($colorSent) {
            $color = Request::string('color');
            if ($color === '') {
                MarkRepository::clearColor($userId, $surah, $verse);
            } elseif (!MarkRepository::isValidColor($color)) {
                throw ApiException::invalid(['color' => 'Unknown highlight colour.']);
            } elseif (!MarkRepository::find($userId, $surah, $verse) && MarkRepository::countHighlights($userId) >= MarkRepository::MAX_HIGHLIGHTS) {
                throw new ApiException('You have reached the highlight limit (5000).', 422, 'limit_reached');
            } else {
                MarkRepository::setColor($userId, $surah, $verse, $color);
            }
        }

        if ($noteSent) {
            $note = Request::string('note');
            if (mb_strlen($note, 'UTF-8') > MarkRepository::MAX_NOTE_LENGTH) {
                throw ApiException::invalid(['note' => 'Keep notes under 1000 characters.']);
            }
            if ($note === '') {
                MarkRepository::clearNote($userId, $surah, $verse);
            } elseif (MarkRepository::find($userId, $surah, $verse) === null && MarkRepository::countNotes($userId) >= MarkRepository::MAX_NOTES) {
                throw new ApiException('You have reached the note limit (2000).', 422, 'limit_reached');
            } else {
                MarkRepository::setNote($userId, $surah, $verse, $note);
            }
        }

        return [
            'surah' => $surah,
            'verse' => $verse,
            'mark'  => MarkRepository::find($userId, $surah, $verse),
            'counts' => [
                'highlights' => MarkRepository::countHighlights($userId),
                'notes'      => MarkRepository::countNotes($userId),
            ],
        ];
    }

    public function remove(): array
    {
        $userId = $this->userId();
        $this->throttle('marks_write', (string) $userId, 120, 60);

        [$surah, $verse] = $this->target();

        $scope = Request::string('scope', 'all');
        if (!in_array($scope, ['all', 'color', 'note'], true)) {
            throw ApiException::invalid(['scope' => 'Scope must be all, color or note.']);
        }

        $removed = match ($scope) {
            'color' => MarkRepository::clearColor($userId, $surah, $verse),
            'note'  => MarkRepository::clearNote($userId, $surah, $verse),
            default => MarkRepository::clearAll($userId, $surah, $verse),
        };

        return [
            'removed' => $removed,
            'mark'    => MarkRepository::find($userId, $surah, $verse),
        ];
    }

    /** Merge marks collected while browsing as a guest. */
    public function import(): array
    {
        $userId = $this->userId();
        $this->throttle('marks_write', (string) $userId, 120, 60);

        $items = Request::input('items', []);
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items   = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($items)) {
            throw ApiException::invalid(['items' => 'Marks must be a list.']);
        }

        $clean = [];
        foreach (array_slice($items, 0, 500) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $surah = (int) ($item['surah'] ?? 0);
            $verse = (int) ($item['verse'] ?? 0);
            if ($surah < 1 || $surah > 114 || $verse < 1 || $verse > 300) {
                continue;
            }
            $color = isset($item['color']) ? mb_substr((string) $item['color'], 0, 20, 'UTF-8') : '';
            $note  = isset($item['note']) ? mb_substr((string) $item['note'], 0, MarkRepository::MAX_NOTE_LENGTH, 'UTF-8') : '';
            $clean[] = [
                'surah' => $surah,
                'verse' => $verse,
                'color' => MarkRepository::isValidColor($color) ? $color : '',
                'note'  => $note,
            ];
        }

        $imported = MarkRepository::importMany($userId, $clean);

        return [
            'imported'   => $imported,
            'total'      => MarkRepository::countHighlights($userId) + MarkRepository::countNotes($userId),
            'highlights' => MarkRepository::countHighlights($userId),
            'notes'      => MarkRepository::countNotes($userId),
        ];
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0:int,1:int} */
    private function target(): array
    {
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

        return [$surah, $verse];
    }

    private function userId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw ApiException::unauthorized();
        }
        return $id;
    }

    private function throttle(string $bucket, string $identifier, int $max, int $window): void
    {
        $result = RateLimiter::hit($bucket, $identifier, $max, $window);
        if (!$result['allowed']) {
            throw ApiException::tooManyRequests($result['retry_after']);
        }
    }
}
