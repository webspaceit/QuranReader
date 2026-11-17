import pathlib
p = pathlib.Path("app/views/layout.php")
s = p.read_text(encoding="utf-8")

old_link = """    <link rel="manifest" href="<?= e(u('manifest.webmanifest')) ?>">"""
new_link = """    <link rel="manifest" href="<?= e(u('manifest.php')) ?>">"""
assert old_link in s
s = s.replace(old_link, new_link, 1)

old_css = """    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">"""
prepaint = """    <script>
        /* Resolve the theme before the first paint. theme.js does the same, but it
           is a separate request: on a cold start of the installed app the browser
           can paint the light tokens while it is still in flight, which reads as a
           white blink against a dark reader. */
        (function () {
            var root = document.documentElement;
            var pref = '';
            try { pref = window.localStorage.getItem('quran.theme') || ''; } catch (e) { /* storage blocked */ }
            pref = pref || root.getAttribute('data-theme') || 'system';
            var dark = pref === 'dark' || (pref !== 'light'
                && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.setAttribute('data-theme', dark ? 'dark' : 'light');
            root.setAttribute('data-theme-preference', pref);
            /* The manifest builds its splash colour from this cookie. */
            try {
                document.cookie = 'quran_theme=' + (dark ? 'dark' : 'light')
                    + '; path=/; max-age=31536000; samesite=lax';
            } catch (e) { /* ignore */ }
        })();
    </script>
""" + old_css
assert old_css in s
s = s.replace(old_css, prepaint, 1)
p.write_text(s, encoding="utf-8")
print("layout patched")
