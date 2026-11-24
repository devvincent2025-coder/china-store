import { Link, router } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import { formatNaira } from '@/utils/money';
import { priceOf, wasPriceOf } from '@/utils/productPrice';
import { t } from '@/utils/translatable';
import DiscountBadge from '@/Components/DiscountBadge';
import { useTranslation } from '@/hooks/useTranslation';
import { showToast } from '@/Components/ToastHost';
import AddToCartModal from '@/Components/AddToCartModal';

export default function ProductCard({ product }) {
    const { __ } = useTranslation();
    const image = product.images?.find((img) => img.is_primary) ?? product.images?.[0];
    const imageUrl = image?.enhanced_url ?? image?.original_url ?? null;
    const sold = Number(product.views_count) || 0;
    const hasDiscount = Number(product.discount_percentage) > 0;
    const href = route('products.show', product.slug);
    const [adding, setAdding] = useState(false);
    // Quick-view payload (options/variants) for products with variations — kept after closing
    // so the modal's leave transition still has content to animate out.
    const [quickView, setQuickView] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);

    const addDirectly = (quantity) => {
        router.post(
            route('cart.store'),
            { product_id: product.id, quantity },
            {
                preserveScroll: true,
                preserveState: true,
                // Only refresh what changes — a full reload would re-shuffle randomised product grids.
                only: ['flash', 'cartCount', 'errors'],
                onError: (errors) =>
                    showToast(
                        'error',
                        errors.quantity ?? errors.product_id ?? Object.values(errors)[0] ?? __('Could not add this item to your cart.'),
                    ),
                onFinish: () => setAdding(false),
            },
        );
    };

    // Card data doesn't say whether a product has variants (it comes from a dozen different
    // listing queries), so look it up first: options -> picker modal, none -> straight into cart.
    const addToCart = async () => {
        if (adding) return;
        setAdding(true);

        try {
            const { data } = await axios.get(route('products.quick-view', product.slug));
            const details = data.product;

            if (details.options?.length > 0) {
                setQuickView(details);
                setModalOpen(true);
                setAdding(false);
                return;
            }

            // Start at the minimum order quantity (bulk-tier products can't be bought in ones) —
            // CartController re-checks this, plus stock, server-side.
            addDirectly(Math.max(1, Number(details.min_order_quantity) || 1));
        } catch {
            setAdding(false);
            showToast('error', __('Could not add this item to your cart.'));
        }
    };

    return (
        <div className="group flex flex-col overflow-hidden rounded-2xl bg-white p-1.5 shadow-soft transition duration-300 hover:-translate-y-1 hover:shadow-float">
            <div className="relative aspect-square w-full overflow-hidden rounded-xl bg-gradient-to-br from-[#fafafa] to-tb-cream">
                <Link href={href} className="absolute inset-0" aria-label={t(product.title)} tabIndex={-1} />
                {hasDiscount && (
                    <DiscountBadge percentage={product.discount_percentage} className="absolute left-2 top-2 z-10" />
                )}
                {imageUrl ? (
                    <img
                        src={imageUrl}
                        alt={t(product.title)}
                        className="pointer-events-none h-full w-full object-cover transition duration-500 group-hover:scale-[1.06]"
                        loading="lazy"
                    />
                ) : (
                    <div className="pointer-events-none flex h-full w-full items-center justify-center text-neutral-300">
                        <svg className="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 16.5l4.5-4.5a2 2 0 012.8 0l3.2 3.2m0 0l2-2a2 2 0 012.8 0L21 16.5M3 5.5h18v13H3v-13z" />
                        </svg>
                    </div>
                )}
                {/* Touch screens: always-visible round cart icon. Mouse/trackpad: full "Add to Cart"
                    pill that slides up on hover (or keyboard focus). */}
                <button
                    type="button"
                    onClick={addToCart}
                    disabled={adding}
                    aria-label={__('Add to Cart')}
                    className="cin-gradient absolute bottom-2 right-2 z-10 flex h-9 w-9 items-center justify-center gap-1.5 rounded-full font-display text-[12px] font-bold text-white shadow-glow transition duration-300 hover:brightness-110 active:scale-95 disabled:cursor-wait disabled:opacity-80 [@media(hover:hover)]:left-2 [@media(hover:hover)]:h-8 [@media(hover:hover)]:w-auto [@media(hover:hover)]:translate-y-2 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:translate-y-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:focus-visible:translate-y-0 [@media(hover:hover)]:focus-visible:opacity-100"
                >
                    {adding ? (
                        <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden>
                            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.3" strokeWidth="3" />
                            <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                        </svg>
                    ) : (
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.2 2.4A1 1 0 006.7 17H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    )}
                    <span className="hidden [@media(hover:hover)]:inline">{adding ? __('Adding…') : __('Add to Cart')}</span>
                </button>
            </div>
            <Link href={href} className="flex flex-1 flex-col gap-1 px-1.5 pb-1.5 pt-2.5">
                <h3 className="line-clamp-2 min-h-[36px] text-[12.5px] font-medium leading-[18px] text-tb-text">
                    {t(product.title)}
                </h3>
                <span className="inline-flex w-fit items-center gap-1 rounded-full bg-tb-green/10 px-2 py-0.5 text-[10px] font-semibold text-tb-green">
                    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.4} d="M5 13l4 4L19 7" />
                    </svg>
                    {__('Shipping insurance')}
                </span>
                <div className="mt-auto flex flex-wrap items-baseline justify-between gap-x-1 gap-y-0.5 pt-1">
                    <span className="flex items-baseline gap-1.5">
                        <span className="font-display text-[17px] font-extrabold leading-none tracking-tight text-tb">
                            {formatNaira(priceOf(product))}
                        </span>
                        {wasPriceOf(product) && (
                            <span className="text-[11px] text-neutral-400 line-through">
                                {formatNaira(wasPriceOf(product))}
                            </span>
                        )}
                    </span>
                    <span className="truncate text-[10.5px] text-tb-muted">
                        {sold > 0 ? __(':count people purchased', { count: sold }) : __('New')}
                    </span>
                </div>
            </Link>

            {quickView && <AddToCartModal show={modalOpen} product={quickView} onClose={() => setModalOpen(false)} />}
        </div>
    );
}
