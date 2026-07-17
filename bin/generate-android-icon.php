#!/usr/bin/env php
<?php

/**
 * Generates an Android app icon (legacy mipmap PNGs + adaptive icon
 * foreground bitmaps) from a single square source PNG, using GD (no
 * ImageMagick dependency — `convert`/`identify` aren't available in every
 * environment this runs in). Standalone: no Composer autoload, so it works
 * the same whether called from this framework's own bin/phpx or from an
 * example app's own bundle-android.sh with its own separate vendor/.
 *
 * Usage: php bin/generate-android-icon.php <source.png> <android-res-dir> [background-hex]
 */

/** @var array<int, string> $densities density name => legacy launcher pixel size */
const LEGACY_SIZES = ['mdpi' => 48, 'hdpi' => 72, 'xhdpi' => 96, 'xxhdpi' => 144, 'xxxhdpi' => 192];

/** Adaptive icon full-bleed canvas size per density (108dp equivalent). */
const ADAPTIVE_CANVAS_SIZES = ['mdpi' => 108, 'hdpi' => 162, 'xhdpi' => 216, 'xxhdpi' => 324, 'xxxhdpi' => 432];

/** Content-to-canvas ratio inside the adaptive icon's safe zone. */
const ADAPTIVE_CONTENT_RATIO = 0.66;

function fail(string $message): never
{
    fwrite(STDERR, "generate-android-icon: {$message}\n");
    exit(1);
}

function loadSquareSource(string $path): GdImage
{
    if (!is_file($path)) {
        fail("source icon not found: {$path}");
    }

    $image = @imagecreatefrompng($path);
    if ($image === false) {
        fail("could not read '{$path}' as a PNG — is it a valid PNG file?");
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $size = min($width, $height);

    if ($width === $height) {
        return $image;
    }

    $square = imagecreatetruecolor($size, $size);
    imagealphablending($square, false);
    imagesavealpha($square, true);
    imagefill($square, 0, 0, imagecolorallocatealpha($square, 0, 0, 0, 127));
    imagecopy($square, $image, 0, 0, (int) (($width - $size) / 2), (int) (($height - $size) / 2), $size, $size);

    return $square;
}

function resizeSquare(GdImage $source, int $size): GdImage
{
    $resized = imagecreatetruecolor($size, $size);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

    return $resized;
}

function circularMask(GdImage $source, int $size): GdImage
{
    $masked = imagecreatetruecolor($size, $size);
    imagealphablending($masked, false);
    imagesavealpha($masked, true);
    imagefill($masked, 0, 0, imagecolorallocatealpha($masked, 0, 0, 0, 127));

    $radius = $size / 2;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $dx = $x - $radius + 0.5;
            $dy = $y - $radius + 0.5;
            if ($dx * $dx + $dy * $dy <= $radius * $radius) {
                imagesetpixel($masked, $x, $y, imagecolorat($source, $x, $y));
            }
        }
    }

    return $masked;
}

function adaptiveForeground(GdImage $source, int $canvasSize): GdImage
{
    $canvas = imagecreatetruecolor($canvasSize, $canvasSize);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

    $contentSize = (int) round($canvasSize * ADAPTIVE_CONTENT_RATIO);
    $offset = (int) (($canvasSize - $contentSize) / 2);

    imagealphablending($canvas, true);
    imagecopyresampled(
        $canvas,
        $source,
        $offset,
        $offset,
        0,
        0,
        $contentSize,
        $contentSize,
        imagesx($source),
        imagesy($source),
    );

    return $canvas;
}

function savePng(GdImage $image, string $path): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }

    if (!imagepng($image, $path)) {
        fail("failed to write {$path}");
    }
}

$sourcePath = $argv[1] ?? fail('usage: generate-android-icon.php <source.png> <android-res-dir> [background-hex]');
$resDir = $argv[2] ?? fail('usage: generate-android-icon.php <source.png> <android-res-dir> [background-hex]');
$backgroundHex = $argv[3] ?? '#FFFFFF';

if (!is_dir($resDir)) {
    fail("Android res directory not found: {$resDir}");
}

$source = loadSquareSource($sourcePath);

foreach (LEGACY_SIZES as $density => $size) {
    $resized = resizeSquare($source, $size);
    savePng($resized, "{$resDir}/mipmap-{$density}/ic_launcher.png");
    savePng(circularMask($resized, $size), "{$resDir}/mipmap-{$density}/ic_launcher_round.png");
}

foreach (ADAPTIVE_CANVAS_SIZES as $density => $canvasSize) {
    savePng(adaptiveForeground($source, $canvasSize), "{$resDir}/mipmap-{$density}/ic_launcher_foreground.png");
}

// The bitmap foregrounds above replace the hand-drawn vector — remove it so
// there's no stale, unreferenced drawable left behind.
$oldForeground = "{$resDir}/drawable/ic_launcher_foreground.xml";
if (is_file($oldForeground)) {
    unlink($oldForeground);
}

file_put_contents("{$resDir}/drawable/ic_launcher_background.xml", <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <shape xmlns:android="http://schemas.android.com/apk/res/android" android:shape="rectangle">
        <solid android:color="{$backgroundHex}" />
    </shape>

    XML);

$adaptiveIconXml = <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
        <background android:drawable="@drawable/ic_launcher_background" />
        <foreground android:drawable="@mipmap/ic_launcher_foreground" />
    </adaptive-icon>

    XML;

file_put_contents("{$resDir}/mipmap-anydpi-v26/ic_launcher.xml", $adaptiveIconXml);
file_put_contents("{$resDir}/mipmap-anydpi-v26/ic_launcher_round.xml", $adaptiveIconXml);

// The splash screen theme (windowSplashScreenAnimatedIcon) also points at
// the old vector drawable directly — repoint it at the generated bitmap now
// that the vector is gone, same reason as the adaptive icon XML above.
$themesPath = "{$resDir}/values/themes.xml";
if (is_file($themesPath)) {
    file_put_contents(
        $themesPath,
        str_replace('@drawable/ic_launcher_foreground', '@mipmap/ic_launcher_foreground', file_get_contents($themesPath)),
    );
}

echo "Generated Android icon from {$sourcePath} into {$resDir}\n";
