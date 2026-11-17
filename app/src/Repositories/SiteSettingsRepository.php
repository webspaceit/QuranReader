<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Config;
use Quran\Support\Database;

/**
 * Site-wide settings — the handful of values that belong to the site rather
 * than to a signed-in reader. Only a super admin can change them.
 *
 * Everything here falls back to a shipped default, so a fresh install renders
 * a complete footer before anyone visits the admin page.
 */
final class SiteSettingsRepository
{
    /** Editable keys. Anything else in the table is ignored on read. */
    public const KEYS = [
        'footer_tagline',
        'footer_copyright_holder',
        'footer_copyright_year',
        'footer_note',
    ];

    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'footer_tagline'          => 'A fast, private Quran reader. No advertisements, no tracking, and your data stays yours.',
            'footer_copyright_holder'  => (string) Config::get('app.name', 'Quran Reader'),
            'footer_copyright_year'    => 'auto',
            'footer_note'             => '',
        ];
    }

    /**
     * Effective values: stored rows over the defaults, unknown keys dropped.
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $values = self::defaults();

        try {
            foreach (Database::select('SELECT setting_key, setting_value FROM site_settings') as $row) {
                $key = (string) $row['setting_key'];
                if (in_array($key, self::KEYS, true) && $row['setting_value'] !== null) {
                    $values[$key] = (string) $row['setting_value'];
                }
            }
        } catch (\Throwable $error) {
            // A database that predates this table still renders the footer.
            return self::$cache = $values;
        }

        return self::$cache = $values;
    }

    public static function get(string $key, string $fallback = ''): string
    {
        $values = self::all();
        return $values[$key] ?? $fallback;
    }

    /**
     * Store the editable values. Missing keys keep their current value.
     *
     * @param array<string,string|null> $values
     */
    public static function put(array $values, ?int $userId = null): void
    {
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            Database::execute(
                'INSERT INTO site_settings (setting_key, setting_value, updated_by)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
                [$key, (string) $values[$key], $userId]
            );
        }

        self::$cache = null;
    }

    /**
     * The year to print: "auto" follows the clock, anything else must be a
     * plausible four-digit year.
     *
     * @param array<string,string>|null $values
     */
    public static function copyrightYear(?array $values = null): string
    {
        $values ??= self::all();
        $year = $values['footer_copyright_year'] ?? 'auto';

        if ($year === 'auto' || $year === '') {
            return date('Y');
        }

        return preg_match('/^\d{4}$/', $year) === 1 ? $year : date('Y');
    }
}
