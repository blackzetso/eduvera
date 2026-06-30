<?php

/**
 * Crop Dova expression avatars from approved brand style guide art.
 * Source: concept sheet (NOT the Duha reference photo).
 */

$root = dirname(__DIR__);
$source = $root.'/assets/c__Users_NPS_AppData_Roaming_Cursor_User_workspaceStorage_6a051af9a70814aed590b476cdf0420d_images_ChatGPT_Image_5_______2026__12_31_24__-ccb6719e-2622-4525-b822-93ee39b468a8.png';
$destDir = $root.'/public/brand/dova';

if (! file_exists($source)) {
    fwrite(STDERR, "Source not found: {$source}\n");
    exit(1);
}

if (! is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$img = imagecreatefrompng($source);
[$width, $height] = [imagesx($img), imagesy($img)];

echo "Source: {$width}x{$height}\n";

/**
 * Expression row — proportional crops from the style guide (6 busts, left → right).
 * Order: welcome, thinking, happiness, explaining, success, help
 */
$crops = [
    'dova-welcome' => [0.055, 0.56, 0.105, 0.22],
    'dova-thinking' => [0.175, 0.56, 0.105, 0.22],
    'dova-success' => [0.295, 0.56, 0.105, 0.22],
    'dova-explaining' => [0.415, 0.56, 0.105, 0.22],
    'dova-success-alt' => [0.535, 0.56, 0.105, 0.22],
    'dova-help' => [0.655, 0.56, 0.105, 0.22],
];

// Map success-alt to thumbs-up crop; use explaining row item 5 as success if better
$exportMap = [
    'dova-welcome' => 'dova-welcome',
    'dova-thinking' => 'dova-thinking',
    'dova-explaining' => 'dova-explaining',
    'dova-success' => [0.535, 0.56, 0.105, 0.22],
    'dova-help' => 'dova-help',
    'dova-header' => [0.055, 0.56, 0.105, 0.22],
];

function cropRegion($img, int $fullW, int $fullH, array $rel): GdImage
{
    $x = (int) round($rel[0] * $fullW);
    $y = (int) round($rel[1] * $fullH);
    $w = (int) round($rel[2] * $fullW);
    $h = (int) round($rel[3] * $fullH);

    $crop = imagecreatetruecolor($w, $h);
    imagealphablending($crop, false);
    imagesavealpha($crop, true);
    $transparent = imagecolorallocatealpha($crop, 0, 0, 0, 127);
    imagefill($crop, 0, 0, $transparent);
    imagecopy($crop, $img, 0, 0, $x, $y, $w, $h);

    return $crop;
}

function saveWebp(GdImage $crop, string $path): void
{
    imagepalettetotruecolor($crop);
    imagealphablending($crop, true);
    imagesavealpha($crop, true);

    if (! imagewebp($crop, $path, 90)) {
        throw new RuntimeException("Failed writing {$path}");
    }

    imagedestroy($crop);
}

$finalCrops = [
    'dova-welcome' => [0.055, 0.56, 0.105, 0.22],
    'dova-thinking' => [0.175, 0.56, 0.105, 0.22],
    'dova-explaining' => [0.415, 0.56, 0.105, 0.22],
    'dova-success' => [0.535, 0.56, 0.105, 0.22],
    'dova-help' => [0.655, 0.56, 0.105, 0.22],
    'dova-header' => [0.055, 0.56, 0.105, 0.22],
];

foreach ($finalCrops as $name => $rel) {
    $crop = cropRegion($img, $width, $height, $rel);
    $webp = "{$destDir}/{$name}.webp";
    saveWebp($crop, $webp);
    echo "Wrote {$webp}\n";
}

// Larger welcome hero from full-body area (left side of sheet)
$heroCrop = cropRegion($img, $width, $height, [0.02, 0.08, 0.28, 0.48]);
saveWebp($heroCrop, "{$destDir}/dova-welcome-hero.webp");
echo "Wrote {$destDir}/dova-welcome-hero.webp\n";

imagedestroy($img);
