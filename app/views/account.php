<?php
/**
 * @var array $userRow
 * @var int $bookmarkCount
 */
$bookmarkCount = $bookmarkCount ?? 0;
?>
<section class="section">
    <div class="container container-narrow">
        <header class="page-head">
            <h1>Your account</h1>
            <p class="muted">Signed in as <strong><?= e($userRow['email']) ?></strong>
                <?php if (!empty($userRow['created_at'])): ?>
                    · member since <?= e(format_date((string) $userRow['created_at'])) ?>
                <?php endif; ?>
            </p>
        </header>

        <div class="account-grid">
            <div class="card">
                <h2>Profile</h2>
                <form data-account-form="profile" data-api="<?= e(u('api/account/update.php')) ?>">
                    <div class="field">
                        <label for="full_name">Display name</label>
                        <input id="full_name" name="full_name" type="text" maxlength="120"
                               value="<?= e($userRow['full_name'] ?? '') ?>" autocomplete="name"
                               placeholder="Optional">
                        <p class="field-hint">Shown in the header. It is not visible to other visitors.</p>
                    </div>
                    <div class="field">
                        <label for="email-readonly">Email</label>
                        <input id="email-readonly" type="email" value="<?= e($userRow['email']) ?>" disabled>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save profile</button>
                        <span class="form-status muted small" data-form-status aria-live="polite"></span>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Your reading</h2>
                <ul class="stat-list">
                    <li><span>Bookmarks</span><strong data-account-bookmarks><?= (int) $bookmarkCount ?></strong></li>
                    <li><span>Reading position</span>
                        <strong data-account-progress>—</strong>
                    </li>
                </ul>
                <div class="form-actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(u('bookmarks.php')) ?>">Open bookmarks</a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(u('settings.php')) ?>">All settings</a>
                </div>
            </div>
        </div>

        <div class="card preferences-card" id="preferences">
            <h2>Reading preferences</h2>
            <p class="muted small">
                These apply wherever you sign in — the reader, search results and the Mushaf view.
                Guests keep the same choices on this device only.
            </p>

            <form data-settings-form data-authed="1" data-api="<?= e(u('api/settings/save.php')) ?>">
                <div class="field">
                    <label for="account-translation">Translation</label>
                    <select id="account-translation" name="translation_id" data-setting="translation">
                        <?php foreach ($translations as $item): ?>
                            <option value="<?= e($item['id']) ?>" <?= $settings['translation_id'] === $item['id'] ? 'selected' : '' ?>>
                                <?= e($item['english_name']) ?><?= !empty($item['author_name']) ? ' — ' . e($item['author_name']) : '' ?> (<?= e($item['language_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">
                        <?= count($translations) ?> editions available, including Bengali. Changing it reloads the reader
                        with the new text next time you open a surah.
                    </p>
                </div>

                <div class="filter-grid">
                    <div class="field">
                        <label for="account-reciter">Reciter</label>
                        <select id="account-reciter" name="reciter_id" data-setting="reciter">
                            <?php foreach ($reciters as $item): ?>
                                <option value="<?= e($item['id']) ?>" <?= $settings['reciter_id'] === $item['id'] ? 'selected' : '' ?>>
                                    <?= e($item['english_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="account-font-size">Arabic text size</label>
                        <select id="account-font-size" name="arabic_font_size" data-setting="arabic_font_size">
                            <?php foreach ($fontSizes as $size): ?>
                                <option value="<?= e($size) ?>" <?= $settings['arabic_font_size'] === $size ? 'selected' : '' ?>>
                                    <?= e(ucfirst($size)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php require __DIR__ . '/partials/arabic-font-field.php'; ?>
                    <div class="field">
                        <label for="account-theme">Theme</label>
                        <select id="account-theme" name="theme" data-setting="theme">
                            <?php foreach ($themes as $theme): ?>
                                <option value="<?= e($theme) ?>" <?= $settings['theme'] === $theme ? 'selected' : '' ?>>
                                    <?= e($theme === 'system' ? 'Match my device' : ucfirst($theme)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="account-playing-style">Reciting verse</label>
                        <select id="account-playing-style" name="playing_style" data-setting="playing_style">
                            <?php foreach (\Quran\Repositories\SettingsRepository::PLAYING_STYLES as $style): ?>
                                <option value="<?= e($style) ?>" <?= ($settings['playing_style'] ?? 'card') === $style ? 'selected' : '' ?>>
                                    <?= e([
                                        'card'   => 'Soft card',
                                        'border' => 'Outline only',
                                        'bar'    => 'Accent bar only',
                                        'off'    => 'Off',
                                    ][$style] ?? $style) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save preferences</button>
                    <span class="form-status muted small" data-settings-status aria-live="polite"></span>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Change password</h2>
            <form data-account-form="password" data-api="<?= e(u('api/account/password.php')) ?>">
                <div class="field">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="new_password">New password</label>
                        <input id="new_password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="field">
                        <label for="new_password_confirm">Repeat new password</label>
                        <input id="new_password_confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update password</button>
                    <span class="form-status muted small" data-form-status aria-live="polite"></span>
                </div>
            </form>
        </div>

        <div class="card danger-card">
            <h2>Delete account</h2>
            <p class="muted small">
                This permanently removes your profile, bookmarks, reading progress and settings.
                It cannot be undone.
            </p>
            <form data-account-form="delete" data-api="<?= e(u('api/account/delete.php')) ?>"
                  data-confirm="Delete your account and all saved data permanently?">
                <div class="field">
                    <label for="delete_password">Confirm with your password</label>
                    <input id="delete_password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Delete my account</button>
                    <span class="form-status muted small" data-form-status aria-live="polite"></span>
                </div>
            </form>
        </div>

        <div class="form-actions signout-row">
            <button type="button" class="btn btn-ghost" data-logout data-api="<?= e(u('api/auth/logout.php')) ?>">Sign out</button>
        </div>
    </div>
</section>
