<?php

/**
 * Remove solid black background from Dova launcher icon → true alpha PNG.
 */

$src = $argv[1] ?? dirname(__DIR__).'/public/brand/dova/dova-icon.png';
$dest = $argv[2] ?? dirname(__DIR__).'/public/brand/dova/dova-icon.png';

if (! is_file($src)) {
    fwrite(STDERR, "Source not found: {$src}\n");
    exit(1);
}

$img = @imagecreatefrompng($src);
if (! $img) {
    $img = @imagecreatefromjpeg($src);
}
if (! $img) {
    $img = @imagecreatefromwebp($src);
}
if (! $img) {
    fwrite(STDERR, "Failed to load image\n");
    exit(1);
}

$w = imagesx($img);
$h = imagesy($img);

function isIconBackground(int $r, int $g, int $b): bool
{
    if ($r <= 28 && $g <= 28 && $b <= 28) {
        return true;
    }

    $maxDiff = max(abs($r - $g), abs($g - $b), abs($r - $b));
    $avg = (int) round(($r + $g + $b) / 3);

    if ($maxDiff <= 18 && $avg <= 40) {
        return true;
    }

    // Matte fringe / compression edge (opaque gray halo left on the canvas border)
    return $maxDiff <= 10 && $avg <= 118;
}

$mask = [];
$queue = new SplQueue;

$enqueue = function (int $x, int $y) use (&$mask, &$queue, $img): void {
    $key = "{$x},{$y}";
    if (isset($mask[$key])) {
        return;
    }

    $rgba = imagecolorat($img, $x, $y);
    $r = ($rgba >> 16) & 255;
    $g = ($rgba >> 8) & 255;
    $b = $rgba & 255;

    if (! isIconBackground($r, $g, $b)) {
        return;
    }

    $mask[$key] = true;
    $queue->enqueue([$x, $y]);
};

for ($x = 0; $x < $w; $x++) {
    $enqueue($x, 0);
    $enqueue($x, $h - 1);
}

for ($y = 0; $y < $h; $y++) {
    $enqueue(0, $y);
    $enqueue($w - 1, $y);
}

while (! $queue->isEmpty()) {
    [$x, $y] = $queue->dequeue();

    foreach ([[$x - 1, $y], [$x + 1, $y], [$x, $y - 1], [$x, $y + 1]] as [$nx, $ny]) {
        if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
            continue;
        }
        $enqueue($nx, $ny);
    }
}

$out = imagecreatetruecolor($w, $h);
imagealphablending($out, false);
imagesavealpha($out, true);
$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefill($out, 0, 0, $transparent);

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        if (isset($mask["{$x},{$y}"])) {
            continue;
        }

        $rgba = imagecolorat($img, $x, $y);
        $r = ($rgba >> 16) & 255;
        $g = ($rgba >> 8) & 255;
        $b = $rgba & 255;
        $color = imagecolorallocatealpha($out, $r, $g, $b, 0);
        imagesetpixel($out, $x, $y, $color);
    }
}

imagepng($out, $dest, 6);
imagedestroy($img);
imagedestroy($out);

echo "Wrote transparent icon: {$dest}\n";
