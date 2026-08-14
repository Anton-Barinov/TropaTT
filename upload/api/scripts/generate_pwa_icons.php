<?php
declare(strict_types=1);

/**
 * Generates the PWA and brand icons from the committed master icon
 * (upload/web/assets/icons/icon-512.png — the brand leaf on a transparent
 * background, installed from the official icon set):
 *
 *   icon-192.png          192x192  leaf on transparent (official app icon)
 *   icon-512.png          512x512  leaf on transparent (official app icon)
 *   icon-512-maskable.png 512x512  full-bleed brand tile with the leaf inside
 *                                  the safe zone (80%) — adaptive icons
 *
 * The sidebar/login logo mark (the .crm-brand-mark CSS background) uses the
 * original apple-touch-icon.png from the official icon set and is therefore
 * not generated here.
 *
 * Requires the GD extension. Run:
 *
 *   php upload/api/scripts/generate_pwa_icons.php
 *
 * The output overwrites the committed PNGs in upload/web/assets/icons/.
 */

$assetsDir = dirname(__DIR__, 2) . '/web/assets';
$outDir = $assetsDir . '/icons';
$masterPath = $outDir . '/icon-512.png';
if (!is_file($masterPath)) {
    fwrite(STDERR, 'Master icon not found: ' . $masterPath . PHP_EOL);
    exit(1);
}
if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, 'PHP GD extension is required.' . PHP_EOL);
    exit(1);
}

$master = imagecreatefrompng($masterPath);
if (!$master) {
    fwrite(STDERR, 'Cannot decode master icon: ' . $masterPath . PHP_EOL);
    exit(1);
}
$masterSize = imagesx($master);

/**
 * Resamples the master leaf (transparent background) to the given size.
 *
 * @return \GdImage
 */
function drawMasterLeaf(int $size): \GdImage
{
    global $master, $masterSize;
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    imagecopyresampled($img, $master, 0, 0, 0, 0, $size, $size, $masterSize, $masterSize);
    return $img;
}

/**
 * Draws a brand tile: brand-green background (gradient, or full-bleed solid)
 * with the master leaf composited in the centre.
 *
 * @return \GdImage
 */
function drawBrandTile(int $size, float $leafScale, bool $rounded, bool $gradient): \GdImage
{
    global $master, $masterSize;
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    if ($gradient) {
        // 135deg gradient between the brand tokens (#0f8f72 -> #0b725c).
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $t = min(1.0, max(0.0, ($x + $y) / (2 * ($size - 1))));
                $cr = (int)round(15 + (11 - 15) * $t);
                $cg = (int)round(143 + (114 - 143) * $t);
                $cb = (int)round(114 + (92 - 114) * $t);
                $c = imagecolorallocatealpha($img, $cr, $cg, $cb, 0);
                imagesetpixel($img, $x, $y, $c);
                imagecolordeallocate($img, $c);
            }
        }
    } else {
        // Solid brand green, full bleed (required for maskable icons).
        $green = imagecolorallocatealpha($img, 15, 143, 114, 0);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $green);
    }

    if ($rounded) {
        // Punch the four corners (radius ratio 14/64 of the original tile).
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

    // Master leaf, centred at the requested scale.
    $leaf = (int)round($size * $leafScale);
    $offset = (int)(($size - $leaf) / 2);
    imagecopyresampled($img, $master, $offset, $offset, 0, 0, $leaf, $leaf, $masterSize, $masterSize);

    return $img;
}

// Plain leaf resamples of the master (official icons, transparent background).
foreach ([
    'icon-192.png' => 192,
    'icon-512.png' => 512,
] as $file => $size) {
    $img = drawMasterLeaf($size);
    $path = $outDir . '/' . $file;
    if (!imagepng($img, $path)) {
        fwrite(STDERR, 'Failed to write ' . $path . PHP_EOL);
        exit(1);
    }
    echo 'OK ' . $path . ' (' . $size . 'x' . $size . ', leaf)' . PHP_EOL;
}

// Brand tiles: maskable (full-bleed solid).
foreach ([
    'icon-512-maskable.png' => [512, 0.62, false, false],
] as $file => [$size, $leafScale, $rounded, $gradient]) {
    $img = drawBrandTile($size, $leafScale, $rounded, $gradient);
    $path = $outDir . '/' . $file;
    if (!imagepng($img, $path)) {
        fwrite(STDERR, 'Failed to write ' . $path . PHP_EOL);
        exit(1);
    }
    echo 'OK ' . $path . ' (' . $size . 'x' . $size . ', ' . ($rounded ? 'rounded' : 'maskable') . ')' . PHP_EOL;
}

echo 'Done.' . PHP_EOL;
