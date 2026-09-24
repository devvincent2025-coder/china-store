<?php

namespace App\Services\Image;

use RuntimeException;

/**
 * Compresses a product photo down to a small, predictable file size (~20-30KB target) while
 * keeping it as visually sharp as possible — this is what actually powers "image enhancement" for
 * the storefront now that Supabase's on-the-fly render transform (a paid add-on, not guaranteed to
 * be enabled, and not size-bounded anyway) isn't a reliable way to hit a byte-size target. Uses PHP's
 * built-in GD extension — no new Composer dependency required.
 *
 * Strategy: for progressively smaller max dimensions, try JPEG quality from high to low and keep
 * the FIRST (highest-quality) encoding that fits under the byte budget — quality has a much bigger
 * visual impact than a moderate dimension reduction, so we always prefer to spend the byte budget
 * on quality before shrinking further.
 */
class ImageCompressionService
{
    private const QUALITY_STEPS = [90, 80, 70, 60, 50, 40, 30, 20];

    private const DIMENSION_FLOOR = 320;

    public function compress(string $binaryData, int $targetMaxBytes = 30 * 1024, int $startDimension = 1600): string
    {
        $source = @imagecreatefromstring($binaryData);

        if ($source === false) {
            throw new RuntimeException('Unable to decode image data for compression.');
        }

        $flattened = $this->flattenToWhiteBackground($source);

        $best = null;
        $dimension = $startDimension;

        while ($dimension >= self::DIMENSION_FLOOR) {
            $resized = $this->resizeToMax($flattened, $dimension);

            foreach (self::QUALITY_STEPS as $quality) {
                $encoded = $this->encodeJpeg($resized, $quality);

                if (strlen($encoded) <= $targetMaxBytes) {
                    return $encoded;
                }

                // Keep the smallest attempt seen so far as a fallback in case NOTHING fits the
                // budget (e.g. an extremely detailed source image) — better to return the closest
                // we got than to throw and lose the upload entirely.
                if ($best === null || strlen($encoded) < strlen($best)) {
                    $best = $encoded;
                }
            }

            $dimension = (int) ($dimension * 0.75);
        }

        return $best ?? throw new RuntimeException('Could not compress image within the target size.');
    }

    /**
     * JPEG has no alpha channel — a transparent PNG upload would otherwise turn solid black once
     * re-encoded, so any transparency is composited onto a white background first.
     */
    private function flattenToWhiteBackground(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        return $canvas;
    }

    private function resizeToMax(\GdImage $image, int $maxDimension): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) <= $maxDimension) {
            $copy = imagecreatetruecolor($width, $height);
            imagecopy($copy, $image, 0, 0, 0, 0, $width, $height);

            return $copy;
        }

        $scale = $maxDimension / max($width, $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // imagescale() uses bicubic-ish resampling by default (IMG_BICUBIC) — noticeably better
        // quality per byte than a nearest-neighbor resize would give at these small dimensions.
        return imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);
    }

    private function encodeJpeg(\GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return ob_get_clean();
    }
}
