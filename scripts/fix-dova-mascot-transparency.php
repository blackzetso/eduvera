<?php

/**
 * Remove baked-in checkerboard backgrounds and export true-alpha mascot assets.
 */

$root = dirname(__DIR__);
$srcDir = getenv('DOVA_ASSETS_SRC')
    ?: 'C:/Users/NPS/.cursor/projects/c-laragon-www-Laravel-Project/assets';
$destDir = $root.'/public/brand/dova';

$mascots = [
    'dova-mascot-welcome',
    'dova-mascot-thinking',
    'dova-mascot-explaining',
    'dova-mascot-success',
    'dova-mascot-help',
];

function isBackgroundPixel(int $r, int $g, int $b): bool
{
    $maxDiff = max(abs($r - $g), abs($g - $b), abs($r - $b));
    $avg = (int) round(($r + $g + $b) / 3);

    // Baked checkerboard: neutral light grey (~237) and white (~255)
    if ($maxDiff <= 22 && $avg >= 220) {
        return true;
    }

    // Dashed export border hugging the canvas edge
    if ($maxDiff <= 8 && $avg <= 32) {
        return true;
    }

    return false;
}

/**
 * @return array<string, true>
 */
function floodBackgroundMask(GdImage $img): array
{
    $w = imagesx($img);
    $h = imagesy($img);
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

        if (! isBackgroundPixel($r, $g, $b)) {
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

    return $mask;
}

/**
 * Remove checkerboard trapped in interior gaps (e.g. between sleeve and body).
 *
 * @param  array<string, true>  $mask
 * @return array<string, true>
 */
function expandCheckerHoles(GdImage $img, array $mask): array
{
    $w = imagesx($img);
    $h = imagesy($img);
    $queue = new SplQueue;

    foreach ($mask as $key => $_) {
        [$x, $y] = array_map('intval', explode(',', $key, 2));

        foreach ([[$x - 1, $y], [$x + 1, $y], [$x, $y - 1], [$x, $y + 1]] as [$nx, $ny]) {
            if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                continue;
            }

            $neighborKey = "{$nx},{$ny}";
            if (isset($mask[$neighborKey])) {
                continue;
            }

            $rgba = imagecolorat($img, $nx, $ny);
            $r = ($rgba >> 16) & 255;
            $g = ($rgba >> 8) & 255;
            $b = $rgba & 255;

            if (! isBackgroundPixel($r, $g, $b)) {
                continue;
            }

            $mask[$neighborKey] = true;
            $queue->enqueue([$nx, $ny]);
        }
    }

    while (! $queue->isEmpty()) {
        [$x, $y] = $queue->dequeue();

        foreach ([[$x - 1, $y], [$x + 1, $y], [$x, $y - 1], [$x, $y + 1]] as [$nx, $ny]) {
            if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                continue;
            }

            $neighborKey = "{$nx},{$ny}";
            if (isset($mask[$neighborKey])) {
                continue;
            }

            $rgba = imagecolorat($img, $nx, $ny);
            $r = ($rgba >> 16) & 255;
            $g = ($rgba >> 8) & 255;
            $b = $rgba & 255;

            if (! isBackgroundPixel($r, $g, $b)) {
                continue;
            }

            $mask[$neighborKey] = true;
            $queue->enqueue([$nx, $ny]);
        }
    }

    return $mask;
}

/**
 * @param  array<string, true>  $mask
 * @return array<string, true>
 */
function removeEnclosedCheckerIslands(GdImage $img, array $mask): array
{
    $w = imagesx($img);
    $h = imagesy($img);
    $visited = [];

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $key = "{$x},{$y}";
            if (isset($mask[$key]) || isset($visited[$key])) {
                continue;
            }

            $rgba = imagecolorat($img, $x, $y);
            $r = ($rgba >> 16) & 255;
            $g = ($rgba >> 8) & 255;
            $b = $rgba & 255;

            if (! isBackgroundPixel($r, $g, $b)) {
                continue;
            }

            $component = [];
            $queue = new SplQueue;
            $queue->enqueue([$x, $y]);
            $visited[$key] = true;
            $component[$key] = true;
            $touchesExterior = false;

            while (! $queue->isEmpty()) {
                [$cx, $cy] = $queue->dequeue();

                foreach ([[$cx - 1, $cy], [$cx + 1, $cy], [$cx, $cy - 1], [$cx, $cy + 1]] as [$nx, $ny]) {
                    if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                        continue;
                    }

                    $neighborKey = "{$nx},{$ny}";

                    if (isset($mask[$neighborKey])) {
                        $touchesExterior = true;
                        continue;
                    }

                    if (isset($visited[$neighborKey])) {
                        continue;
                    }

                    $nrgba = imagecolorat($img, $nx, $ny);
                    $nr = ($nrgba >> 16) & 255;
                    $ng = ($nrgba >> 8) & 255;
                    $nb = $nrgba & 255;

                    if (! isBackgroundPixel($nr, $ng, $nb)) {
                        continue;
                    }

                    $visited[$neighborKey] = true;
                    $component[$neighborKey] = true;
                    $queue->enqueue([$nx, $ny]);
                }
            }

            if (! $touchesExterior) {
                foreach ($component as $componentKey => $_) {
                    $mask[$componentKey] = true;
                }
            }
        }
    }

    return $mask;
}

function exportTransparent(GdImage $src, string $pngPath, string $webpPath, array $mask): void
{
    $w = imagesx($src);
    $h = imagesy($src);

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

            $rgba = imagecolorat($src, $x, $y);
            $r = ($rgba >> 16) & 255;
            $g = ($rgba >> 8) & 255;
            $b = $rgba & 255;
            $color = imagecolorallocatealpha($out, $r, $g, $b, 0);
            imagesetpixel($out, $x, $y, $color);
        }
    }

    $pngTmp = $pngPath.'.tmp';
    $webpTmp = $webpPath.'.tmp';

    if (! imagepng($out, $pngTmp, 6)) {
        imagedestroy($out);
        throw new RuntimeException("Failed to write PNG: {$pngPath}");
    }

    if (! imagewebp($out, $webpTmp, 92)) {
        @unlink($pngTmp);
        imagedestroy($out);
        throw new RuntimeException("Failed to write WebP: {$webpPath}");
    }

    imagedestroy($out);

    rename($pngTmp, $pngPath);
    rename($webpTmp, $webpPath);
}

if (! is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

foreach ($mascots as $name) {
    $png = "{$srcDir}/{$name}.png";

    if (! file_exists($png)) {
        fwrite(STDERR, "Missing source: {$png}\n");
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

    $mask = removeEnclosedCheckerIslands(
        $img,
        expandCheckerHoles($img, floodBackgroundMask($img))
    );
    $outPng = "{$destDir}/{$name}.png";
    $outWebp = "{$destDir}/{$name}.webp";

    exportTransparent($img, $outPng, $outWebp, $mask);
    imagedestroy($img);

    echo "Fixed {$name}: removed ".count($mask)." background pixels\n";
    echo "  -> {$outPng}\n";
    echo "  -> {$outWebp}\n";
}
