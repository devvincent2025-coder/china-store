<?php

namespace App\Services\Product;

use App\Jobs\EnhanceProductImage;
use App\Models\Product;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\UploadedFile;

/**
 * Shared upload logic for product photos — used by both Vendor\ProductImageController (adding
 * photos to an existing product) and Vendor\ProductController::store() (photos staged during
 * product creation, submitted in the same request as the product itself).
 */
class ProductImageIngestionService
{
    public const MAX_PHOTOS_PER_PRODUCT = 5;

    public function __construct(private readonly SupabaseStorageService $storage)
    {
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function ingest(Product $product, array $files): void
    {
        $existingCount = $product->images()->count();
        $nextSortOrder = $existingCount;
        $firstImage = null;

        foreach ($files as $file) {
            $uploaded = $this->storage->uploadPublic($file->getRealPath(), 'products');

            $image = $product->images()->create([
                'original_url' => $uploaded['url'],
                'storage_path' => $uploaded['path'],
                'is_primary' => false,
                'sort_order' => $nextSortOrder++,
            ]);

            $firstImage ??= $image;

            // Resize/quality optimization runs async so the vendor gets an immediate preview of
            // the raw upload rather than waiting on the render step.
            EnhanceProductImage::dispatch($image);
        }

        // The very first photo a product ever gets becomes primary automatically, so a product
        // never ends up with photos but no cover image.
        if ($firstImage && $product->images()->count() === count($files)) {
            $firstImage->update(['is_primary' => true]);
        }
    }
}
