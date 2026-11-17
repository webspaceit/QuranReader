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
     * The footer is plain text by design: values are escaped on output, so HTML
     * tags show up as text. Character entities are still decoded, because
     * people reasonably type "&copy;" or "&mdash;" and expect the glyph.
     */
    public static function normalise(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Nothing is stripped: the text is stored as typed and escaped on
        // output, so a stray tag shows up as visible characters instead of
        // silently eating somebody's words.
        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }

    /**
     * The template prints "© <year> <holder>", so a © typed into the holder is
     * redundant. Drop it rather than rendering "© 2026 © Name".
     */
    public static function normaliseHolder(string $value): string
    {
        $clean = self::normalise($value);
        return trim(preg_replace('/^[\s\x{00A9}\x{00AE}]+/u', '', $clean) ?? $clean);
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
                    // Normalise on read as well, so rows stored before this
                    // rule (e.g. "&copy;Name") render correctly without a fix-up.
                    $values[$key] = $key === 'footer_copyright_holder'
                        ? self::normaliseHolder((string) $row['setting_value'])
                        : self::normalise((string) $row['setting_value']);
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

            $value = $key === 'footer_copyright_holder'
                ? self::normaliseHolder((string) $values[$key])
                : self::normalise((string) $values[$key]);

            Database::execute(
                'INSERT INTO site_settings (setting_key, setting_value, updated_by)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
                [$key, $value, $userId]
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
