import pathlib
p = pathlib.Path("app/src/Repositories/SiteSettingsRepository.php")
s = p.read_text(encoding="utf-8")

anchor = """    /**
     * Effective values: stored rows over the defaults, unknown keys dropped."""
helper = '''    /**
     * Render a footer value for HTML output.
     *
     * Everything is escaped, except one construct people actually need: a single
     * <a href="…">label</a> with an http(s) or mailto target. Anything else —
     * other tags, javascript: URLs, attributes — comes out as visible text.
     */
    public static function inline(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out = '';
        $offset = 0;

        if (preg_match_all('/<a\\s+href="([^"]{1,300})"\\s*>(.{1,200}?)<\\/a>/is', $decoded, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                [$whole, $at] = $match;
                $out .= self::escape(substr($decoded, $offset, $at - $offset));
                $out .= self::anchor($matches[1][$index][0], $matches[2][$index][0]);
                $offset = $at + strlen($whole);
            }
        }

        return $out . self::escape(substr($decoded, $offset));
    }

    private static function anchor(string $href, string $label): string
    {
        $href = trim($href);
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        $valid = $scheme === 'mailto'
            ? filter_var($href, FILTER_VALIDATE_EMAIL) !== false || str_contains($href, '@')
            : ($scheme === 'http' || $scheme === 'https') && filter_var($href, FILTER_VALIDATE_URL) !== false;

        if (!$valid) {
            // Show what was typed instead of silently dropping it.
            return self::escape('<a href="' . $href . '">' . $label . '</a>');
        }

        return '<a href="' . self::escape($href) . '" target="_blank" rel="noopener nofollow">'
            . self::escape($label) . '</a>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

''' + anchor
assert anchor in s
p.write_text(s.replace(anchor, helper, 1), encoding="utf-8")
print("inline renderer added")
