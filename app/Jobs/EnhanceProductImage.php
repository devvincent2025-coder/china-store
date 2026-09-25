<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\Image\ImageCompressionService;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Runs asynchronously after a vendor uploads a product photo — downloads the original upload,
 * compresses it down to a small, predictable file size (~20-30KB target — see
 * App\Services\Image\ImageCompressionService) while keeping it as sharp as possible, and stores the
 * result as a genuinely separate object (`enhanced_url`/`enhanced_storage_path`). This runs as a
 * job rather than inline on upload so the vendor's upload request stays fast — they see the
 * original immediately (ProductImage::getDisplayUrlAttribute() falls back to it) and the storefront
 * picks up the lighter, compressed copy once this finishes.
 */
// Pinned to the "database" queue connection in the constructor below (not QUEUE_CONNECTION's default) — this calls an external API and shouldn't block the request that triggers it, even on a shared host that sets QUEUE_CONNECTION=sync so NOTIFICATIONS send instantly instead (see .env.example). Still needs something processing the "database" queue: a real `queue:work` daemon, or the cron-driven Schedule::command('queue:work --stop-when-empty') in routes/console.php. Doubly true here — running this synchronously would defeat the entire reason it's a
// job in the first place (see the class doc comment above).
class EnhanceProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly ProductImage $productImage)
    {
        $this->onConnection('database');
    }

    public function handle(SupabaseStorageService $storage, ImageCompressionService $compressor): void
    {
        $this->productImage->update(['processing_status' => 'processing']);

        try {
            $original = Http::timeout(30)->get($this->productImage->original_url)->throw()->body();

            $compressed = $compressor->compress($original);

            $uploaded = $storage->uploadPublicBytes($compressed, 'products/enhanced');

            $this->productImage->update([
                'enhanced_url' => $uploaded['url'],
                'enhanced_storage_path' => $uploaded['path'],
                'enhanced_size_bytes' => strlen($compressed),
                'processing_status' => 'done',
            ]);
        } catch (\Throwable $e) {
            $this->productImage->update(['processing_status' => 'failed']);

            throw $e;
        }
    }
}
