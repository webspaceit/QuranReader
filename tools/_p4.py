import pathlib
p = pathlib.Path("public_html/assets/js/app.js")
s = p.read_text(encoding="utf-8")
old = """        root.setAttribute('data-theme', resolved);
        root.setAttribute('data-theme-preference', pref);
        store.setString('theme', pref);"""
new = """        root.setAttribute('data-theme', resolved);
        root.setAttribute('data-theme-preference', pref);
        // The manifest reads this to pick the launch splash colour.
        try {
            document.cookie = 'quran_theme=' + resolved + '; path=/; max-age=31536000; samesite=lax';
        } catch (e) { /* ignore */ }
        store.setString('theme', pref);"""
assert old in s
s = s.replace(old, new, 1)
p.write_text(s, encoding="utf-8")
print("app.js patched")
