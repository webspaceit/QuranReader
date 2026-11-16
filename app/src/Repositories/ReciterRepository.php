<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Database;

final class ReciterRepository
{
    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::select(
            'SELECT id, name, english_name, style, bitrate, audio_base, is_default
             FROM reciters ORDER BY sort_order, id'
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(string $id): ?array
    {
        return Database::first('SELECT * FROM reciters WHERE id = ?', [$id]);
    }

    public static function exists(string $id): bool
    {
        return (int) Database::value('SELECT COUNT(*) FROM reciters WHERE id = ?', [$id]) > 0;
    }

    public static function defaultId(): string
    {
        $fromDb = Database::value('SELECT id FROM reciters WHERE is_default = 1 ORDER BY sort_order LIMIT 1');
        if (is_string($fromDb) && $fromDb !== '') {
            return $fromDb;
        }
        $first = Database::value('SELECT id FROM reciters ORDER BY sort_order LIMIT 1');
        return is_string($first) ? $first : '';
    }

    public static function resolve(?string $requested): string
    {
        if (is_string($requested) && $requested !== '' && self::exists($requested)) {
            return $requested;
        }
        return self::defaultId();
    }

    /** @return array<string,mixed>|null */
    public static function current(?string $requested): ?array
    {
        $id = self::resolve($requested);
        return $id === '' ? null : self::find($id);
    }
}
