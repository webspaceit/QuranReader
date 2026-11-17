import pathlib
p = pathlib.Path("public_html/sw.js")
s = p.read_text(encoding="utf-8")
s = s.replace("    'manifest.webmanifest',\n", "", 1)

# The manifest is per-reader (theme-dependent): never serve it from cache.
old = "        if (url.pathname.endsWith('/mp3') || url.pathname.indexOf('/audio/') !== -1) {"
if old in s:
    new = ("        // Theme-dependent manifest: always go to the network.\n"
           "        if (url.pathname.endsWith('/manifest.php')) {\n"
           "            event.respondWith(fetch(request));\n"
           "            return;\n"
           "        }\n\n" + old)
    s = s.replace(old, new, 1)
p.write_text(s, encoding="utf-8")
print("sw patched", "manifest.php" in s)
