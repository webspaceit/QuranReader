<?php
declare(strict_types=1);

namespace Quran\Support;

/**
 * Text helpers for Arabic and Latin content.
 *
 * Quranic Arabic is written with diacritics (harakat) and several forms of
 * alef/ya. Nobody types those when searching, so both the stored text and the
 * query are reduced to a "plain" form before comparison.
 */
final class Text
{
    /** Diacritics, Quranic annotation marks and formatting characters. */
    private const ARABIC_NOISE = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{08D3}-\x{08FF}\x{0640}\x{0651}\x{FEFF}\x{200B}-\x{200F}]/u';

    /** Alef, waw-hamza, ya-hamza, alef-maqsura and ta-marbuta unifications. */
    private const ARABIC_FORMS = [
        "\u{0622}" => "\u{0627}", // آ
        "\u{0623}" => "\u{0627}", // أ
        "\u{0625}" => "\u{0627}", // إ
        "\u{0671}" => "\u{0627}", // ٱ
        "\u{0649}" => "\u{064A}", // ى
        "\u{0626}" => "\u{064A}", // ئ
        "\u{0624}" => "\u{0648}", // ؤ
        "\u{0629}" => "\u{0647}", // ة
    ];

    public static function normalizeArabic(string $text): string
    {
        $text = (string) preg_replace(self::ARABIC_NOISE, '', $text);
        $text = strtr($text, self::ARABIC_FORMS);
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    public static function normalizeLatin(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Keep letters and digits from any script, everything else is a separator.
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /** True when the query contains Arabic letters. */
    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /** Plain-text excerpt for search result previews. */
    public static function excerpt(string $html, int $length = 160): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
    }

    /**
     * Wrap every match of $query in <mark> and escape the rest of $text.
     *
     * Highlighting is diacritic-insensitive for Arabic (the same folding used by
     * the SQL search) and case-insensitive for Latin. The returned string is
     * HTML-safe: every fragment is escaped before the <mark> tags are added.
     */
    public static function highlight(string $text, string $query): string
    {
        $ranges = self::matchRanges($text, $query);
        if ($ranges === []) {
            return self::escape($text);
        }

        $out     = '';
        $cursor  = 0;
        foreach ($ranges as [$start, $end]) {
            if ($start < $cursor) {
                continue;
            }
            $out    .= self::escape(mb_substr($text, $cursor, $start - $cursor, 'UTF-8'));
            $out    .= '<mark>' . self::escape(mb_substr($text, $start, $end - $start, 'UTF-8')) . '</mark>';
            $cursor  = $end;
        }

        return $out . self::escape(mb_substr($text, $cursor, null, 'UTF-8'));
    }

    /**
     * Character ranges (start inclusive, end exclusive) where the query matches.
     *
     * @return array<int,array{0:int,1:int}>
     */
    public static function matchRanges(string $text, string $query, int $maxRanges = 40): array
    {
        $query = trim($query);
        if ($query === '' || $text === '') {
            return [];
        }

        $ranges = self::containsArabic($query)
            ? self::arabicRanges($text, $query)
            : self::latinRanges($text, $query);

        return array_slice($ranges, 0, $maxRanges);
    }

    /** @return array<int,array{0:int,1:int}> */
    private static function arabicRanges(string $text, string $query): array
    {
        $needle = self::normalizeArabic($query);
        if ($needle === '') {
            return [];
        }

        // Fold the text one character at a time and remember which original
        // character produced each plain character, so matches can be mapped
        // back onto the fully voweled text.
        $chars     = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $plain     = '';
        $origin    = [];
        foreach ($chars as $index => $char) {
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $origin[] = $index;
                $plain   .= ' ';
                continue;
            }
            if (preg_match(self::ARABIC_NOISE, $char) === 1) {
                continue;
            }
            $folded = strtr($char, self::ARABIC_FORMS);
            foreach (preg_split('//u', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
                $origin[] = $index;
                $plain   .= $piece;
            }
        }

        $needleLength = mb_strlen($needle, 'UTF-8');
        $plainLength  = mb_strlen($plain, 'UTF-8');
        if ($needleLength === 0 || $plainLength < $needleLength) {
            return [];
        }

        $ranges = [];
        $offset = 0;
        while ($offset <= $plainLength - $needleLength) {
            $position = mb_strpos($plain, $needle, $offset, 'UTF-8');
            if ($position === false) {
                break;
            }
            $start = $origin[$position];
            $end   = $origin[$position + $needleLength - 1] + 1;
            // Keep trailing diacritics inside the highlight so the marked word
            // stays visually intact.
            while (isset($chars[$end]) && preg_match(self::ARABIC_NOISE, $chars[$end]) === 1) {
                $end++;
            }
            $ranges[] = [$start, $end];
            $offset   = $position + $needleLength;
        }

        return $ranges;
    }

    /** @return array<int,array{0:int,1:int}> */
    private static function latinRanges(string $text, string $query): array
    {
        $needle       = trim($query);
        $needleLength = mb_strlen($needle, 'UTF-8');
        if ($needleLength < 2) {
            return [];
        }

        $ranges = [];
        $offset = 0;
        while (($position = mb_stripos($text, $needle, $offset, 'UTF-8')) !== false) {
            $before = $position > 0 ? mb_substr($text, $position - 1, 1, 'UTF-8') : '';
            $after  = mb_substr($text, $position + $needleLength, 1, 'UTF-8');
            $glued  = ($before !== '' && preg_match('/[\p{L}\p{N}]/u', $before) === 1)
                || ($after !== '' && preg_match('/[\p{L}\p{N}]/u', $after) === 1);

            if (!$glued) {
                $ranges[] = [$position, $position + $needleLength];
                $offset   = $position + $needleLength;
            } else {
                $offset = $position + 1;
            }
        }

        return $ranges;
    }

    /** Remove the basmala prefix that some editions repeat inside verse 1. */
    public static function stripBasmala(string $text, string $basmala): string
    {
        if ($basmala === '') {
            return $text;
        }

        if (strncmp($text, $basmala, strlen($basmala)) === 0) {
            return ltrim(substr($text, strlen($basmala)));
        }

        // Fall back to a normalised comparison (editions differ in diacritics).
        $plainText     = self::normalizeArabic($text);
        $plainBasmala  = self::normalizeArabic($basmala);
        if ($plainBasmala !== '' && strncmp($plainText, $plainBasmala, mb_strlen($plainBasmala, 'UTF-8')) === 0) {
            $words = preg_split('/\s+/u', $text) ?: [];
            $count = count(preg_split('/\s+/u', $plainBasmala) ?: []);
            return ltrim(implode(' ', array_slice($words, $count)));
        }

        return $text;
    }

    /** Escape for HTML output. */
    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Number of Arabic-Indic digits used for verse end markers. */
    public static function toArabicDigits(int $number): string
    {
        $digits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $out    = '';
        foreach (str_split((string) $number) as $digit) {
            $out .= $digits[(int) $digit] ?? $digit;
        }
        return $out;
    }
}
