<?php

$path = dirname(__DIR__).'/public/brand/dova/dova-mascot-welcome.png';
$img = imagecreatefrompng($path);
$w = imagesx($img);
$h = imagesy($img);
$remnants = 0;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $c = imagecolorat($img, $x, $y);
        $a = ($c >> 24) & 0x7F;
        if ($a >= 100) {
            continue;
        }

        $r = ($c >> 16) & 255;
        $g = ($c >> 8) & 255;
        $b = $c & 255;
        $maxDiff = max(abs($r - $g), abs($g - $b), abs($r - $b));
        $avg = (int) round(($r + $g + $b) / 3);

        if ($maxDiff <= 22 && $avg >= 220) {
            $remnants++;
        }
    }
}

echo "Checker-like opaque pixels remaining: {$remnants}\n";
