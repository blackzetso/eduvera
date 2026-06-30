<?php

$path = dirname(__DIR__).'/public/brand/dova/dova-mascot-welcome.png';
$img = imagecreatefrompng($path);
$w = imagesx($img);
$h = imagesy($img);

for ($x = 0; $x < min(30, $w); $x++) {
    $c = imagecolorat($img, $x, 0);
    printf(
        "top y=0 x=%d: r=%d g=%d b=%d a=%d\n",
        $x,
        ($c >> 16) & 255,
        ($c >> 8) & 255,
        $c & 255,
        ($c >> 24) & 0x7F
    );
}
