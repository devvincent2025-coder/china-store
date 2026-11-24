<?php

namespace App\Services\Home;

use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Turns the admin-managed home_sections rows into the `sections` prop Pages/Home.jsx renders.
 *
 * Product sections show their hand-picked items first (in admin order, only products that are
 * still publicly listed, only items inside their own schedule), then — when auto_fill is on —
 * top up to item_limit with automatic picks. A product is never shown twice on the page.
 */
class HomePageBuilder
{
    /** @var array<int, true> product ids already placed on the page */
    private array $used = [];

    /** @return Collection<int, HomeSection> */
    public function liveSections(): Collection
    {
        return HomeSection::query()
            ->live()
            ->with(['items' => fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()))])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function build(): array
    {
        $this->used = [];
        $sections = $this->liveSections();

        // Hand-picked products first across ALL sections, so automatic top-ups never steal an
        // item an admin explicitly placed further down the page.
        $picked = $this->pickedProducts($sections);
        foreach ($picked as $product) {
            $this->used[$product->id] = true;
        }

        return $sections->map(fn (HomeSection $section) => [
            'id' => $section->id,
            'type' => $section->type,
            'title' => $section->getTranslations('title'),
            'eyebrow' => $section->getTranslations('eyebrow'),
            'subtitle' => $section->getTranslations('subtitle'),
            'settings' => $section->settings ?? (object) [],
            'ends_at' => $section->ends_at?->toIso8601String(),
            'products' => $section->hasProducts() ? $this->productsFor($section, $picked)->values() : [],
        ])->values()->all();
    }

    /** @return Collection<int, Product> keyed by id */
    private function pickedProducts(Collection $sections): Collection
    {
        $ids = $sections->filter->hasProducts()->flatMap(fn ($s) => $s->items->pluck('product_id'))->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return $this->listed()->whereIn('id', $ids)->get()->keyBy('id');
    }

    private function productsFor(HomeSection $section, Collection $picked): Collection
    {
        $limit = max(1, $section->item_limit);

        $products = $section->items
            ->map(fn ($item) => $picked->get($item->product_id))
            ->filter()
            ->unique('id')
            ->take($limit)
            ->values();

        if ($section->auto_fill && $products->count() < $limit) {
            $fill = $this->autoFill($section->type, $limit - $products->count());
            $products = $products->concat($fill);
        }

        return $products;
    }

    private function autoFill(string $type, int $count): Collection
    {
        $query = $this->listed()->whereNotIn('id', array_keys($this->used));

        $products = match ($type) {
            // Featured first, then most viewed.
            'flash_deals' => $query->orderByDesc('is_featured')->orderByDesc('views_count')->limit($count)->get(),
            'collection' => $query->orderByDesc('views_count')->limit($count)->get(),
            default => $query->inRandomOrder()->limit($count)->get(),
        };

        foreach ($products as $product) {
            $this->used[$product->id] = true;
        }

        return $products;
    }

    private function listed()
    {
        return Product::query()->publiclyListed()->with(['images', 'vendor.store']);
    }
}
