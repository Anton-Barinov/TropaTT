<?php
declare(strict_types=1);

/**
 * Generates the PWA icons from the favicon design (upload/web/assets/favicon.svg):
 * a blue (#1f6feb) tile with three white horizontal bars.
 *
 *   icon-192.png          192x192  rounded tile   (installed app icon)
 *   icon-512.png          512x512  rounded tile   (installed app icon)
 *   icon-512-maskable.png 512x512  full-bleed tile with the glyph inside the
 *                                  safe zone (80%) — used for adaptive icons
 *
 * Requires the GD extension. Run:
 *
 *   php upload/api/scripts/generate_pwa_icons.php
 *
 * The output overwrites the committed PNGs in upload/web/assets/icons/.
 */

$outDir = dirname(__DIR__, 2) . '/web/assets/icons';
if (!is_dir($outDir)) {
    if (!mkdir($outDir, 0755, true) && !is_dir($outDir)) {
        fwrite(STDERR, 'Cannot create ' . $outDir . PHP_EOL);
        exit(1);
    }
}
if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, 'PHP GD extension is required.' . PHP_EOL);
    exit(1);
}

/**
 * Draws the TropaTT tile.
 *
 * Geometry mirrors the 64x64 viewBox of favicon.svg:
 *   bars at y 18/29/40, height 6, x from 14 to 50 (width 36),
 *   corner radius 14.
 *
 * @param float $scale   1.0 = exact favicon geometry; 0.8 = shrunk into the
 *                       maskable safe zone (full-bleed background).
 * @param bool  $rounded  true = transparent rounded corners (regular icon),
 *                       false = full-bleed square (maskable icon).
 * @return \GdImage
 */
function drawPwaTile(int $size, bool $rounded, float $scale): \GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    $blue = imagecolorallocate($img, 0x1f, 0x6f, 0xeb);
    $white = imagecolorallocate($img, 255, 255, 255);

    // Background tile.
    imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $blue);

    if ($rounded) {
        // Punch the four corners so the tile gets a rounded shape with a
        // transparent background (radius ratio 14/64 from the SVG).
        $radius = (int)round($size * (14 / 64));
        foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as $corner) {
            $cx = $corner[0] * ($size - 1);
            $cy = $corner[1] * ($size - 1);
            for ($y = 0; $y <= $radius; $y++) {
                for ($x = 0; $x <= $radius; $x++) {
                    $px = $corner[0] === 0 ? $x : $size - 1 - $x;
                    $py = $corner[1] === 0 ? $y : $size - 1 - $y;
                    $dx = $px - $cx;
                    $dy = $py - $cy;
                    if ($dx * $dx + $dy * $dy > $radius * $radius) {
                        imagesetpixel($img, $px, $py, $transparent);
                    }
                }
            }
        }
    }

    // Three white bars (scaled by $scale, centered in the tile).
    $barW = (int)round($size * (36 / 64) * $scale);
    $barH = max(1, (int)round($size * (6 / 64) * $scale));
    $gap = max(1, (int)round($size * (11 / 64) * $scale));
    $margin = (int)round(($size - $barW) / 2);
    $y0 = (int)round($size * (18 / 64) * $scale + ($size * (1 - $scale)) / 2);
    for ($i = 0; $i < 3; $i++) {
        $y = $y0 + $i * $gap;
        imagefilledrectangle($img, $margin, $y, $margin + $barW - 1, $y + $barH - 1, $white);
    }

    return $img;
}

$targets = [
    'icon-192.png'           => [192, true,  1.0],
    'icon-512.png'           => [512, true,  1.0],
    'icon-512-maskable.png'  => [512, false, 0.8],
];

foreach ($targets as $file => [$size, $rounded, $scale]) {
    $img = drawPwaTile($size, $rounded, $scale);
    $path = $outDir . '/' . $file;
    if (!imagepng($img, $path)) {
        fwrite(STDERR, 'Failed to write ' . $path . PHP_EOL);
        exit(1);
    }
    echo 'OK ' . $path . ' (' . $size . 'x' . $size . ', ' . ($rounded ? 'rounded' : 'maskable') . ')' . PHP_EOL;
}

echo 'Done.' . PHP_EOL;
