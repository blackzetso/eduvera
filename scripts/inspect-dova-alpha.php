<?php

$dir = dirname(__DIR__).'/public/brand/dova';
$files = array_merge(
    glob($dir.'/dova-mascot-*.png') ?: [],
    glob($dir.'/dova-mascot-*.webp') ?: [],
);

foreach ($files as $path) {
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $img = $ext === 'png' ? @imagecreatefrompng($path) : @imagecreatefromwebp($path);

    if (! $img) {
        echo basename($path).": failed to load\n";
        continue;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $transparent = 0;
    $opaque = 0;
    $checkerOpaque = 0;

    $samples = [
        [0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1],
        [10, 10], [$w - 11, 10],
    ];

    foreach ($samples as [$x, $y]) {
        $c = imagecolorat($img, $x, $y);
        $a = ($c >> 24) & 0x7F;
        $r = ($c >> 16) & 255;
        $g = ($c >> 8) & 255;
        $b = $c & 255;

        if ($a >= 100) {
            $transparent++;
        } else {
            $opaque++;
            if ($r > 170 && $g > 170 && $b > 170) {
                $checkerOpaque++;
            }
        }
    }

    $status = $checkerOpaque === 0 ? 'OK' : 'CHECKER_REMAINS';
    echo basename($path)." [{$status}] transparent={$transparent} opaque={$opaque} checkerOpaque={$checkerOpaque}\n";
    imagedestroy($img);
}
