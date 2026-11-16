<?php
/**
 * @var array $items
 * @var array $marks
 * @var array<int,string> $markColors
 * @var array|null $translation
 * @var array $translations
 * @var array|null $currentUser
 */
$colorLabels = [
    'yellow' => 'Yellow highlight',
    'green'  => 'Green highlight',
    'blue'   => 'Blue highlight',
    'pink'   => 'Pink highlight',
    'purple' => 'Purple highlight',
];

/**
 * Group saved rows by surah: one collapsible node per surah, verses newest
 * first inside it. Groups are ordered by their most recent entry so the list
 * still reads "newest activity first" overall.
 *
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array{surah:int,name:string,arabic:string,count:int,latest:string,items:array<int,array<string,mixed>>}>
 */
$groupBySurah = static function (array $rows, string $surahKey, string $nameKey, string $arabicKey, string $timeKey): array {
    $groups = [];
    foreach ($rows as $row) {
        $surah = (int) $row[$surahKey];
        if (!isset($groups[$surah])) {
            $groups[$surah] = [
                'surah'  => $surah,
                'name'   => (string) ($row[$nameKey] ?? ''),
                'arabic' => (string) ($row[$arabicKey] ?? ''),
                'count'  => 0,
                'latest' => (string) ($row[$timeKey] ?? ''),
                'items'  => [],
            ];
        }
        $groups[$surah]['items'][] = $row;
        $groups[$surah]['count']++;
        if ((string) ($row[$timeKey] ?? '') > $groups[$surah]['latest']) {
            $groups[$surah]['latest'] = (string) $row[$timeKey];
        }
    }

    usort($groups, static fn (array $a, array $b) => strcmp($b['latest'], $a['latest']));

    return $groups;
};

