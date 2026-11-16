<?php
declare(strict_types=1);

namespace Quran\Repositories;

use Quran\Support\Config;
use Quran\Support\Database;

final class SettingsRepository
{
    public const FONT_SIZES = ['small', 'medium', 'large', 'xlarge'];

    public const THEMES = ['light', 'dark', 'system'];

    /** How the verse being recited is marked in the reader. */
    public const PLAYING_STYLES = ['card', 'border', 'bar', 'off'];

    /**
     * User settings merged over the site defaults.
     *
     * @return array{translation_id:string,reciter_id:string,arabic_font_size:string,theme:string,playing_style:string}
     */
    public static function get(int $userId): array
    {
        $row = Database::first('SELECT * FROM user_settings WHERE user_id = ?', [$userId]);

        $defaults = self::defaults();

        if ($row === null) {
            return $defaults;
        }

        return [
            'translation_id'   => TranslationRepository::resolve($row['translation_id'] ?? null) ?: $defaults['translation_id'],
            'reciter_id'       => ReciterRepository::resolve($row['reciter_id'] ?? null) ?: $defaults['reciter_id'],
            'arabic_font_size' => in_array($row['arabic_font_size'] ?? '', self::FONT_SIZES, true)
                ? (string) $row['arabic_font_size']
                : $defaults['arabic_font_size'],
            'theme'            => in_array($row['theme'] ?? '', self::THEMES, true)
                ? (string) $row['theme']
                : $defaults['theme'],
            'playing_style'    => in_array($row['playing_style'] ?? '', self::PLAYING_STYLES, true)
                ? (string) $row['playing_style']
                : $defaults['playing_style'],
        ];
    }

    /** @return array{translation_id:string,reciter_id:string,arabic_font_size:string,theme:string,playing_style:string} */
    public static function defaults(): array
    {
        return [
            'translation_id'   => TranslationRepository::defaultId(),
            'reciter_id'       => ReciterRepository::defaultId(),
            'arabic_font_size' => (string) Config::get('content.default_font_size', 'medium'),
            'theme'            => 'system',
            'playing_style'    => 'card',
        ];
    }

    /**
     * @param array<string,string|null> $values
     * @return array{translation_id:string,reciter_id:string,arabic_font_size:string,theme:string,playing_style:string}
     */
    public static function save(int $userId, array $values): array
    {
        $current = self::get($userId);

        $translation = isset($values['translation_id'])
            ? TranslationRepository::resolve((string) $values['translation_id'])
            : $current['translation_id'];
        $reciter = isset($values['reciter_id'])
            ? ReciterRepository::resolve((string) $values['reciter_id'])
            : $current['reciter_id'];
        $fontSize = isset($values['arabic_font_size']) && in_array($values['arabic_font_size'], self::FONT_SIZES, true)
            ? (string) $values['arabic_font_size']
            : $current['arabic_font_size'];
        $theme = isset($values['theme']) && in_array($values['theme'], self::THEMES, true)
            ? (string) $values['theme']
            : $current['theme'];
        $playingStyle = isset($values['playing_style']) && in_array($values['playing_style'], self::PLAYING_STYLES, true)
            ? (string) $values['playing_style']
            : $current['playing_style'];

        Database::execute(
            'INSERT INTO user_settings (user_id, translation_id, reciter_id, arabic_font_size, theme, playing_style)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE translation_id = VALUES(translation_id), reciter_id = VALUES(reciter_id),
                 arabic_font_size = VALUES(arabic_font_size), theme = VALUES(theme), playing_style = VALUES(playing_style)',
            [$userId, $translation, $reciter, $fontSize, $theme, $playingStyle]
        );

        return [
            'translation_id'   => $translation,
            'reciter_id'       => $reciter,
            'arabic_font_size' => $fontSize,
            'theme'            => $theme,
            'playing_style'    => $playingStyle,
        ];
    }
}
