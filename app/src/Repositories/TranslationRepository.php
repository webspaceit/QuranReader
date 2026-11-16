<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Config;
use Quran\Support\Database;

final class TranslationRepository
{
    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::select(
            'SELECT id, name, english_name, author_name, language_code, language_name, direction, is_default
             FROM translations ORDER BY is_default DESC, language_code, name'
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(string $id): ?array
    {
        return Database::first('SELECT * FROM translations WHERE id = ?', [$id]);
    }

    public static function exists(string $id): bool
    {
        return (int) Database::value('SELECT COUNT(*) FROM translations WHERE id = ?', [$id]) > 0;
    }

    /** Default translation id, falling back to the first imported edition. */
    public static function defaultId(): string
    {
        $configured = (string) Config::get('content.default_translation', '');
        if ($configured !== '' && self::exists($configured)) {
            return $configured;
        }

        $fromDb = Database::value('SELECT id FROM translations WHERE is_default = 1 LIMIT 1');
        if (is_string($fromDb) && $fromDb !== '') {
            return $fromDb;
        }

        $first = Database::value('SELECT id FROM translations ORDER BY id LIMIT 1');
        return is_string($first) ? $first : '';
    }

    /**
     * Resolve a requested translation id, guarding against unknown values.
     */
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
