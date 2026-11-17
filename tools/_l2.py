import pathlib
p = pathlib.Path("app/views/layout.php")
s = p.read_text(encoding="utf-8")

pairs = [
    ("""<p class="muted small"><?= e($site['footer_tagline'] ?? '') ?></p>""",
     """<p class="muted small"><?= \\Quran\\Repositories\\SiteSettingsRepository::inline((string) ($site['footer_tagline'] ?? '')) ?></p>"""),
    ("""            © <?= e($year) ?> <?= e($site['footer_copyright_holder'] ?? $appName) ?>.
            <?php if (($site['footer_note'] ?? '') !== ''): ?>
                <?= e($site['footer_note']) ?>
            <?php endif; ?>""",
     """            © <?= e($year) ?> <?= \\Quran\\Repositories\\SiteSettingsRepository::inline((string) ($site['footer_copyright_holder'] ?? $appName)) ?>.
            <?php if (($site['footer_note'] ?? '') !== ''): ?>
                <?= \\Quran\\Repositories\\SiteSettingsRepository::inline((string) $site['footer_note']) ?>
            <?php endif; ?>"""),
]
for old, new in pairs:
    assert old in s, old[:60]
    s = s.replace(old, new, 1)
p.write_text(s, encoding="utf-8")

# Preview in the admin form should show the link as a link, not as escaped text.
p = pathlib.Path("app/views/admin.php")
s = p.read_text(encoding="utf-8")
s = s.replace("""<p class="admin-preview-tagline"><?= e($site['footer_tagline'] ?? '') ?></p>""",
"""<p class="admin-preview-tagline"><?= \\Quran\\Repositories\\SiteSettingsRepository::inline((string) ($site['footer_tagline'] ?? '')) ?></p>""", 1)
s = s.replace("""                    © <?= e($year) ?> <?= e($site['footer_copyright_holder'] ?? '') ?>.""",
"""                    © <?= e($year) ?> <?= \\Quran\\Repositories\\SiteSettingsRepository::inline((string) ($site['footer_copyright_holder'] ?? '')) ?>.""", 1)
s = s.replace("""                <p class="field-hint">Just the name — the © symbol and the year are added for you.</p>""",
"""                <p class="field-hint">Just the name — the © and the year are added for you. One link is allowed,
                    e.g. <code>Quran Reader. Developed by &lt;a href="https://example.com"&gt;Example&lt;/a&gt;</code>.</p>""", 1)
p.write_text(s, encoding="utf-8")
print("views patched")
