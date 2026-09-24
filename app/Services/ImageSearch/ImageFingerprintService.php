<?php

namespace App\Services\ImageSearch;

/**
 * Builds a compact visual fingerprint (average color + simple histogram) from a local image
 * so we can rank catalog photos by similarity without an external vision API.
 */
class ImageFingerprintService
{
    /**
     * @return array{r: float, g: float, b: float, buckets: list<float>}|null
     */
    public function fromPath(string $path): ?array
    {
        if (! is_readable($path) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            return null;
        }

        // Sample a downscaled grid for speed.
        $sampleW = min(32, $width);
        $sampleH = min(32, $height);
        $thumb = imagecreatetruecolor($sampleW, $sampleH);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $sampleW, $sampleH, $width, $height);

        $sumR = $sumG = $sumB = 0;
        $count = 0;
        $buckets = array_fill(0, 8, 0);

        for ($y = 0; $y < $sampleH; $y++) {
            for ($x = 0; $x < $sampleW; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $sumR += $r;
                $sumG += $g;
                $sumB += $b;
                $count++;

                $bucket = ((int) floor($r / 128) << 2) | ((int) floor($g / 128) << 1) | (int) floor($b / 128);
                $buckets[$bucket]++;
            }
        }

        if ($count === 0) {
            return null;
        }

        $total = array_sum($buckets) ?: 1;

        return [
            'r' => $sumR / $count,
            'g' => $sumG / $count,
            'b' => $sumB / $count,
            'buckets' => array_map(fn ($n) => $n / $total, $buckets),
        ];
    }

    /**
     * @param  array{r: float, g: float, b: float, buckets: list<float>}  $a
     * @param  array{r: float, g: float, b: float, buckets: list<float>}  $b
     */
    public function distance(array $a, array $b): float
    {
        $color = abs($a['r'] - $b['r']) + abs($a['g'] - $b['g']) + abs($a['b'] - $b['b']);
        $hist = 0.0;
        foreach ($a['buckets'] as $i => $value) {
            $hist += abs($value - ($b['buckets'][$i] ?? 0));
        }

        // Lower is more similar.
        return ($color / 255) + $hist;
    }
}
