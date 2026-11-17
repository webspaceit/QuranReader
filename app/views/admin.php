<?php
/**
 * @var array  $site
 * @var string $year
 * @var string $csrfToken
 * @var array|null $currentUser
 */
$yearValue = (string) ($site['footer_copyright_year'] ?? 'auto');
?>
<section class="section">
    <div class="container container-narrow">
        <header class="page-head admin-head">
            <p class="eyebrow">Super admin</p>
            <h1>Site settings</h1>
            <p class="muted">
                Changes here apply to every visitor on every page. Signed in as
                <strong><?= e($currentUser['email'] ?? '') ?></strong>.
            </p>
        </header>

        <nav class="admin-nav" aria-label="Admin sections">
            <a class="admin-nav-link is-current" href="<?= e(u('admin.php')) ?>" aria-current="page">Footer &amp; copyright</a>
        </nav>

        <form class="card settings-form" method="post" action="<?= e(u('admin.php')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="section" value="footer">

            <h2 class="admin-section-title">Footer</h2>

            <div class="field">
                <label for="footer-tagline">Tagline</label>
                <textarea id="footer-tagline" name="footer_tagline" rows="2" maxlength="240"
                          class="admin-textarea"><?= e($site['footer_tagline'] ?? '') ?></textarea>
                <p class="field-hint">The sentence under the site name in the footer. 240 characters max.
                    Plain text — character entities like <code>&amp;copy;</code> or <code>&amp;mdash;</code> work, HTML tags do not.</p>
            </div>

            <div class="field">
                <label for="footer-note">Extra footer line <span class="muted small">(optional)</span></label>
                <input id="footer-note" type="text" name="footer_note" maxlength="160"
                       value="<?= e($site['footer_note'] ?? '') ?>" placeholder="e.g. Built for readers in Bangladesh">
                <p class="field-hint">Shown after the copyright line. Leave empty to hide it. 160 characters max.</p>
            </div>

            <h2 class="admin-section-title">Copyright</h2>

            <div class="field">
                <label for="footer-holder">Copyright holder</label>
                <input id="footer-holder" type="text" name="footer_copyright_holder" maxlength="120" required
                       value="<?= e($site['footer_copyright_holder'] ?? '') ?>">
                <p class="field-hint">Just the name — the © and the year are added for you. One link is allowed,
                    e.g. <code>Quran Reader. Developed by &lt;a href="https://example.com"&gt;Example&lt;/a&gt;</code>.</p>
            </div>

            <div class="field">
                <label for="footer-year">Year</label>
                <select id="footer-year" name="footer_copyright_year">
                    <option value="auto" <?= $yearValue === 'auto' ? 'selected' : '' ?>>Automatic (current year)</option>
                    <?php
                    $thisYear = (int) date('Y');
                    $first = (int) ($site['footer_copyright_year'] ?? 0);
                    $years = range($thisYear, $thisYear - 12);
                    if ($first >= 1990 && !in_array($first, $years, true)) {
                        array_unshift($years, $first);
                    }
                    foreach ($years as $option):
                        ?>
                        <option value="<?= e((string) $option) ?>" <?= $yearValue === (string) $option ? 'selected' : '' ?>>
                            <?= e((string) $option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint">Automatic follows the server clock, so the notice never goes stale.</p>
            </div>

            <div class="admin-preview" aria-live="polite">
                <span class="admin-preview-label">Preview</span>
                <p class="admin-preview-tagline"><?= \Quran\Repositories\SiteSettingsRepository::inline((string) ($site['footer_tagline'] ?? '')) ?></p>
                <p class="muted small">
                    © <?= e($year) ?> <?= \Quran\Repositories\SiteSettingsRepository::inline((string) ($site['footer_copyright_holder'] ?? '')) ?>.
                    <?php if (($site['footer_note'] ?? '') !== ''): ?>
                        <?= e($site['footer_note']) ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save footer</button>
                <a class="btn btn-ghost" href="<?= e(u('')) ?>">View the site</a>
            </div>
        </form>

        <p class="muted small admin-foot-note">
            The data-source credit in the footer is fixed: it names the Quran text and recitation
            providers whose licences require attribution, so it is not editable here.
        </p>
    </div>
</section>
