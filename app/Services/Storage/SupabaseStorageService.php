<?php

namespace App\Services\Storage;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin wrapper around Supabase Storage's REST API — there is no official Laravel package, so every
 * call here is a plain HTTP request built from Supabase's published API reference. This replaces
 * the Cloudinary integration used earlier; the two are NOT equivalent, so a couple of behavioral
 * notes are worth being explicit about:
 *
 *   - Supabase Storage is plain object storage. It does not offer Cloudinary-style AI background
 *     removal or auto color/exposure correction. What it DOES offer (on projects with the Image
 *     Transformation add-on enabled) is on-the-fly resize/format/quality via a render endpoint —
 *     see optimizedPublicUrl() below, which App\Jobs\EnhanceProductImage now uses as the closest
 *     available substitute for "enhancement". If that add-on isn't enabled on your project, this
 *     method still returns a working URL, just without the transformation query params applied.
 *   - Vendor verification documents (ID/CAC uploads) are sensitive, so they go in a PRIVATE bucket
 *     and are only ever accessed via short-lived signed URLs generated on demand — never a
 *     permanent public link. Product images and banners are genuinely public storefront assets and
 *     go in the public bucket.
 *
 * All calls use the service_role key, which bypasses Supabase's row-level security / storage
 * policies entirely — this key must NEVER be exposed to the frontend, only used server-side here.
 */
class SupabaseStorageService
{
    // Not readonly: each falls back to config() when the constructor arg is omitted, which means
    // reassigning the promoted property inside the constructor body below — PHP treats that as a
    // second write to a readonly property and throws, since promotion already counts as the first.
    public function __construct(
        private string $baseUrl = '',
        private string $serviceRoleKey = '',
        private string $publicBucket = '',
        private string $privateBucket = '',
    ) {
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : (string) config('services.supabase.url'), '/');
        $this->serviceRoleKey = $serviceRoleKey !== '' ? $serviceRoleKey : (string) config('services.supabase.service_role_key');
        $this->publicBucket = $publicBucket !== '' ? $publicBucket : (string) config('services.supabase.public_bucket');
        $this->privateBucket = $privateBucket !== '' ? $privateBucket : (string) config('services.supabase.private_bucket');
    }

    /**
     * Uploads a genuinely public asset (product image, banner, store logo/banner) and returns its
     * permanent public URL alongside the storage path (kept so the record can be deleted later).
     */
    public function uploadPublic(string $filePath, string $folder = 'uploads'): array
    {
        $path = $this->upload($this->publicBucket, $filePath, $folder);

        return [
            'path' => $path,
            'url' => $this->publicUrl($path),
        ];
    }

    /**
     * Uploads a sensitive document (vendor ID/CAC) to the private bucket. Returns only the storage
     * path — deliberately no URL, since access must always go through a freshly generated signed
     * URL (see signedUrl()) rather than a link that could be shared or cached indefinitely.
     */
    public function uploadPrivate(string $filePath, string $folder = 'vendor-documents'): string
    {
        return $this->upload($this->privateBucket, $filePath, $folder);
    }

    /**
     * Generates a time-limited URL for a private-bucket object — used in the Filament admin panel
     * when a reviewer opens a vendor's submitted documents. Defaults to 5 minutes, long enough for
     * one review session without leaving a durable public link behind.
     */
    public function signedUrl(string $path, int $expiresInSeconds = 300): string
    {
        $response = $this->client()->post(
            "{$this->baseUrl}/storage/v1/object/sign/{$this->privateBucket}/{$path}",
            ['expiresIn' => $expiresInSeconds],
        );

        if ($response->failed()) {
            throw new SupabaseStorageException('Failed to create a signed URL: '.$response->body());
        }

        return $this->baseUrl.'/storage/v1'.$response->json('signedURL');
    }

    /**
     * Uploads already-encoded image bytes (e.g. the output of
     * App\Services\Image\ImageCompressionService::compress()) rather than a local file path —
     * used for the compressed/"enhanced" copy of a product photo, which only ever exists in memory.
     */
    public function uploadPublicBytes(string $bytes, string $folder, string $extension = 'jpg', string $mimeType = 'image/jpeg'): array
    {
        $path = trim($folder, '/').'/'.(string) Str::uuid().".{$extension}";

        $response = $this->client()
            ->withBody($bytes, $mimeType)
            ->withHeaders(['x-upsert' => 'false'])
            ->post("{$this->baseUrl}/storage/v1/object/{$this->publicBucket}/{$path}");

        if ($response->failed()) {
            throw new SupabaseStorageException("Failed to upload compressed image to Supabase Storage bucket [{$this->publicBucket}]: ".$response->body());
        }

        return [
            'path' => $path,
            'url' => $this->publicUrl($path),
        ];
    }

    /**
     * The closest available substitute for Cloudinary's auto quality/format enhancement — resizes
     * and re-encodes a PUBLIC bucket image on the fly via Supabase's render/image endpoint. Requires
     * the Image Transformation add-on to be enabled on the Supabase project; if it isn't, Supabase
     * still serves the original at this URL, just without applying the transform.
     */
    public function optimizedPublicUrl(string $path, int $width = 1200, int $quality = 80): string
    {
        return "{$this->baseUrl}/storage/v1/render/image/public/{$this->publicBucket}/{$path}"
            ."?width={$width}&quality={$quality}&resize=contain";
    }

    public function publicUrl(string $path): string
    {
        return "{$this->baseUrl}/storage/v1/object/public/{$this->publicBucket}/{$path}";
    }

    public function deletePublic(string $path): void
    {
        $this->delete($this->publicBucket, $path);
    }

    public function deletePrivate(string $path): void
    {
        $this->delete($this->privateBucket, $path);
    }

    private function upload(string $bucket, string $filePath, string $folder): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $path = trim($folder, '/').'/'.(string) Str::uuid().($extension ? ".{$extension}" : '');

        $response = $this->client()
            ->withBody(file_get_contents($filePath), $this->mimeType($filePath))
            ->withHeaders(['x-upsert' => 'false'])
            ->post("{$this->baseUrl}/storage/v1/object/{$bucket}/{$path}");

        if ($response->failed()) {
            throw new SupabaseStorageException("Failed to upload to Supabase Storage bucket [{$bucket}]: ".$response->body());
        }

        return $path;
    }

    private function delete(string $bucket, string $path): void
    {
        $response = $this->client()->delete("{$this->baseUrl}/storage/v1/object/{$bucket}", [
            'prefixes' => [$path],
        ]);

        if ($response->failed()) {
            throw new SupabaseStorageException("Failed to delete [{$path}] from Supabase Storage bucket [{$bucket}]: ".$response->body());
        }
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'apikey' => $this->serviceRoleKey,
        ])->acceptJson()->timeout(30);
    }

    private function mimeType(string $filePath): string
    {
        return mime_content_type($filePath) ?: 'application/octet-stream';
    }
}
