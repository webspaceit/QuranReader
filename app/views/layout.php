<?php
/**
 * Main layout.
 *
 * @var string $content
 * @var string $pageTitle
 * @var string $metaDescription
 * @var string $canonical
 * @var string $appName
 * @var array|null $currentUser
 * @var array $settings
 * @var string $csrfToken
 * @var string|null $flashStatus
 * @var string|null $flashError
 * @var string $activeNav
 * @var string $bodyClass
 * @var array<int,string> $pageScripts
 */
$pageScripts = $pageScripts ?? [];
$pageStyles  = $pageStyles ?? [];
$bodyClass   = $bodyClass ?? '';
$activeNav   = $activeNav ?? '';
$year        = date('Y');
?>
<!doctype html>
<html lang="en" data-theme="<?= e($settings['theme'] ?? 'system') ?>" data-font-size="<?= e($settings['arabic_font_size'] ?? 'medium') ?>" data-playing-style="<?= e($settings['playing_style'] ?? 'card') ?>"
      data-arabic-font="<?= e($settings['arabic_font'] ?? 'amiri-quran') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="theme-color" content="#fbf9f4" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f1512" media="(prefers-color-scheme: dark)">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <meta name="app-base" content="<?= e(base_path()) ?>">
    <meta name="authed" content="<?= $currentUser ? '1' : '0' ?>">
    <meta name="server-theme" content="<?= e($settings['theme'] ?? 'system') ?>">
    <meta name="server-arabic-font" content="<?= e($settings['arabic_font'] ?? 'amiri-quran') ?>">
    <meta name="server-font-size" content="<?= e($settings['arabic_font_size'] ?? 'medium') ?>">
    <meta name="server-translation" content="<?= e($settings['translation_id'] ?? '') ?>">
    <meta name="server-reciter" content="<?= e($settings['reciter_id'] ?? '') ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <link rel="icon" href="<?= e(u('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(u('assets/img/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(u('manifest.webmanifest')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= e($appName) ?>">
    <meta name="application-name" content="<?= e($appName) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/sanctuary.css')) ?>">
<?php foreach ($pageStyles as $style): ?>
    <link rel="stylesheet" href="<?= e(asset_url($style)) ?>">
<?php endforeach; ?>
    <script src="<?= e(asset_url('assets/js/theme.js')) ?>"></script>
</head>
<body class="<?= e(trim($bodyClass . ' ' . ($activeNav === 'home' ? 'page-home' : ''))) ?>">
<?php require QURAN_APP . '/views/partials/icons.php'; ?>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(u('')) ?>" aria-label="<?= e($appName) ?> home">
            <span class="brand-mark" aria-hidden="true">
                <svg class="icon" width="26" height="26" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><use href="#i-logo"/></svg>
            </span>
            <span class="brand-block">
                <span class="brand-text"><?= e($appName) ?></span>
                <span class="brand-sub">Quranic Sanctuary</span>
            </span>
        </a>

        <nav class="main-nav" aria-label="Main">
            <a href="<?= e(u('')) ?>" class="<?= $activeNav === 'home' ? 'is-active' : '' ?>">Surah Index</a>
            <a href="<?= e(u('page.php?n=1')) ?>">Mushaf Reader</a>
            <a href="<?= e(u('juz.php')) ?>">Juz</a>
            <a href="<?= e(u('reciters.php')) ?>" class="<?= $activeNav === 'audio' ? 'is-active' : '' ?>">Reciters &amp; Audio</a>

            <a href="<?= e(u('bookmarks.php')) ?>" class="<?= $activeNav === 'bookmarks' ? 'is-active' : '' ?>">
                Bookmarks &amp; Notes<span class="count-pill" data-bookmark-count <?= ($bookmarkCount ?? 0) > 0 ? '' : 'hidden' ?>><?= (int) ($bookmarkCount ?? 0) ?></span>
            </a>
        </nav>

        <form class="topbar-search" action="<?= e(u('search.php')) ?>" method="get" role="search" data-surah-picker>
            <label class="visually-hidden" for="topbar-q">Search the Quran</label>
            <svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.2 4.2"/></svg>
            <input id="topbar-q" type="search" name="q" placeholder="Search a surah or ayah" autocomplete="off" enterkeyhint="search">
        </form>

        <div class="header-actions">
            <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode">
                <svg class="icon-sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><use href="#i-sun"/></svg>
                <svg class="icon-moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><use href="#i-moon"/></svg>
            </button>

            <?php if ($currentUser): ?>
                <a class="avatar-btn" href="<?= e(u('account.php')) ?>" aria-label="Your account" title="<?= e($currentUser['full_name'] ?: 'Your account') ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><use href="#i-user"/></svg>
                </a>
            <?php else: ?>
                <a class="btn btn-primary btn-sm auth-cta" href="<?= e(u('login.php')) ?>">Sign in</a>
                <a class="auth-alt" href="<?= e(u('register.php')) ?>">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if (!empty($flashStatus) || !empty($flashError)): ?>
    <div class="container">
        <?php if (!empty($flashStatus)): ?>
            <div class="alert alert-success" role="status"><?= e($flashStatus) ?></div>
        <?php endif; ?>
        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="guest-banner" data-guest-banner hidden>
    <div class="container guest-banner-inner">
        <span data-guest-banner-text>You have saved bookmarks on this device. Import them into your account?</span>
        <div class="guest-banner-actions">
            <button type="button" class="btn btn-sm btn-primary" data-import-bookmarks>Import</button>
            <button type="button" class="btn btn-sm btn-ghost" data-dismiss-banner>Not now</button>
        </div>
    </div>
</div>

<main id="main" class="site-main">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-col">
            <div class="footer-brand"><?= e($appName) ?></div>
            <p class="muted small">
                A fast, private Quran reader. No advertisements, no tracking, and your data stays yours.
            </p>
        </div>
        <div class="footer-col">
            <div class="footer-heading">Explore</div>
            <ul class="footer-links">
                <li><a href="<?= e(u('')) ?>">Home</a></li>
                <li><a href="<?= e(u('surah.php?n=1')) ?>">Read the Quran</a></li>
                <li><a href="<?= e(u('juz.php')) ?>">Juz list</a></li>
                <li><a href="<?= e(u('reciters.php')) ?>">Reciters &amp; audio</a></li>
                <li><a href="<?= e(u('search.php')) ?>">Search</a></li>
                <li><a href="<?= e(u('page.php?n=1')) ?>">Mushaf pages</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <div class="footer-heading">Project</div>
            <ul class="footer-links">
                <li><a href="<?= e(u('about.php')) ?>">About &amp; data sources</a></li>
                <li><a href="<?= e(u('settings.php')) ?>">Settings</a></li>
                <li><a href="<?= e(u('bookmarks.php')) ?>">Bookmarks</a></li>
                <?php if ($currentUser): ?>
                    <li><a href="<?= e(u('account.php')) ?>">Your account</a></li>
                <?php else: ?>
                    <li><a href="<?= e(u('login.php')) ?>">Sign in</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">
        <span class="muted small">© <?= e($year) ?> <?= e($appName) ?>.</span>
        <span class="muted small">
            Quran text, translations and page data from the public
            <a href="https://alquran.cloud/api" rel="noopener nofollow" target="_blank">Al Quran Cloud</a> API.
            Recitations via the <a href="https://islamic.network" rel="noopener nofollow" target="_blank">Islamic Network</a> CDN.
        </span>
    </div>
</footer>

<script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/pwa.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/surah-picker.js')) ?>" defer></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= e(asset_url($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
