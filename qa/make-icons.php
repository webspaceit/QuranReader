<?php
/**
 * Development helper: renders the PWA icons (green tile + gold open book).
 *
 *   php qa/make-icons.php
 *
 * Writes PNGs into public_html/assets/img/. Requires the GD extension.
 * The artwork is drawn at 4x and downsampled, which is the cheapest way to get
 * clean edges without an SVG rasteriser on the build machine.
 */
declare(strict_types=1);

if (!extension_loaded('gd')) {
    fwrite(STDERR, "The GD extension is required.\n");
    exit(1);
}

$root = dirname(__DIR__);
$out  = $root . '/public_html/assets/img';

/**
 * Draw one icon.
 *
 * @param bool $rounded  rounded square background (iOS applies its own mask)
 * @param bool $maskable keep the artwork inside the maskable safe zone
 */
function makeIcon(string $path, int $size, bool $rounded, bool $maskable): void
{
    $scale = 4;
    $px    = $size * $scale;
    $img   = imagecreatetruecolor($px, $px);
    imagesavealpha($img, true);
    imagealphablending($img, true);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $green = imagecolorallocate($img, 0x0F, 0x51, 0x32);
    $gold  = imagecolorallocate($img, 0xC9, 0xA2, 0x27);

    if ($rounded) {
        $radius = (int) round($px * 0.22);
        imagefilledrectangle($img, $radius, 0, $px - $radius, $px, $green);
        imagefilledrectangle($img, 0, $radius, $px, $px - $radius, $green);
        foreach ([[$radius, $radius], [$px - $radius, $radius], [$radius, $px - $radius], [$px - $radius, $px - $radius]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $green);
        }
    } else {
        imagefilledrectangle($img, 0, 0, $px, $px, $green);
    }

    // Design grid (64 units) scaled into the safe box and centred.
    $unit = ($px * ($maskable ? 0.74 : 0.94)) / 64.0;
    $zero = ($px - 64.0 * $unit) / 2.0;
    $map  = static function (float $x, float $y) use ($unit, $zero): array {
        return [(int) round($zero + $x * $unit), (int) round($zero + $y * $unit)];
    };

    // Open book: two pages meeting at a green spine.
    $left  = [$map(11, 19), $map(31, 23), $map(31, 46), $map(11, 42)];
    $right = [$map(53, 19), $map(33, 23), $map(33, 46), $map(53, 42)];

    $flatLeft  = array_merge(...$left);
    $flatRight = array_merge(...$right);

    imagefilledpolygon($img, $flatLeft, $gold);
    imagefilledpolygon($img, $flatRight, $gold);

    // Page lines, in green, so the book reads as text rather than a shape.
    $stroke = max(1, (int) round($unit * 1.1));
    imagesetthickness($img, $stroke);
    foreach ([27.5, 32.5, 37.5] as $y) {
        [$x1, $y1] = $map(16, $y - 1.2);
        [$x2, $y2] = $map(26.5, $y + 0.6);
        imageline($img, $x1, $y1, $x2, $y2, $green);
        [$x3, $y3] = $map(37.5, $y + 0.6);
        [$x4, $y4] = $map(48, $y - 1.2);
        imageline($img, $x3, $y3, $x4, $y4, $green);
    }

    $final = imagecreatetruecolor($size, $size);
    imagesavealpha($final, true);
    imagealphablending($final, false);
    imagefill($final, 0, 0, $transparent);
    imagecopyresampled($final, $img, 0, 0, 0, 0, $size, $size, $px, $px);
    imagepng($final, $path);

    printf("  %-34s %dx%d\n", basename($path), $size, $size);
}

echo "Rendering icons into public_html/assets/img\n";
makeIcon($out . '/icon-192.png', 192, true, false);
makeIcon($out . '/icon-512.png', 512, true, false);
makeIcon($out . '/icon-maskable-512.png', 512, false, true);
makeIcon($out . '/apple-touch-icon.png', 180, false, false);
echo "Done.\n";
