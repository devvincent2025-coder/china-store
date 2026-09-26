<?php

namespace App\Services\Image;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Poof (poof.bg) background removal — this is what actually gives a casually-snapped vendor photo
 * the clean, studio-catalog look (Jumia/Amazon-style), by cutting the product out from whatever
 * cluttered table/floor/wall it was photographed against. Plain PHP (GD/Imagick, what
 * App\Services\Image\ImageCompressionService uses) has no way to do this itself — telling "product"
 * from "background" in an arbitrary casual photo is a machine-learning segmentation problem, not
 * something a pixel filter can approximate.
 *
 * Built against Poof's documented contract (docs.poof.bg/api-reference/remove-background) — POST
 * multipart to https://api.poof.bg/v1/remove, auth via the `x-api-key` header, image bytes under
 * the `image_file` field, response is the raw output image bytes (transparent PNG by default) on
 * success or a flat `{code, message, ...}` JSON error body on failure.
 *
 * Falls back to silently doing nothing whenever POOF_API_KEY isn't set (isConfigured() false) —
 * same pattern as App\Services\Sms\SmsService when no gateway account is configured — so the app
 * behaves exactly as it did before this feature existed until an admin actually adds a key.
 */
class PoofBackgroundRemovalService
{
    private const ENDPOINT = 'https://api.poof.bg/v1/remove';

    public function isConfigured(): bool
    {
        return filled(config('services.poof.api_key')) && (bool) config('services.poof.enabled');
    }

    /**
     * @param  string  $binaryImageData  the original upload's raw bytes
     * @return string  the background-removed image's raw bytes — a transparent PNG (RGBA) by
     *   default, cropped tight to the product with a small margin. Caller is expected to flatten
     *   any transparency onto a solid background itself — see
     *   ImageCompressionService::flattenToWhiteBackground(), which already does exactly that for
     *   any PNG upload with an alpha channel, transparent or not.
     *
     * @throws PoofBackgroundRemovalException
     */
    public function removeBackground(string $binaryImageData): string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => config('services.poof.api_key')])
                ->attach('image_file', $binaryImageData, 'upload.jpg')
                ->post(self::ENDPOINT, [
                    // Tight, consistent framing across every vendor's photos — the single biggest
                    // visual difference between "casual snapshot" and "catalog photo" besides the
                    // background itself.
                    'crop' => 'true',
                    'padding' => '8%',
                ]);
        } catch (\Throwable $e) {
            Log::error('Poof background removal request failed', ['message' => $e->getMessage()]);

            throw new PoofBackgroundRemovalException('Could not reach Poof background removal API: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $this->throwForErrorResponse($response);
        }

        return $response->body();
    }

    /**
     * Poof's error body is flat JSON: {"code", "message", "details"?, "request_id", "doc_url"}.
     * 402 (out of credits) and 429 (rate limited) are the two an operator most needs to notice —
     * everything else (400/401/403/422/5xx) just means this one photo couldn't be processed.
     */
    private function throwForErrorResponse($response): never
    {
        $body = $response->json();
        $message = $body['message'] ?? $response->body();
        $code = $body['code'] ?? $response->status();

        Log::error('Poof background removal rejected the image', [
            'status' => $response->status(),
            'code' => $code,
            'message' => $message,
        ]);

        throw new PoofBackgroundRemovalException("Poof background removal failed ({$code}): {$message}");
    }
}
