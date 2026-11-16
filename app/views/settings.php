<?php
/**
 * @var array $translations
 * @var array $reciters
 * @var array $fontSizes
 * @var array $themes
 * @var array $settings
 * @var array|null $currentUser
 */
$sizeLabels = [
    'small'  => 'Small',
    'medium' => 'Medium (default)',
    'large'  => 'Large',
    'xlarge' => 'Extra large',
];
$themeLabels = ['light' => 'Light', 'dark' => 'Dark', 'system' => 'Match my device'];
?>
<section class="section">
    <div class="container container-narrow">
        <header class="page-head">
            <h1>Settings</h1>
            <?php if ($currentUser): ?>
                <p class="muted">Your preferences are saved to your account and applied on every device.</p>
            <?php else: ?>
                <p class="muted">
                    You are browsing as a guest. Preference changes are stored in this browser.
                    <a href="<?= e(u('login.php')) ?>">Sign in</a> to sync them across devices.
                </p>
            <?php endif; ?>
        </header>

        <form class="card settings-form" data-settings-form
              data-authed="<?= $currentUser ? '1' : '0' ?>"
              data-api="<?= e(u('api/settings/save.php')) ?>">
            <div class="field">
                <label for="setting-translation">Translation</label>
                <select id="setting-translation" name="translation_id" data-setting="translation">
                    <?php foreach ($translations as $item): ?>
                        <option value="<?= e($item['id']) ?>" <?= $settings['translation_id'] === $item['id'] ? 'selected' : '' ?>>
                            <?= e($item['english_name']) ?><?= $item['author_name'] !== '' ? ' — ' . e($item['author_name']) : '' ?> (<?= e($item['language_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint">Only pre-reviewed translations are offered. Nothing is machine translated.</p>
            </div>

            <div class="field">
                <label for="setting-reciter">Reciter</label>
                <select id="setting-reciter" name="reciter_id" data-setting="reciter">
                    <?php foreach ($reciters as $item): ?>
                        <option value="<?= e($item['id']) ?>" <?= $settings['reciter_id'] === $item['id'] ? 'selected' : '' ?>>
                            <?= e($item['english_name']) ?><?= $item['style'] ? ' · ' . e($item['style']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="setting-font">Arabic text size</label>
                <select id="setting-font" name="arabic_font_size" data-setting="font-size">
                    <?php foreach ($fontSizes as $size): ?>
                        <option value="<?= e($size) ?>" <?= $settings['arabic_font_size'] === $size ? 'selected' : '' ?>>
                            <?= e($sizeLabels[$size] ?? $size) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint">Applies to Quran text everywhere on the site.</p>
            </div>

            <div class="field">
                <label for="setting-theme">Theme</label>
                <select id="setting-theme" name="theme" data-setting="theme">
                    <?php foreach ($themes as $theme): ?>
                        <option value="<?= e($theme) ?>" <?= $settings['theme'] === $theme ? 'selected' : '' ?>>
                            <?= e($themeLabels[$theme] ?? $theme) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="setting-playing-style">Reciting verse highlight</label>
                <select id="setting-playing-style" name="playing_style" data-setting="playing_style">
                    <?php foreach (\Quran\Repositories\SettingsRepository::PLAYING_STYLES as $style): ?>
                        <option value="<?= e($style) ?>" <?= ($settings['playing_style'] ?? 'card') === $style ? 'selected' : '' ?>>
                            <?= e([
                                'card'   => 'Soft card (default)',
                                'border' => 'Outline only',
                                'bar'    => 'Accent bar only',
                                'off'    => 'Off — verse number only',
                            ][$style] ?? $style) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-hint">How the verse being recited is marked. Hovering a verse always uses the soft card.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save settings</button>
                <span class="form-status muted small" data-settings-status aria-live="polite"></span>
            </div>
        </form>

        <div class="card offline-card" data-offline>
            <h2>Offline reading</h2>
            <p class="muted small">
                Download all 114 surahs with your current translation. After that the reader, search results
                you have already seen, and your bookmarks open without a connection. Recitation audio is
                streamed and is not downloaded.
            </p>

            <p class="offline-status" data-offline-status aria-live="polite">Checking this browser…</p>

            <div class="offline-progress" data-offline-progress hidden>
                <progress max="114" value="0" data-offline-bar>0 of 114</progress>
                <span class="muted small" data-offline-detail></span>
            </div>

            <div class="form-actions offline-actions">
                <button type="button" class="btn btn-primary" data-offline-download>
                    <?= icon('download', 'ico', 17) ?> Download all surahs
                </button>
                <button type="button" class="btn btn-ghost" data-offline-stop hidden>Stop</button>
                <button type="button" class="btn btn-ghost" data-offline-clear hidden>Remove download</button>
                <button type="button" class="btn btn-ghost" data-install-app>Install app</button>
            </div>

            <p class="muted small install-hint" data-install-hint hidden></p>

            <p class="muted small">
                A full download is roughly 60&nbsp;MB. Keep the tab open while it runs; a stopped download
                resumes where it left off.
            </p>
        </div>

        <div class="card local-data-card">
            <h2>Data on this device</h2>
            <p class="muted small">
                Guest bookmarks, reading position and preferences are stored in this browser's local storage.
                They are never sent anywhere unless you sign in and import them.
            </p>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-reset-local>Clear local data</button>
                <?php if ($currentUser): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(u('account.php')) ?>">Account &amp; password</a>
                <?php endif; ?>
            </div>
            <p class="form-status muted small" data-reset-status aria-live="polite"></p>
        </div>
    </div>
</section>
