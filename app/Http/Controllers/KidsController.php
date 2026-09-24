<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer-facing Kids browse page — aggregates Baby & Kids and Toys catalog items.
 */
class KidsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $categoryIds = Category::query()
            ->whereIn('slug', ['baby-kids', 'toys'])
            ->where('is_active', true)
            ->pluck('id');

        $products = Product::query()
            ->publiclyListed()
            ->whereIn('category_id', $categoryIds)
            ->with(['images', 'vendor.store'])
            ->orderByDesc('views_count')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Kids/Index', [
            'products' => $products,
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'seo' => Seo::generic(
                $request,
                'Kids & Baby Products in Nigeria - Shop Verified Vendors',
                'Shop kids and baby products from verified Chinese-run stores on ChinaInNigeria. Naira pricing, fast local delivery, and every order protected by escrow.',
                ['kids products nigeria', 'baby products nigeria', 'toys nigeria', 'chinese vendors nigeria'],
            ),
        ]);
    }
}
