<?php

namespace App\Services\Export;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ProductExportQuery
{
    public static function build(?Vendor $vendor, Request $request): Collection
    {
        $query = Product::query()
            ->with(['vendor', 'category'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($vendor, fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $term = strtolower($term);
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(JSON_EXTRACT(title, "$.en")) LIKE ?', ["%{$term}%"])
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('category'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->boolean('low_stock'), fn ($q) => $q->where('stock', '<=', 5))
            ->latest();

        return $query->get();
    }
}
