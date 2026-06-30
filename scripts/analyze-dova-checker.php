<?php

$path = $argv[1] ?? dirname(__DIR__).'/public/brand/dova/dova-mascot-welcome.png';
$img = imagecreatefrompng($path);
$w = imagesx($img);
$h = imagesy($img);

$buckets = [];

for ($y = 0; $y < $h; $y += 2) {
    for ($x = 0; $x < $w; $x += 2) {
        $c = imagecolorat($img, $x, $y);
        $a = ($c >> 24) & 0x7F;
        if ($a >= 100) {
            continue;
        }

        $r = ($c >> 16) & 255;
        $g = ($c >> 8) & 255;
        $b = $c & 255;
        $maxDiff = max(abs($r - $g), abs($g - $b), abs($r - $b));

        if ($maxDiff > 20) {
            continue;
        }

        $avg = (int) round(($r + $g + $b) / 3);
        $bucket = (int) (floor($avg / 8) * 8);
        $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;
    }
}

ksort($buckets);
echo basename($path)." neutral luminance buckets:\n";
foreach ($buckets as $bucket => $count) {
    if ($count < 50) {
        continue;
    }
    echo sprintf("  %3d-%3d: %d\n", $bucket, $bucket + 7, $count);
}
