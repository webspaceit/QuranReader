import pathlib
p = pathlib.Path("qa/run-tests.php")
s = p.read_text(encoding="utf-8")
anchor = """check('settings links the manifest', str_contains($r['body'], 'rel="manifest"'));"""
block = anchor + """

// The launch splash must follow the reader's theme, and the theme must be
// resolved before any stylesheet can paint.
$r = call('GET', '/manifest.php');
check('manifest endpoint is served', $r['status'] === 200, (string) $r['status']);
$dyn = json_decode($r['body'], true);
check('manifest endpoint returns the same app', is_array($dyn) && ($dyn['start_url'] ?? '') === './'
    && count($dyn['icons'] ?? []) === 3);
check('manifest splash follows the theme (light default)',
    ($dyn['background_color'] ?? '') === '#fbf9f4', (string) ($dyn['background_color'] ?? ''));
$r = call('GET', '/manifest.php', [], null, ['Cookie' => 'quran_theme=dark']);
$dynDark = json_decode($r['body'], true);
check('manifest splash follows the theme (dark cookie)',
    ($dynDark['background_color'] ?? '') === '#0f1512'
    && ($dynDark['theme_color'] ?? '') === '#0f1512',
    (string) ($dynDark['background_color'] ?? ''));
$r = call('GET', '/');
check('theme is resolved before the first stylesheet',
    strpos($r['body'], 'quran_theme=') < strpos($r['body'], 'assets/css/app.css'), 'inline prepaint order');
check('page links the theme-aware manifest', str_contains($r['body'], 'manifest.php'));"""
assert anchor in s
s = s.replace(anchor, block, 1)
p.write_text(s, encoding="utf-8")
print("tests added")