$bookmarkGroups = $groupBySurah($items, 'surah_number', 'name_simple', 'name_arabic', 'created_at');
$markGroups     = $groupBySurah($marks, 'surah', 'surah_name', 'surah_arabic', 'updated_at');
?>
<section class="section">
    <div class="container container-narrow">
        <header class="page-head">
            <h1>Bookmarks</h1>
            <?php if ($currentUser): ?>
                <p class="muted">Saved verses, highlights and notes are synced to your account and available on every device.</p>
            <?php else: ?>
                <p class="muted">
                    You are browsing as a guest, so bookmarks, highlights and notes are stored in this browser only.
                    <a href="<?= e(u('register.php')) ?>">Create an account</a> to keep them everywhere.
                </p>
            <?php endif; ?>
        </header>

        <nav class="saved-nav" aria-label="Saved items">
            <a class="saved-nav-link is-active" href="#bookmarks">Bookmarks</a>
            <a class="saved-nav-link" href="#marks">Notes &amp; highlights</a>
        </nav>

        <div id="bookmarks">
            <?php if ($currentUser): ?>
                <?php if ($bookmarkGroups === []): ?>
                    <div class="empty-state card" data-empty-bookmarks>
                        <h2>No bookmarks yet</h2>
                        <p>Tap the bookmark icon next to any verse while reading, and it will appear here.</p>
                        <a class="btn btn-primary" href="<?= e(surah_url(1)) ?>">Start reading</a>
                    </div>
                <?php else: ?>
                    <p class="muted small" data-bookmark-summary>
                        <?= count($items) ?> saved verse<?= count($items) === 1 ? '' : 's' ?>
                        in <?= count($bookmarkGroups) ?> surah<?= count($bookmarkGroups) === 1 ? '' : 's' ?>
                    </p>

                    <div class="saved-tree" data-bookmark-list>
                        <?php foreach ($bookmarkGroups as $group): ?>
                            <details class="saved-group" open>
                                <summary>
                                    <span class="saved-group-num"><?= (int) $group['surah'] ?></span>
                                    <span class="saved-group-title">
                                        <strong><?= e($group['name']) ?></strong>
                                        <?php if ($group['arabic'] !== ''): ?>
                                            <span class="saved-group-arabic" lang="ar" dir="rtl"><?= e($group['arabic']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="saved-group-count" title="<?= (int) $group['count'] ?> saved verse<?= (int) $group['count'] === 1 ? '' : 's' ?>"><?= (int) $group['count'] ?></span>
                                </summary>

                                <ol class="bookmark-list saved-group-items">
                                    <?php foreach ($group['items'] as $item): ?>
                                        <li class="bookmark" data-bookmark-item data-surah="<?= (int) $item['surah_number'] ?>" data-verse="<?= (int) $item['verse_number'] ?>">
                                            <div class="bookmark-head">
                                                <a class="bookmark-ref" href="<?= e(surah_url((int) $item['surah_number'], (int) $item['verse_number'])) ?>">
                                                    <span class="saved-verse-ref"><?= (int) $item['surah_number'] ?>:<?= (int) $item['verse_number'] ?></span>
                                                </a>
                                                <div class="bookmark-actions">
                                                    <span class="muted small"><?= e(format_date((string) $item['created_at'])) ?></span>
                                                    <button type="button" class="icon-btn sm" data-remove-bookmark
                                                            data-id="<?= (int) $item['id'] ?>"
                                                            data-surah="<?= (int) $item['surah_number'] ?>"
                                                            data-verse="<?= (int) $item['verse_number'] ?>"
                                                            aria-label="Remove bookmark">
                                                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                                                            <path d="M5 7h14M9.5 7V5.5h5V7M7 7l.8 12.5h8.4L17 7"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php if (!empty($item['text_uthmani'])): ?>
                                                <p class="bookmark-arabic" lang="ar" dir="rtl"><?= e($item['text_uthmani']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['translation_text'])): ?>
                                                <p class="bookmark-translation"><?= e(\Quran\Support\Text::excerpt((string) $item['translation_text'], 220)) ?></p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card guest-bookmarks" data-guest-bookmarks>
                    <p class="muted">Reading bookmarks saved in this browser…</p>
                </div>
                <div class="card guest-bookmarks" data-guest-empty hidden>
                    <h2>No bookmarks on this device</h2>
                    <p>Bookmark verses while reading and they will be listed here — no account needed.</p>
                    <a class="btn btn-primary" href="<?= e(surah_url(1)) ?>">Start reading</a>
                </div>
            <?php endif; ?>
        </div>

        <div id="marks" class="saved-marks">
            <h2 class="saved-heading">Notes &amp; highlights</h2>

            <?php if ($currentUser): ?>
                <?php if ($markGroups === []): ?>
                    <div class="empty-state card" data-empty-marks>
                        <h2>Nothing highlighted yet</h2>
                        <p>Use the highlighter to colour a verse, or the note icon to write something private about it.</p>
                        <a class="btn btn-primary" href="<?= e(surah_url(1)) ?>">Start reading</a>
                    </div>
                <?php else: ?>
                    <p class="muted small" data-mark-summary>
                        <?= count($marks) ?> verse<?= count($marks) === 1 ? '' : 's' ?> with a highlight or note
                        in <?= count($markGroups) ?> surah<?= count($markGroups) === 1 ? '' : 's' ?>
                    </p>

                    <div class="saved-tree" data-mark-list>
                        <?php foreach ($markGroups as $group): ?>
                            <details class="saved-group" open>
                                <summary>
                                    <span class="saved-group-num"><?= (int) $group['surah'] ?></span>
                                    <span class="saved-group-title">
                                        <strong><?= e($group['name']) ?></strong>
                                        <?php if ($group['arabic'] !== ''): ?>
                                            <span class="saved-group-arabic" lang="ar" dir="rtl"><?= e($group['arabic']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="saved-group-count" title="<?= (int) $group['count'] ?> highlighted or noted verse<?= (int) $group['count'] === 1 ? '' : 's' ?>"><?= (int) $group['count'] ?></span>
                                </summary>

                                <ol class="mark-list saved-group-items">
                                    <?php foreach ($group['items'] as $mark): ?>
                                        <?php
                                        $markSurah = (int) $mark['surah'];
                                        $markVerse = (int) $mark['verse'];
                                        $markColor = (string) ($mark['color'] ?? '');
                                        $markNote  = (string) ($mark['note'] ?? '');
                                        ?>
                                        <li class="mark" data-mark-item
                                            data-surah="<?= $markSurah ?>" data-verse="<?= $markVerse ?>"
                                            <?= $markColor !== '' ? 'data-highlight="' . e($markColor) . '"' : '' ?>>
                                            <div class="mark-head">
                                                <a class="mark-ref" href="<?= e(surah_url($markSurah, $markVerse)) ?>">
                                                    <span class="saved-verse-ref"><?= $markSurah ?>:<?= $markVerse ?></span>
                                                </a>
                                                <div class="mark-actions">
                                                    <?php if ($markColor !== ''): ?>
                                                        <span class="visually-hidden"><?= e($colorLabels[$markColor] ?? 'Highlighted') ?></span>
                                                    <?php endif; ?>
                                                    <a class="icon-btn sm" title="Edit note in the reader"
                                                       aria-label="Edit note in the reader"
                                                       href="<?= e(surah_url($markSurah, $markVerse)) ?>&edit=note#verse-<?= $markVerse ?>">
                                                        <?= icon('note', 'ico', 17) ?>
                                                    </a>
                                                    <button type="button" class="icon-btn sm" data-mark-remove
                                                            data-surah="<?= $markSurah ?>" data-verse="<?= $markVerse ?>"
                                                            aria-label="Remove highlight and note">
                                                        <?= icon('trash', 'ico', 17) ?>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php if (!empty($mark['arabic'])): ?>
                                                <p class="mark-arabic" lang="ar" dir="rtl"><?= e($mark['arabic']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($markNote !== ''): ?>
                                                <p class="mark-note"><?= e($markNote) ?></p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card guest-bookmarks" data-guest-marks>
                    <p class="muted">Highlights and notes saved in this browser…</p>
                </div>
                <div class="card guest-bookmarks" data-guest-marks-empty hidden>
                    <h2>No highlights or notes on this device</h2>
                    <p>Colour a verse or write a note while reading and it will be listed here.</p>
                    <a class="btn btn-primary" href="<?= e(surah_url(1)) ?>">Start reading</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="bookmark-footer">
            <a class="btn btn-ghost btn-sm" href="<?= e(u('search.php')) ?>">Find a verse</a>
            <?php if ($currentUser): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(u('account.php')) ?>">Account settings</a>
            <?php endif; ?>
        </div>
    </div>
</section>
