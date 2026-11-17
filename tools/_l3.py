import pathlib
p = pathlib.Path("qa/run-tests.php")
s = p.read_text(encoding="utf-8")
anchor = """// Restore the shipped defaults, then prove a demoted account is shut out."""
block = """// One inline link is allowed in footer text; everything else stays text.
$r = call('POST', '/admin.php', [
    '_csrf'                   => $tok,
    'footer_tagline'          => 'A fast, private Quran reader.',
    'footer_copyright_holder' => 'Quran Reader. All rights reserved. Developed by <a href="https://webspaceit.com">Web Space IT</a>',
    'footer_note'             => '',
    'footer_copyright_year'   => 'auto',
], $tok);
$r = call('GET', '/');
check('an anchor in the copyright line renders as a link',
    str_contains($r['body'], 'href="https://webspaceit.com"')
    && str_contains($r['body'], '>Web Space IT</a>')
    && str_contains($r['body'], 'rel="noopener nofollow"'));
check('the link keeps the surrounding sentence',
    str_contains($r['body'], 'Developed by <a href="https://webspaceit.com"'));

$r = call('POST', '/admin.php', [
    '_csrf'                   => $tok,
    'footer_tagline'          => 'Read it <a href="javascript:alert(1)">here</a>',
    'footer_copyright_holder' => 'Quran Reader',
    'footer_note'             => '',
    'footer_copyright_year'   => 'auto',
], $tok);
$r = call('GET', '/');
check('a javascript: link is refused',
    !str_contains($r['body'], 'href="javascript')
    && str_contains($r['body'], '&lt;a href=&quot;javascript:alert(1)&quot;&gt;here&lt;/a&gt;'));

""" + anchor
assert anchor in s
p.write_text(s.replace(anchor, block, 1), encoding="utf-8")
print("tests added")
