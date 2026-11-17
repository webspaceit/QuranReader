import pathlib
p = pathlib.Path("qa/run-tests.php")
s = p.read_text(encoding="utf-8")
old = """check('page links the theme-aware manifest', str_contains($home['body'], 'manifest.php'));
check('settings offers the offline download', str_contains($r['body'], 'data-offline-download'));"""
new = """check('page links the theme-aware manifest', str_contains($home['body'], 'manifest.php'));

$r = call('GET', '/settings.php');
check('settings offers the offline download', str_contains($r['body'], 'data-offline-download'));"""
assert old in s
s = s.replace(old, new, 1)
p.write_text(s, encoding="utf-8")
print("added explicit settings fetch")
