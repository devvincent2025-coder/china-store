<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Product\ProductImageIngestionService;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    private const MAX_PHOTOS_PER_PRODUCT = ProductImageIngestionService::MAX_PHOTOS_PER_PRODUCT;

    /**
     * Accepts one or many files under `images[]` — the drag-and-drop uploader on the vendor
     * product edit page always sends an array, even for a single file.
     */
    public function store(Request $request, Product $product, ProductImageIngestionService $ingestion): RedirectResponse
    {
        $this->authorize('update', $product);

        $existingCount = $product->images()->count();

        $validated = $request->validate([
            'images' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) use ($existingCount) {
                    if ($existingCount + count($value) > self::MAX_PHOTOS_PER_PRODUCT) {
                        $fail('A product can have at most '.self::MAX_PHOTOS_PER_PRODUCT.' photos — you already have '.$existingCount.'.');
                    }
                },
            ],
            'images.*' => ['image', 'max:10240'],
        ]);

        $ingestion->ingest($product, $validated['images']);

        return back()->with('success', 'Photos uploaded — optimizing in the background.');
    }

    public function destroy(Request $request, Product $product, int $image, SupabaseStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $product);

        $productImage = $product->images()->findOrFail($image);

        if ($productImage->storage_path) {
            $storage->deletePublic($productImage->storage_path);
        }

        if ($productImage->enhanced_storage_path) {
            $storage->deletePublic($productImage->enhanced_storage_path);
        }

        $productImage->delete();

        return back();
    }

    public function setPrimary(Request $request, Product $product, int $image): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->images()->update(['is_primary' => false]);
        $product->images()->findOrFail($image)->update(['is_primary' => true]);

        return back();
    }
}
