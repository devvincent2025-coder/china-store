<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\Image\ImageCompressionService;
use App\Services\Image\PoofBackgroundRemovalException;
use App\Services\Image\PoofBackgroundRemovalService;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Runs asynchronously after a vendor uploads a product photo — downloads the original upload,
 * cuts the product out from its background (Poof — see PoofBackgroundRemovalService's doc comment
 * for why this needs a real background-removal API rather than a plain PHP filter) so a casual
 * snapshot ends up on a clean white background like a proper catalog photo, then compresses the
 * result down to a small, predictable file size (~20-30KB target — see
 * App\Services\Image\ImageCompressionService) while keeping it as sharp as possible, and stores the
 * result as a genuinely separate object (`enhanced_url`/`enhanced_storage_path`). This runs as a
 * job rather than inline on upload so the vendor's upload request stays fast — they see the
 * original immediately (ProductImage::getDisplayUrlAttribute() falls back to it) and the storefront
 * picks up the lighter, background-cleaned copy once this finishes.
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

    public function handle(SupabaseStorageService $storage, ImageCompressionService $compressor, PoofBackgroundRemovalService $backgroundRemoval): void
    {
        $this->productImage->update(['processing_status' => 'processing']);

        try {
            $original = Http::timeout(30)->get($this->productImage->original_url)->throw()->body();

            // Background removal is best-effort: a vendor's product photo is far more valuable
            // with an unedited background than not uploaded at all, so a Poof failure (out of
            // credits, a rate limit, a transient outage) falls back to the plain compressed
            // original instead of failing this whole job — see PoofBackgroundRemovalService's doc
            // comment for when this step is skipped entirely (no API key configured at all).
            $forCompression = $original;

            if ($backgroundRemoval->isConfigured()) {
                try {
                    $forCompression = $backgroundRemoval->removeBackground($original);
                } catch (PoofBackgroundRemovalException $e) {
                    Log::warning('Background removal failed — falling back to the original photo, just compressed', [
                        'product_image_id' => $this->productImage->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $compressed = $compressor->compress($forCompression);

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
