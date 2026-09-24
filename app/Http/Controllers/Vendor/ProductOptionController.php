<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Product\ProductOptionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductOptionController extends Controller
{
    public function __construct(private readonly ProductOptionSyncService $optionSync)
    {
    }

    /**
     * Replaces this product's full option/value set (e.g. Packaging: Piece/Pack of 6/Carton of 24,
     * Color: Red/Blue) and regenerates the variant matrix — existing variants whose combination
     * survives keep their price/stock/sku; new combinations get a blank variant to fill in; removed
     * combinations are deleted. See ProductOptionSyncService for the matching logic.
     */
    public function sync(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'options' => ['present', 'array'],
            'options.*.name' => ['required', 'array'],
            'options.*.name.en' => ['required', 'string', 'max:100'],
            'options.*.name.zh' => ['nullable', 'string', 'max:100'],
            'options.*.name.fr' => ['nullable', 'string', 'max:100'],
            'options.*.values' => ['required', 'array', 'min:1'],
            'options.*.values.*.en' => ['required', 'string', 'max:100'],
            'options.*.values.*.zh' => ['nullable', 'string', 'max:100'],
            'options.*.values.*.fr' => ['nullable', 'string', 'max:100'],
        ]);

        $this->optionSync->sync($product, $validated['options']);

        return back()->with('success', 'Options updated — fill in price/stock for each new variant below.');
    }
}
