<?php
/**
 * @var array $translations
 * @var array $reciters
 * @var int $verseCount
 * @var int $chapterCount
 */
?>
<section class="section">
    <div class="container container-narrow prose">
        <header class="page-head">
            <h1>About this project</h1>
            <p class="muted">A quiet place to read, listen and search the Quran — built to work on ordinary hosting.</p>
        </header>

        <h2>Purpose</h2>
        <p>
            This site is a focused Quran reader: Arabic text in the Uthmani script, trusted translations,
            verse-by-verse recitation, search, bookmarks and reading progress. There are no advertisements,
            no analytics and no third-party trackers in Version 1.
        </p>

        <h2>Data sources and attribution</h2>
        <ul class="list">
            <li>
                <strong>Quran text, translations, page and juz data</strong> — imported from the public
                <a href="https://alquran.cloud/api" rel="noopener nofollow" target="_blank">Al Quran Cloud API</a>
                and stored in this site's MySQL database. The Arabic text is the Uthmani edition.
            </li>
            <li>
                <strong>Recitations</strong> — streamed per verse from the
                <a href="https://islamic.network" rel="noopener nofollow" target="_blank">Islamic Network</a> CDN.
            </li>
            <li>
                <strong>Translations</strong> — the editions listed below. They are published, pre-reviewed
                translations; nothing on this site is machine translated.
            </li>
        </ul>

        <h3>Available translations</h3>
        <ul class="list list-compact">
            <?php foreach ($translations as $item): ?>
                <li>
                    <strong><?= e($item['english_name']) ?></strong>
                    <?php if (!empty($item['author_name'])): ?>by <?= e($item['author_name']) ?><?php endif; ?>
                    <span class="muted">· <?= e($item['language_name']) ?> · code <code><?= e($item['id']) ?></code></span>
                </li>
            <?php endforeach; ?>
            <?php if ($translations === []): ?>
                <li class="muted">No translation has been imported yet.</li>
            <?php endif; ?>
        </ul>

        <h3>Reciters</h3>
        <ul class="list list-compact">
            <?php foreach ($reciters as $item): ?>
                <li>
                    <strong><?= e($item['english_name']) ?></strong>
                    <span class="muted">· <?= e($item['style'] ?: 'Murattal') ?> · <?= (int) $item['bitrate'] ?> kbps</span>
                </li>
            <?php endforeach; ?>
        </ul>

        <h2>How your data is stored</h2>
        <ul class="list">
            <li>
                <strong>Guest use</strong> — bookmarks, reading position and display preferences are kept in
                your browser's <code>localStorage</code>. Nothing leaves your device.
            </li>
            <li>
                <strong>With an account</strong> — your email address, a hashed password, bookmarks, reading
                position and display settings are stored in this site's MySQL database. If you bookmark verses
                as a guest and then sign in, you can import them; the local copy is removed afterwards.
            </li>
            <li>
                <strong>Cookies</strong> — one session cookie keeps you signed in. It is HTTP-only, SameSite=Lax,
                and Secure when the site runs on HTTPS. No advertising or analytics cookies exist.
            </li>
            <li>
                <strong>Deleting data</strong> — you can delete individual bookmarks at any time, or delete your
                whole account from the account page. Deletion removes your profile, bookmarks, progress and
                settings immediately.
            </li>
        </ul>

        <h2>Security in brief</h2>
        <ul class="list">
            <li>Passwords are hashed with PHP's <code>password_hash()</code>; plain passwords are never stored or logged.</li>
            <li>Every database query uses prepared statements.</li>
            <li>State-changing requests require a per-session CSRF token.</li>
            <li>Login, registration and password-reset endpoints are rate limited.</li>
            <li>Authentication errors are deliberately generic, so the API cannot be used to discover registered emails.</li>
        </ul>

        <h2>Content status</h2>
        <p class="muted">
            <?= number_format((int) $chapterCount) ?> chapters and <?= number_format((int) $verseCount) ?> verses
            are cached in the local database. If these numbers look wrong, the content import has not finished —
            see the README's deployment checklist.
        </p>

        <h2>Please note</h2>
        <p class="muted small">
            This website is a reading tool. For religious rulings, teaching or verification of the text,
            please refer to qualified scholars and printed Mushaf editions. Report content issues to the
            site owner so they can be corrected against the source data.
        </p>
    </div>
</section>
