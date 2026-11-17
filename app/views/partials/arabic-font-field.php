<?php
/**
 * Quran typeface picker, shared by Settings and the account dashboard.
 *
 * Every face is self-hosted and OFL-licensed; the preview renders in the real
 * font so the choice can be judged before saving.
 *
 * @var array $settings
 */
declare(strict_types=1);

$choices = [
    'amiri-quran'  => [
        'label'  => 'Amiri Quran',
        'note'   => 'Default · Quranic naskh on King Fahd Complex metrics',
        'family' => '"Amiri Quran"',
    ],
    'scheherazade' => [
        'label'  => 'Scheherazade New',
        'note'   => 'Most legible at small sizes — open counters, clear marks',
        'family' => '"Scheherazade New"',
    ],
    'noto-naskh'   => [
        'label'  => 'Noto Naskh Arabic',
        'note'   => 'Neutral modern naskh, even colour on screen',
        'family' => '"Noto Naskh Arabic"',
    ],
    'nastaliq'     => [
        'label'  => 'Noto Nastaliq Urdu',
        'note'   => 'Nastaliq for readers used to the South Asian script',
        'family' => '"Noto Nastaliq Urdu"',
    ],
];

$current = (string) ($settings['arabic_font'] ?? 'amiri-quran');
if (!isset($choices[$current])) {
    $current = 'amiri-quran';
}
?>
<div class="field font-field">
    <span class="field-label" id="arabic-font-label">Quran typeface</span>
    <div class="font-choices" role="radiogroup" aria-labelledby="arabic-font-label">
        <?php foreach ($choices as $value => $choice): ?>
            <label class="font-choice">
                <input type="radio" name="arabic_font" value="<?= e($value) ?>"
                       data-setting="arabic-font" <?= $current === $value ? 'checked' : '' ?>>
                <span class="font-choice-body">
                    <span class="font-choice-name"><?= e($choice['label']) ?></span>
                    <span class="font-choice-note"><?= e($choice['note']) ?></span>
                </span>
                <span class="font-choice-sample" lang="ar" dir="rtl"
                      style="font-family: <?= e($choice['family']) ?>">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</span>
            </label>
        <?php endforeach; ?>
    </div>
    <p class="field-hint">Applies to Quran text everywhere. All four faces are self-hosted, so they also work offline.</p>
</div>
