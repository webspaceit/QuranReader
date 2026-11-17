<?php
declare(strict_types=1);

namespace Quran\Http\Controllers;

use Quran\Auth\Auth;
use Quran\Http\ApiException;
use Quran\Http\Request;
use Quran\Repositories\ReciterRepository;
use Quran\Repositories\SettingsRepository;
use Quran\Repositories\TranslationRepository;
use Quran\Support\RateLimiter;

final class SettingsController
{
    public function get(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw ApiException::unauthorized();
        }

        return [
            'settings' => SettingsRepository::get($userId),
            'options'  => self::options(),
        ];
    }

    public function save(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw ApiException::unauthorized();
        }

        $bucket = RateLimiter::hit('settings_write', (string) $userId, 120, 60);
        if (!$bucket['allowed']) {
            throw ApiException::tooManyRequests($bucket['retry_after']);
        }

        $input = Request::all();
        $values = [];

        if (array_key_exists('translation_id', $input)) {
            $translationId = (string) $input['translation_id'];
            if ($translationId !== '' && !TranslationRepository::exists($translationId)) {
                throw ApiException::invalid(['translation_id' => 'Unknown translation.']);
            }
            $values['translation_id'] = $translationId;
        }

        if (array_key_exists('reciter_id', $input)) {
            $reciterId = (string) $input['reciter_id'];
            if ($reciterId !== '' && !ReciterRepository::exists($reciterId)) {
                throw ApiException::invalid(['reciter_id' => 'Unknown reciter.']);
            }
            $values['reciter_id'] = $reciterId;
        }

        if (array_key_exists('arabic_font_size', $input)) {
            $size = (string) $input['arabic_font_size'];
            if (!in_array($size, SettingsRepository::FONT_SIZES, true)) {
                throw ApiException::invalid(['arabic_font_size' => 'Unsupported font size.']);
            }
            $values['arabic_font_size'] = $size;
        }

        if (array_key_exists('theme', $input)) {
            $theme = (string) $input['theme'];
            if (!in_array($theme, SettingsRepository::THEMES, true)) {
                throw ApiException::invalid(['theme' => 'Unsupported theme.']);
            }
            $values['theme'] = $theme;
        }

        if (array_key_exists('arabic_font', $input)) {
            $font = (string) $input['arabic_font'];
            if (!in_array($font, SettingsRepository::ARABIC_FONTS, true)) {
                throw ApiException::invalid(['arabic_font' => 'Unsupported Arabic typeface.']);
            }
            $values['arabic_font'] = $font;
        }

        if (array_key_exists('playing_style', $input)) {
            $style = (string) $input['playing_style'];
            if (!in_array($style, SettingsRepository::PLAYING_STYLES, true)) {
                throw ApiException::invalid(['playing_style' => 'Unsupported reciting-verse style.']);
            }
            $values['playing_style'] = $style;
        }

        return ['settings' => SettingsRepository::save($userId, $values)];
    }

    /** @return array<string,mixed> */
    public static function options(): array
    {
        return [
            'translations' => TranslationRepository::all(),
            'reciters'     => ReciterRepository::all(),
            'font_sizes'   => SettingsRepository::FONT_SIZES,
            'themes'       => SettingsRepository::THEMES,
            'playing_styles' => SettingsRepository::PLAYING_STYLES,
            'arabic_fonts'   => SettingsRepository::ARABIC_FONTS,
        ];
    }
}
