<?php
/**
 * Offline fallback. Cached by the service worker at install time and served
 * when a page cannot be fetched. Deliberately self-contained: no PHP bootstrap,
 * no database, no external files — it has to work with the network switched off.
 */
declare(strict_types=1);

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$base = $base === '' ? '' : $base;
$home = $base . '/';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>You are offline · Quran Reader</title>
    <meta name="theme-color" content="#0F5132">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?= htmlspecialchars($base, ENT_QUOTES) ?>/assets/img/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            color-scheme: light dark;
            --bg: #FAF8F2; --surface: #FFFFFF; --text: #1F2937; --muted: #6B7280;
            --border: #E5DFD2; --green: #0F5132; --gold: #C9A227;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #121212; --surface: #1A1D1B; --text: #E5E7EB; --muted: #9CA3AF; --border: #2C322D; --green: #4FA57B; }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.5rem;
            background: var(--bg); color: var(--text);
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main {
            max-width: 30rem; width: 100%; text-align: center;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 18px; padding: 2.2rem 1.6rem;
        }
        .mark {
            width: 64px; height: 64px; margin: 0 auto 1.1rem;
            display: grid; place-items: center; border-radius: 50%;
            background: color-mix(in srgb, var(--gold) 16%, transparent);
            color: var(--gold);
        }
        h1 { font-size: 1.35rem; margin: 0 0 .5rem; }
        p { margin: 0 0 1rem; color: var(--muted); font-size: .95rem; }
        .actions { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; margin-top: 1.4rem; }
        a {
            display: inline-block; padding: .6rem 1rem; border-radius: 10px;
            text-decoration: none; font-size: .92rem; font-weight: 600;
            border: 1px solid var(--border); color: var(--text);
        }
        a.primary { background: var(--green); border-color: var(--green); color: #fff; }
        ul { text-align: left; margin: 0 0 0 1rem; padding: 0; color: var(--muted); font-size: .9rem; }
        li { margin-bottom: .35rem; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 4l18 16"/>
                <path d="M7.4 13.2a7.4 7.4 0 0 1 3.1-1.7M11.9 8.6a11 11 0 0 1 6.6 1.9"/>
                <path d="M5.2 10.3a11 11 0 0 1 2.6-1.6M16.7 5.9a11 11 0 0 1 2.1 1.1"/>
                <path d="M10.3 17.2a4 4 0 0 1 3.4 0M12 20.2h.01"/>
            </svg>
        </div>

        <h1>You are offline</h1>
        <p>This page is not in the offline copy yet. Surahs you downloaded, plus anything you opened while online, still work.</p>

        <ul>
            <li>Downloaded surahs open normally.</li>
            <li>To read everything offline, download all surahs in Settings.</li>
            <li>Recitation audio is streamed and needs a connection.</li>
        </ul>

        <div class="actions">
            <a class="primary" href="<?= htmlspecialchars($home, ENT_QUOTES) ?>">Try the home page</a>
            <a href="<?= htmlspecialchars($base, ENT_QUOTES) ?>/settings.php">Offline settings</a>
        </div>
    </main>
</body>
</html>
