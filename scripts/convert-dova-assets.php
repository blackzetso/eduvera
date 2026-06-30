<?php

/**
 * One-off: convert generated Dova PNG avatars to WebP in public/brand/dova/.
 */

$root = dirname(__DIR__);
$srcDir = getenv('DOVA_ASSETS_SRC')
    ?: 'C:/Users/NPS/.cursor/projects/c-laragon-www-Laravel-Project/assets';
$destDir = $root.'/public/brand/dova';

if (! is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$files = [
    'dova-mascot-welcome' => 'dova-mascot-welcome',
    'dova-mascot-thinking' => 'dova-mascot-thinking',
    'dova-mascot-explaining' => 'dova-mascot-explaining',
    'dova-mascot-success' => 'dova-mascot-success',
    'dova-mascot-help' => 'dova-mascot-help',
    'dova-welcome-v2' => 'dova-welcome',
    'dova-thinking-v2' => 'dova-thinking',
    'dova-explaining-v2' => 'dova-explaining',
    'dova-success-v2' => 'dova-success',
    'dova-help-v2' => 'dova-help',
    'dova-header-v2' => 'dova-header',
];

foreach ($files as $key => $value) {
    $sourceName = is_int($key) ? $value : $key;
    $destName = is_int($key) ? $value : $value;
    $png = "{$srcDir}/{$sourceName}.png";
    $webp = "{$destDir}/{$destName}.webp";

    if (! file_exists($png)) {
        fwrite(STDERR, "Missing: {$png}\n");
        exit(1);
    }

    $img = imagecreatefrompng($png);
    if ($img === false) {
        fwrite(STDERR, "Failed to read: {$png}\n");
        exit(1);
    }

    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    if (! imagewebp($img, $webp, 88)) {
        fwrite(STDERR, "Failed to write: {$webp}\n");
        exit(1);
    }

    imagedestroy($img);
    echo "Wrote {$webp}\n";
}
