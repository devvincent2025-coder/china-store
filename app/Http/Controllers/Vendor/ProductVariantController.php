<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Product\ProductPriceTierSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    /**
     * Fills in the price/stock/sku for one generated combination (e.g. "Carton of 24 / Red"). The
     * combination itself (which option values it represents) is fixed once created by
     * ProductOptionSyncService — this only edits the sellable details.
     */
    public function update(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($variant->product_id === $product->id, 404);

        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:100'],
            // Null/blank means "inherit the base product price" — see ProductVariant::getFinalPriceAttribute().
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $variant->update([
            'sku' => $validated['sku'] ?? null,
            'price' => $validated['price'] !== null ? (int) round($validated['price'] * 100) : null,
            'stock' => $validated['stock'],
        ]);

        return back()->with('success', 'Variant updated.');
    }

    /**
     * Lets the vendor edit every generated combination's price/stock/sku and save them all in one
     * request instead of hitting "Save" on each row individually.
     */
    public function bulkUpdate(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['required', 'integer'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
        ]);

        $variantIds = collect($validated['variants'])->pluck('id');
        $ownedCount = $product->variants()->whereIn('id', $variantIds)->count();

        abort_unless($ownedCount === $variantIds->unique()->count(), 404);

        DB::transaction(function () use ($validated, $product) {
            foreach ($validated['variants'] as $row) {
                $product->variants()->whereKey($row['id'])->update([
                    'sku' => $row['sku'] ?? null,
                    'price' => $row['price'] !== null ? (int) round($row['price'] * 100) : null,
                    'stock' => $row['stock'],
                ]);
            }
        });

        return back()->with('success', 'All variants updated.');
    }

    /**
     * Bulk-pricing tiers for one specific variant (e.g. "Carton of 24") — separate from update()
     * above since a tier list is its own repeater UI in Edit.jsx, saved independently of the
     * variant's own sku/price/stock row.
     */
    public function syncTiers(Request $request, Product $product, ProductVariant $variant, ProductPriceTierSyncService $tierSync): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($variant->product_id === $product->id, 404);

        $validated = $request->validate([
            'price_tiers' => ['nullable', 'array'],
            'price_tiers.*.min_quantity' => ['required_with:price_tiers', 'integer', 'min:1'],
            'price_tiers.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'price_tiers.*.price' => ['required_with:price_tiers', 'numeric', 'min:0'],
        ]);

        $tierSync->syncForVariant($variant, $validated['price_tiers'] ?? []);

        return back()->with('success', 'Tier pricing updated.');
    }
}
