<?php

$path = $argv[1] ?? dirname(__DIR__).'/public/brand/dova/dova-mascot-welcome.png';
$img = imagecreatefrompng($path);
$w = imagesx($img);
$h = imagesy($img);

echo basename($path)." ({$w}x{$h})\n";

foreach ([[0, 0], [10, 10], [$w - 1, 0], [$w / 2, 0]] as [$x, $y]) {
    $c = imagecolorat($img, (int) $x, (int) $y);
    printf(
        "(%d,%d) r=%d g=%d b=%d a=%d\n",
        $x,
        $y,
        ($c >> 16) & 255,
        ($c >> 8) & 255,
        $c & 255,
        ($c >> 24) & 0x7F
    );
}
