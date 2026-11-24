import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import Pagination from '@/Components/Pagination';
import EmptyState from '@/Components/EmptyState';
import ProductGridSkeleton from '@/Components/ProductGridSkeleton';
import PriceRangeSlider from '@/Components/PriceRangeSlider';
import TopPicksSection from '@/Components/TopPicksSection';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ products, categories, stores = [], cities = [], priceBounds, filters, topPicks = [], seo }) {
    const { __ } = useTranslation();

    const RATING_OPTIONS = [
        { value: 4, label: __(':stars & up', { stars: '4★' }) },
        { value: 3, label: __(':stars & up', { stars: '3★' }) },
        { value: 2, label: __(':stars & up', { stars: '2★' }) },
    ];

    const DISCOUNT_OPTIONS = [
        { value: 10, label: __(':percent% off or more', { percent: 10 }) },
        { value: 20, label: __(':percent% off or more', { percent: 20 }) },
        { value: 30, label: __(':percent% off or more', { percent: 30 }) },
    ];

    const bounds = useMemo(() => ({
        min: Number(priceBounds?.min ?? 0),
        max: Math.max(Number(priceBounds?.max ?? 100000), Number(priceBounds?.min ?? 0) + 100),
    }), [priceBounds]);

    // Every filter/sort/pagination visit below is a `preserveState` reload of this same page
    // component (not a navigation to a different route), so a plain local state flag survives the
    // round trip cleanly and can gate a skeleton in place of the stale grid while it loads.
    const [loading, setLoading] = useState(false);
    const [q, setQ] = useState(filters?.q ?? '');
    const [minPrice, setMinPrice] = useState(
        filters?.min_price !== undefined && filters?.min_price !== null && filters?.min_price !== ''
            ? Number(filters.min_price)
            : bounds.min,
    );
    const [maxPrice, setMaxPrice] = useState(
        filters?.max_price !== undefined && filters?.max_price !== null && filters?.max_price !== ''
            ? Number(filters.max_price)
            : bounds.max,
    );

    const applyFilters = (overrides = {}) => {
        const nextMin = overrides.min_price !== undefined ? overrides.min_price : minPrice;
        const nextMax = overrides.max_price !== undefined ? overrides.max_price : maxPrice;
        const priceIsDefault = Number(nextMin) <= bounds.min && Number(nextMax) >= bounds.max;

        router.get(route('products.index'), {
            q: q || undefined,
            min_price: priceIsDefault ? undefined : nextMin,
            max_price: priceIsDefault ? undefined : nextMax,
            category: filters?.category || undefined,
            store: filters?.store || undefined,
            min_rating: filters?.min_rating || undefined,
            min_discount: filters?.min_discount || undefined,
            city: filters?.city || undefined,
            on_sale: filters?.on_sale ? 1 : undefined,
            in_stock: filters?.in_stock ? 1 : undefined,
            featured: filters?.featured ? 1 : undefined,
            verified: filters?.verified ? 1 : undefined,
            sort: filters?.sort || undefined,
            visual: filters?.visual ? 1 : undefined,
            ...overrides,
        }, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const toggleFlag = (key) => {
        applyFilters({ [key]: filters?.[key] ? undefined : 1 });
    };

    const toggleCategory = (categoryId) => {
        applyFilters({ category: String(filters?.category) === String(categoryId) ? undefined : categoryId });
    };

    const clearFilters = () => {
        setQ('');
        setMinPrice(bounds.min);
        setMaxPrice(bounds.max);
        router.get(route('products.index'), {}, {
            preserveState: false,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const activeCount = [
        filters?.q,
        filters?.category,
        filters?.store,
        filters?.min_rating,
        filters?.min_discount,
        filters?.city,
        filters?.on_sale,
        filters?.in_stock,
        filters?.featured,
        filters?.verified,
        filters?.min_price || filters?.max_price,
    ].filter(Boolean).length;

    return (
        <StorefrontLayout categories={categories} showCategoryBar={false}>
            <SeoHead seo={seo} />

            <div className="mx-auto max-w-marketplace px-2 py-6 sm:px-3">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-text">
                            {filters?.visual
                                ? __('Image search results')
                                : filters?.q
                                    ? __('Results for ":term"', { term: filters.q })
                                    : __('')}
                        </h1>
                        <p className="mt-1 font-ui text-sm text-tb-muted">
                            {__(':count items', { count: products.total ?? products.data?.length ?? 0 })}
                            {activeCount > 0 ? ` · ${__(':count filter(s) on', { count: activeCount })}` : ''}
                        </p>
                    </div>
                    {activeCount > 0 && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="font-display text-sm font-bold text-tb hover:underline"
                        >
                            {__('Clear all filters')}
                        </button>
                    )}
                </div>

                <TopPicksSection topPicks={topPicks} />

                {filters?.visual && (
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-tb/20 bg-tb-soft px-4 py-3 font-ui text-sm text-tb-text">
                        <span>{__('Showing products that look similar to your photo.')}</span>
                        <Link href={route('products.index')} className="font-display font-bold text-tb hover:underline">
                            {__('Clear image search')}
                        </Link>
                    </div>
                )}

                <div className="flex flex-col gap-6 lg:flex-row">
                    <aside className="w-full shrink-0 lg:w-72">
                        <div className="rounded-[12px] border border-tb-border bg-white p-4">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="font-display text-[15px] font-bold text-tb-text">{__('Filters')}</h2>
                                {activeCount > 0 && (
                                    <span className="rounded-full bg-tb-soft px-2 py-0.5 font-ui text-[11px] font-bold text-tb">
                                        {activeCount}
                                    </span>
                                )}
                            </div>

                            {/* Keyword */}
                            <FilterSection title={__('Search')}>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        applyFilters();
                                    }}
                                >
                                    <input
                                        type="text"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        placeholder={__('Keyword, brand, item…')}
                                        className="w-full rounded-md border-neutral-300 text-sm focus:border-tb focus:ring-tb"
                                    />
                                </form>
                                {filters?.q && (
                                    <Link
                                        href={`${route('compare.index')}?q=${encodeURIComponent(filters.q)}`}
                                        className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-tb hover:underline"
                                    >
                                        {__('Compare prices for ":term" across sellers', { term: filters.q })}
                                    </Link>
                                )}
                            </FilterSection>

                            {/* Category */}
                            <FilterSection title={__('Category')}>
                                <div className="flex max-h-44 flex-col gap-2 overflow-y-auto pr-1">
                                    {categories.map((cat) => (
                                        <label key={cat.id} className="flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                            <input
                                                type="checkbox"
                                                checked={String(filters?.category) === String(cat.id)}
                                                onChange={() => toggleCategory(cat.id)}
                                                className="rounded border-neutral-300 text-tb focus:ring-tb"
                                            />
                                            <span className="truncate">{t(cat.name)}</span>
                                        </label>
                                    ))}
                                </div>
                            </FilterSection>

                            {/* Price slider */}
                            <FilterSection title={__('Price (₦)')}>
                                <PriceRangeSlider
                                    min={bounds.min}
                                    max={bounds.max}
                                    valueMin={minPrice}
                                    valueMax={maxPrice}
                                    step={Math.max(50, Math.round((bounds.max - bounds.min) / 100))}
                                    onChange={({ min, max }) => {
                                        setMinPrice(min);
                                        setMaxPrice(max);
                                    }}
                                />
                            </FilterSection>

                            {/* Rating */}
                            <FilterSection title={__('Customer rating')}>
                                <div className="flex flex-col gap-2">
                                    {RATING_OPTIONS.map((opt) => (
                                        <label key={opt.value} className="flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                            <input
                                                type="radio"
                                                name="min_rating"
                                                checked={Number(filters?.min_rating) === opt.value}
                                                onChange={() => applyFilters({
                                                    min_rating: Number(filters?.min_rating) === opt.value ? undefined : opt.value,
                                                })}
                                                className="border-neutral-300 text-tb focus:ring-tb"
                                            />
                                            {opt.label}
                                        </label>
                                    ))}
                                    {filters?.min_rating && (
                                        <button
                                            type="button"
                                            onClick={() => applyFilters({ min_rating: undefined })}
                                            className="text-left text-[12px] font-medium text-tb hover:underline"
                                        >
                                            {__('Clear rating')}
                                        </button>
                                    )}
                                </div>
                            </FilterSection>

                            {/* Deals */}
                            <FilterSection title={__('Deals & discounts')}>
                                <label className="mb-2 flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(filters?.on_sale)}
                                        onChange={() => toggleFlag('on_sale')}
                                        className="rounded border-neutral-300 text-tb focus:ring-tb"
                                    />
                                    {__('On sale')}
                                </label>
                                <div className="flex flex-col gap-2">
                                    {DISCOUNT_OPTIONS.map((opt) => (
                                        <label key={opt.value} className="flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                            <input
                                                type="radio"
                                                name="min_discount"
                                                checked={Number(filters?.min_discount) === opt.value}
                                                onChange={() => applyFilters({
                                                    min_discount: Number(filters?.min_discount) === opt.value ? undefined : opt.value,
                                                    on_sale: 1,
                                                })}
                                                className="border-neutral-300 text-tb focus:ring-tb"
                                            />
                                            {opt.label}
                                        </label>
                                    ))}
                                </div>
                            </FilterSection>

                            {/* Availability / quality */}
                            <FilterSection title={__('Availability')}>
                                <label className="mb-2 flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(filters?.in_stock)}
                                        onChange={() => toggleFlag('in_stock')}
                                        className="rounded border-neutral-300 text-tb focus:ring-tb"
                                    />
                                    {__('In stock only')}
                                </label>
                                <label className="mb-2 flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(filters?.featured)}
                                        onChange={() => toggleFlag('featured')}
                                        className="rounded border-neutral-300 text-tb focus:ring-tb"
                                    />
                                    {__('Featured picks')}
                                </label>
                                <label className="flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(filters?.verified)}
                                        onChange={() => toggleFlag('verified')}
                                        className="rounded border-neutral-300 text-tb focus:ring-tb"
                                    />
                                    {__('Verified vendors')}
                                </label>
                            </FilterSection>

                            {/* Stores / suppliers (Alibaba-style supplier filter) */}
                            {stores.length > 0 && (
                                <FilterSection title={__('Store / supplier')}>
                                    <div className="flex max-h-40 flex-col gap-2 overflow-y-auto pr-1">
                                        {stores.map((store) => (
                                            <label key={store.id} className="flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                                <input
                                                    type="checkbox"
                                                    checked={String(filters?.store) === String(store.id)}
                                                    onChange={() => applyFilters({
                                                        store: String(filters?.store) === String(store.id) ? undefined : store.id,
                                                    })}
                                                    className="rounded border-neutral-300 text-tb focus:ring-tb"
                                                />
                                                <span className="truncate">{t(store.name)}</span>
                                            </label>
                                        ))}
                                    </div>
                                </FilterSection>
                            )}

                            {/* Ship from / location */}
                            {cities.length > 0 && (
                                <FilterSection title={__('Ship from')}>
                                    <div className="flex max-h-36 flex-col gap-2 overflow-y-auto pr-1">
                                        {cities.map((city) => (
                                            <label key={city} className="flex cursor-pointer items-center gap-2 text-sm text-tb-text">
                                                <input
                                                    type="radio"
                                                    name="city"
                                                    checked={filters?.city === city}
                                                    onChange={() => applyFilters({
                                                        city: filters?.city === city ? undefined : city,
                                                    })}
                                                    className="border-neutral-300 text-tb focus:ring-tb"
                                                />
                                                {city}
                                            </label>
                                        ))}
                                        {filters?.city && (
                                            <button
                                                type="button"
                                                onClick={() => applyFilters({ city: undefined })}
                                                className="text-left text-[12px] font-medium text-tb hover:underline"
                                            >
                                                {__('Clear location')}
                                            </button>
                                        )}
                                    </div>
                                </FilterSection>
                            )}

                            <button
                                type="button"
                                onClick={() => applyFilters()}
                                className="mt-2 w-full rounded-md bg-tb py-2.5 font-display text-sm font-bold text-white hover:bg-tb-hover"
                            >
                                {__('Apply filters')}
                            </button>
                        </div>
                    </aside>

                    <div className="min-w-0 flex-1">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <p className="font-ui text-sm text-tb-muted">
                                {__('Sort & browse like on major marketplaces')}
                            </p>
                            <select
                                value={filters?.sort ?? ''}
                                onChange={(e) => applyFilters({ sort: e.target.value || undefined })}
                                className="rounded-md border-neutral-300 text-sm focus:border-tb focus:ring-tb"
                            >
                                <option value="">{__('Most popular')}</option>
                                <option value="newest">{__('Newest')}</option>
                                <option value="rating">{__('Top rated')}</option>
                                <option value="price_asc">{__('Price: low to high')}</option>
                                <option value="price_desc">{__('Price: high to low')}</option>
                            </select>
                        </div>

                        {loading ? (
                            <ProductGridSkeleton count={products.data.length || 12} />
                        ) : products.data.length === 0 ? (
                            <EmptyState
                                title={__('No Products Found')}
                                description={__('Try adjusting your filters or search for something else.')}
                                actionHref={route('products.index')}
                                actionLabel={__('Clear filters')}
                            />
                        ) : (
                            <>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5">
                                    {products.data.map((product) => (
                                        <ProductCard key={product.id} product={product} />
                                    ))}
                                </div>
                                <Pagination
                                    paginator={products}
                                    onStart={() => setLoading(true)}
                                    onFinish={() => setLoading(false)}
                                />
                            </>
                        )}
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}

function FilterSection({ title, children }) {
    return (
        <div className="mb-5 border-b border-tb-border pb-5 last:mb-0 last:border-0 last:pb-0">
            <h3 className="mb-3 font-display text-[13px] font-bold text-tb-text">{title}</h3>
            {children}
        </div>
    );
}
