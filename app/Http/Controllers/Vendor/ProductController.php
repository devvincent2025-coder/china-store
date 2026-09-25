<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Commission\CommissionService;
use App\Services\Product\ProductImageIngestionService;
use App\Services\Product\ProductOptionSyncService;
use App\Services\Product\ProductPriceTierSyncService;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = $request->user()->vendor->products()
            ->with('images', 'category')
            ->when($request->string('q')->toString(), function ($query, $term) {
                $term = strtolower($term);
                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(JSON_EXTRACT(title, "$.en")) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.zh")) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.fr")) LIKE ?', ["%{$term}%"])
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->integer('category'), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($request->boolean('low_stock'), fn ($query) => $query->where('stock', '<=', $request->user()->vendor->low_stock_threshold))
            ->tap(function ($query) use ($request) {
                match ($request->string('sort')->toString()) {
                    'price_asc' => $query->orderBy('price', 'asc'),
                    'price_desc' => $query->orderBy('price', 'desc'),
                    'stock_asc' => $query->orderBy('stock', 'asc'),
                    'stock_desc' => $query->orderBy('stock', 'desc'),
                    'oldest' => $query->oldest(),
                    default => $query->latest(),
                };
            })
            ->paginate(20)
            ->withQueryString();

        // Only categories this vendor actually lists products in — not the full platform category
        // tree, so the filter dropdown stays relevant to what they're actually selling.
        $categoryIds = $request->user()->vendor->products()->distinct()->pluck('category_id')->filter();

        return Inertia::render('Vendor/Products/Index', [
            'vendor' => $request->user()->vendor,
            'products' => $products,
            'categories' => Category::whereIn('id', $categoryIds)->orderBy('sort_order')->get(),
            // Cast to object: an empty PHP array serializes as JSON `[]`, and `filters.sort` on an
            // array resolves to the native Array.prototype.sort function instead of undefined,
            // which then gets passed into useState() as a (wrongly invoked) lazy initializer.
            'filters' => (object) $request->only(['q', 'status', 'category', 'low_stock', 'sort']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Vendor/Products/Create', [
            'categories' => Category::where('is_active', true)->get(),
            'vendor' => $request->user()->vendor,
            // Lets the "Estimated Earnings" panel mirror CommissionService::rateFor() client-side
            // without a round trip per keystroke — see resources/js/utils/commission.js.
            'commissionTiers' => CommissionService::tiers(),
            'vatRatePercent' => Setting::vatRate(),
        ]);
    }

    /**
     * Create is the source of truth for what a product listing needs — a vendor can stage photos
     * (including camera captures) and options/variants right here in the same form used to create
     * the product, rather than being forced into a separate "now go add photos" follow-up step.
     * Both are optional and, being genuinely dependent on the product existing first (foreign keys,
     * storage paths), are only actually persisted after the product row itself is created below.
     */
    public function store(
        Request $request,
        ProductImageIngestionService $imageIngestion,
        ProductOptionSyncService $optionSync,
        ProductPriceTierSyncService $tierSync,
    ): RedirectResponse {
        $validated = $this->validated($request);

        $staged = $request->validate([
            'images' => ['nullable', 'array', 'max:'.ProductImageIngestionService::MAX_PHOTOS_PER_PRODUCT],
            'images.*' => ['image', 'max:10240'],
            'options' => ['nullable', 'array'],
            'options.*.name' => ['required_with:options', 'array'],
            'options.*.name.en' => ['required_with:options', 'string', 'max:100'],
            'options.*.name.zh' => ['nullable', 'string', 'max:100'],
            'options.*.name.fr' => ['nullable', 'string', 'max:100'],
            'options.*.values' => ['required_with:options', 'array', 'min:1'],
            'options.*.values.*.en' => ['required_with:options', 'string', 'max:100'],
            'options.*.values.*.zh' => ['nullable', 'string', 'max:100'],
            'options.*.values.*.fr' => ['nullable', 'string', 'max:100'],
            ...$this->priceTierRules(),
        ]);

        $product = $request->user()->vendor->products()->create([
            ...$validated,
            'slug' => Str::slug($validated['title']['en'] ?? $validated['title']['zh']).'-'.Str::random(6),
            'price' => (int) round($validated['price'] * 100),
            'compare_price' => isset($validated['compare_price']) ? (int) round($validated['compare_price'] * 100) : null,
        ]);

        if (! empty($staged['images'])) {
            $imageIngestion->ingest($product, $staged['images']);
        }

        if (! empty($staged['options'])) {
            $optionSync->sync($product, $staged['options']);
        }

        // Bulk-pricing tiers set at creation only ever apply at the product level — a product
        // that also got options/variants above manages its tiers per-variant instead (see
        // Vendor/Products/Edit.jsx's VariantsTable), so these are simply unused in that case
        // rather than silently conflicting with per-variant tiers.
        if (! empty($staged['price_tiers'])) {
            $tierSync->syncForProduct($product, $staged['price_tiers']);
        }

        return redirect()->route('vendor.products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Vendor/Products/Edit', [
            'product' => $product->load('images', 'variants.optionValues.option', 'variants.priceTiers', 'options.values', 'priceTiers'),
            'categories' => Category::where('is_active', true)->get(),
            'vendor' => $request->user()->vendor,
            'commissionTiers' => CommissionService::tiers(),
            'vatRatePercent' => Setting::vatRate(),
        ]);
    }

    public function update(Request $request, Product $product, ProductPriceTierSyncService $tierSync): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $this->validated($request);
        $tiers = $request->validate($this->priceTierRules())['price_tiers'] ?? [];

        $product->update([
            ...$validated,
            'price' => (int) round($validated['price'] * 100),
            'compare_price' => isset($validated['compare_price']) ? (int) round($validated['compare_price'] * 100) : null,
        ]);

        // Only relevant while this product has no variants — see VariantsTable/store() above for
        // why per-variant tiers take over once options/variants exist. `price_tiers` is only sent
        // by Edit.jsx in the no-variants case, but sync unconditionally (including clearing to
        // empty) so removing every tier a vendor previously added actually takes effect.
        if ($product->variants()->doesntExist()) {
            $tierSync->syncForProduct($product, $tiers);
        }

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('vendor.products.index')->with('success', 'Product deleted.');
    }

    /**
     * Bulk price/stock updates, scoped for v1: a flat percentage price adjustment or a flat stock
     * set, applied to every product id the vendor selects from their own catalog.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer'],
            'action' => ['required', 'in:price_percentage,set_stock'],
            'value' => ['required', 'numeric'],
        ]);

        $products = $request->user()->vendor->products()->whereIn('id', $validated['product_ids'])->get();

        foreach ($products as $product) {
            if ($validated['action'] === 'price_percentage') {
                $product->update(['price' => (int) round($product->price * (1 + $validated['value'] / 100))]);
            } else {
                $product->update(['stock' => (int) $validated['value']]);
            }
        }

        return back()->with('success', 'Bulk update applied to '.$products->count().' products.');
    }

    /**
     * Shape-only validation — overlap/gap/last-tier-open-ended rules are business logic, not
     * simple field rules, so those live in ProductPriceTierSyncService::validateTiers() instead.
     */
    private function priceTierRules(): array
    {
        return [
            'price_tiers' => ['nullable', 'array'],
            'price_tiers.*.min_quantity' => ['required_with:price_tiers', 'integer', 'min:1'],
            'price_tiers.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'price_tiers.*.price' => ['required_with:price_tiers', 'numeric', 'min:0'],
        ];
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'array'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.zh' => ['nullable', 'string', 'max:255'],
            'title.fr' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'description.zh' => ['nullable', 'string'],
            'description.fr' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        // The description arrives as vendor-authored HTML from the rich text editor — strip it
        // down to the bold/italic/underline/list tags the editor can actually produce before it
        // ever reaches the database, since it's rendered unescaped on the public product page.
        if (isset($validated['description'])) {
            foreach ($validated['description'] as $locale => $html) {
                $validated['description'][$locale] = HtmlSanitizer::clean($html);
            }
        }

        return $validated;
    }
}
