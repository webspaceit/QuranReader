import pathlib
p = pathlib.Path("public_html/sw.js")
s = p.read_text(encoding="utf-8")
old = "    if (/\\.mp3$/i.test(url.pathname)) {"
new = ("    // The manifest carries the reader's splash colour, so it must not be cached.\n"
       "    if (url.pathname.endsWith('/manifest.php')) {\n"
       "        event.respondWith(fetch(request));\n"
       "        return;\n"
       "    }\n\n"
       "    if (/\\.mp3$/i.test(url.pathname)) {")
assert old in s
s = s.replace(old, new, 1)
s = s.replace("var CACHE_VERSION = 'v4';", "var CACHE_VERSION = 'v5';", 1)
p.write_text(s, encoding="utf-8")
print("ok", "manifest.php" in s, "v5" in s)
