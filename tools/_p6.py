import pathlib
p = pathlib.Path("qa/run-tests.php")
s = p.read_text(encoding="utf-8")
old = """$r = call('GET', '/manifest.php', [], null, ['Cookie' => 'quran_theme=dark']);
$dynDark = json_decode($r['body'], true);"""
new = """$probe = escapeshellarg($php) . ' ' . escapeshellarg($root . '/qa/manifest-probe.php') . ' dark';
$darkRaw = (string) shell_exec($probe);
$dynDark = json_decode($darkRaw, true);"""
assert old in s, "cookie call not found"
s = s.replace(old, new, 1)
p.write_text(s, encoding="utf-8")
print("patched")
