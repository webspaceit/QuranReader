import pathlib
p = pathlib.Path("qa/run-tests.php")
s = p.read_text(encoding="utf-8")
old = """$r = call('GET', '/');
check('theme is resolved before the first stylesheet',
    strpos($r['body'], 'quran_theme=') < strpos($r['body'], 'assets/css/app.css'), 'inline prepaint order');
check('page links the theme-aware manifest', str_contains($r['body'], 'manifest.php'));"""
new = """// A separate variable on purpose: $r still holds the settings page the two
// checks below read.
$home = call('GET', '/');
check('theme is resolved before the first stylesheet',
    strpos($home['body'], 'quran_theme=') < strpos($home['body'], 'assets/css/app.css'), 'inline prepaint order');
check('page links the theme-aware manifest', str_contains($home['body'], 'manifest.php'));"""
assert old in s
s = s.replace(old, new, 1)
p.write_text(s, encoding="utf-8")
print("fixed")
