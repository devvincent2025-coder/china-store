import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DOMPurify from 'dompurify';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import PriceTag from '@/Components/PriceTag';
import StarRating from '@/Components/StarRating';
import InputError from '@/Components/InputError';
import ImageLightbox from '@/Components/ImageLightbox';
import VariantOptions from '@/Components/VariantOptions';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';
import { formatNaira } from '@/utils/money';
import { minOrderQuantity, resolveTierPrice } from '@/utils/tieredPricing';
import { formatDeliveryEstimate } from '@/utils/deliveryEstimate';
import { defaultSelection, resolveVariant } from '@/utils/variants';
import { priceOf, wasPriceOf } from '@/utils/productPrice';

function ReportProductButton({ product }) {
    const { __ } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        reason: 'misleading',
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('products.report', product.slug), {
            preserveScroll: true,
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="mt-4 flex items-center gap-1 text-xs text-neutral-400 hover:text-red-500"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 3v18m0-18h11l-1.5 4.5L14 12H3" />
                </svg>
                {__('Report this product')}
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="mt-4 space-y-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
            <p className="text-xs font-semibold text-neutral-700">{__('Report this product')}</p>
            <select
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                className="w-full rounded-md border-neutral-300 text-xs"
            >
                <option value="counterfeit">{__('Counterfeit / fake')}</option>
                <option value="prohibited">{__('Prohibited item')}</option>
                <option value="misleading">{__('Misleading listing')}</option>
                <option value="offensive">{__('Offensive content')}</option>
                <option value="other">{__('Other')}</option>
            </select>
            <textarea
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                rows={2}
                placeholder={__('Briefly describe the issue')}
                className="w-full rounded-md border-neutral-300 text-xs"
            />
            {errors.description && <p className="text-xs text-red-600">{errors.description}</p>}
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                >
                    {__('Submit Report')}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="px-3 py-1.5 text-xs text-neutral-500">
                    {__('Cancel')}
                </button>
            </div>
        </form>
    );
}

// The description is vendor-authored HTML (from the vendor dashboard's rich text editor),
// already stripped server-side to bold/italic/underline/lists — DOMPurify here is defense in
// depth for the same allowlist, not the primary sanitization pass.
const DESCRIPTION_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'];

function CouponStrip({ coupons }) {
    const { __ } = useTranslation();

    if (!coupons || coupons.length === 0) return null;

    return (
        <div className="mt-4 flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold text-neutral-500">{__('Coupons for this store:')}</span>
            {coupons.map((coupon) => (
                <div
                    key={coupon.code}
                    className="flex items-center gap-2 rounded-md border border-dashed border-orange-300 bg-orange-50 px-3 py-1 text-xs"
                >
                    <span className="font-bold text-orange-700">{coupon.code}</span>
                    <span className="text-orange-600">
                        {coupon.type === 'percentage' ? `${coupon.value}% off` : `${formatNaira(coupon.value)} off`}
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function Show({ product, relatedProducts, otherSellers = [], coupons = [], cartLines = [], seo }) {
    const { __ } = useTranslation();
    const { errors: pageErrors = {} } = usePage().props;
    const [updatingCart, setUpdatingCart] = useState(false);
    const images = product.images ?? [];
    const [activeImage, setActiveImage] = useState(0);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    // Desktop hover-magnifier: transform-origin follows the cursor over the main image.
    const [zoomOrigin, setZoomOrigin] = useState(null);
    const options = product.options ?? [];
    const variants = product.variants ?? [];
    const [selection, setSelection] = useState(() => defaultSelection(options));

    const { data, setData, post, processing, errors } = useForm({
        product_id: product.id,
        product_variant_id: null,
        quantity: 1,
    });

    const variant = options.length > 0 ? resolveVariant(variants, options, selection) : null;

    // A product WITH options only ever uses its variant's tiers (once one is resolved) — a
    // product with no options at all uses its own. See TieredPricingService (PHP) for the
    // server-side mirror of this same resolution, which is what CartController/CheckoutService
    // actually enforce.
    const activeTiers = options.length > 0 ? (variant?.price_tiers ?? []) : (product.price_tiers ?? []);
    const moq = minOrderQuantity(activeTiers);

    useEffect(() => {
        setData('product_variant_id', variant ? variant.id : null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [variant?.id]);

    // Reset quantity to the (possibly new) MOQ whenever it or the variant changes — e.g. switching
    // to a variant with a higher minimum order quantity, or less stock, than the one just selected.
    useEffect(() => {
        setData('quantity', moq);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [moq, variant?.id]);

    const submit = (e) => {
        e.preventDefault();
        post(route('cart.store'));
    };

    // final_price / effective_price already include a live admin homepage deal (DealPricing).
    const basePrice = variant ? variant.final_price : priceOf(product);
    const wasPrice = variant?.price != null ? product.compare_price : wasPriceOf(product);
    const tieredPrice = resolveTierPrice(activeTiers, data.quantity, basePrice);
    const displayPrice = tieredPrice ?? activeTiers[0]?.price_kobo ?? basePrice;
    const availableStock = options.length > 0 ? (variant ? variant.stock : 0) : product.stock;
    const meetsMoq = data.quantity >= moq;
    const canAddToCart = (options.length > 0 ? Boolean(variant) && availableStock > 0 : availableStock > 0) && meetsMoq;

    // Once the selected product/variant is already in the cart, the stepper edits that cart line
    // directly (no separate "Add to Cart" step). Stepping below the MOQ removes the line.
    const cartLine = cartLines.find((line) => (line.product_variant_id ?? null) === (variant?.id ?? null)) ?? null;
    const quantity = cartLine ? cartLine.quantity : data.quantity;
    const canIncrease = !updatingCart && quantity < availableStock && (options.length === 0 || Boolean(variant));
    const quantityError = errors.quantity || errors.product_id || pageErrors.quantity;

    const changeQuantity = (next) => {
        if (!cartLine) {
            setData('quantity', Math.min(Math.max(moq, next), Math.max(moq, availableStock)));
            return;
        }

        const visitOptions = {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setUpdatingCart(true),
            onFinish: () => setUpdatingCart(false),
        };

        if (next < moq) {
            router.delete(route('cart.destroy', cartLine.id), visitOptions);
        } else {
            router.patch(route('cart.update', cartLine.id), { quantity: Math.min(next, availableStock) }, visitOptions);
        }
    };

    const selectValue = (optionId, valueId) => {
        setSelection((prev) => ({ ...prev, [optionId]: valueId }));
    };

    const imageUrls = images.map((img) => img.enhanced_url ?? img.original_url);
    const activeImageUrl = imageUrls[activeImage] ?? null;
    const stepImage = (dir) => setActiveImage((i) => (i + dir + images.length) % images.length);

    const description = t(product.description);
    const descriptionHtml = useMemo(
        () => (description ? DOMPurify.sanitize(description, { ALLOWED_TAGS: DESCRIPTION_TAGS, ALLOWED_ATTR: [] }) : ''),
        [description]
    );
    const descriptionText = useMemo(
        () => (description ? DOMPurify.sanitize(description, { ALLOWED_TAGS: [] }) : ''),
        [description]
    );
    const reviews = product.reviews ?? [];
    const avgRating = reviews.length
        ? reviews.reduce((sum, r) => sum + r.rating, 0) / reviews.length
        : 0;

    return (
        <StorefrontLayout>
            <SeoHead seo={seo} />

            <div className="mx-auto max-w-[1520px] px-4 py-6 sm:px-6 lg:px-8">
                {/* One white card: gallery + product details + reviews */}
                <div className="rounded-3xl bg-white p-4 sm:p-6 lg:p-8">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    {/* Gallery */}
                    <div className="lg:sticky lg:top-28 lg:self-start">
                        <div className="group/gallery relative aspect-square w-full overflow-hidden rounded-2xl bg-neutral-50">
                            {activeImageUrl ? (
                                <button
                                    type="button"
                                    onClick={() => setLightboxOpen(true)}
                                    onMouseMove={(e) => {
                                        const r = e.currentTarget.getBoundingClientRect();
                                        setZoomOrigin(`${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`);
                                    }}
                                    onMouseLeave={() => setZoomOrigin(null)}
                                    className="block h-full w-full cursor-zoom-in overflow-hidden"
                                    aria-label={__('View image full screen')}
                                >
                                    <img
                                        src={activeImageUrl}
                                        alt={t(product.title)}
                                        className="h-full w-full object-cover transition-transform duration-200 ease-out"
                                        style={zoomOrigin ? { transform: 'scale(1.8)', transformOrigin: zoomOrigin } : undefined}
                                        draggable={false}
                                    />
                                </button>
                            ) : (
                                <div className="flex h-full items-center justify-center text-neutral-300">{__('No image')}</div>
                            )}

                            {activeImageUrl && (
                                <button
                                    type="button"
                                    onClick={() => setLightboxOpen(true)}
                                    className="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-tb-ink shadow-soft transition hover:bg-white hover:text-tb"
                                    aria-label={__('View image full screen')}
                                    title={__('View image full screen')}
                                >
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                    </svg>
                                </button>
                            )}

                            {images.length > 1 && (
                                <>
                                    {[-1, 1].map((dir) => (
                                        <button
                                            key={dir}
                                            type="button"
                                            onClick={() => stepImage(dir)}
                                            aria-label={dir < 0 ? __('Previous image') : __('Next image')}
                                            className={`absolute top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-tb-ink shadow-soft transition hover:bg-white hover:text-tb sm:opacity-0 sm:group-hover/gallery:opacity-100 ${
                                                dir < 0 ? 'left-3' : 'right-3'
                                            }`}
                                        >
                                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.4} d={dir < 0 ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7'} />
                                            </svg>
                                        </button>
                                    ))}
                                    <span className="pointer-events-none absolute bottom-3 left-3 rounded-full bg-black/55 px-2.5 py-1 font-display text-[11px] font-semibold tabular-nums text-white">
                                        {activeImage + 1} / {images.length}
                                    </span>
                                </>
                            )}

                            {activeImageUrl && (
                                <span className="pointer-events-none absolute bottom-3 right-3 hidden items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-tb-ink shadow-sm transition-opacity group-hover/gallery:opacity-0 sm:inline-flex">
                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="11" cy="11" r="7" strokeWidth={2} /><path strokeLinecap="round" strokeWidth={2} d="M8 11h6M11 8v6M20 20l-3.5-3.5" /></svg>
                                    {__('Hover to zoom · click to expand')}
                                </span>
                            )}
                        </div>

                        {images.length > 1 && (
                            <div className="no-scrollbar mt-3 flex gap-2.5 overflow-x-auto p-1">
                                {images.map((img, i) => (
                                    <button
                                        key={img.id}
                                        type="button"
                                        onClick={() => setActiveImage(i)}
                                        onDoubleClick={() => {
                                            setActiveImage(i);
                                            setLightboxOpen(true);
                                        }}
                                        aria-label={`${__('Image')} ${i + 1}`}
                                        className={`h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-neutral-50 transition sm:h-20 sm:w-20 ${
                                            i === activeImage ? 'ring-2 ring-tb ring-offset-2' : 'opacity-70 hover:opacity-100'
                                        }`}
                                    >
                                        <img src={imageUrls[i]} alt="" className="h-full w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}

                        <ImageLightbox
                            images={imageUrls}
                            index={activeImage}
                            open={lightboxOpen}
                            alt={t(product.title)}
                            onClose={() => setLightboxOpen(false)}
                            onIndexChange={setActiveImage}
                        />
                    </div>

                    {/* Details */}
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold text-neutral-900 sm:text-2xl">{t(product.title)}</h1>

                        <div className="mt-2 flex items-center gap-3">
                            <StarRating rating={avgRating} count={reviews.length} size="lg" />
                        </div>

                        <div className="mt-4 rounded-lg bg-orange-50 p-4">
                            <PriceTag kobo={displayPrice} comparePriceKobo={wasPrice} size="xl" />
                        </div>

                        {product.vendor?.store && (
                            <Link
                                href={route('vendors.show', product.vendor.store.slug)}
                                className="mt-4 flex items-center gap-3 rounded-2xl bg-neutral-50 p-3 transition hover:bg-tb-soft"
                            >
                                <div className="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-neutral-100">
                                    {product.vendor.store.logo_url && (
                                        <img src={product.vendor.store.logo_url} alt="" className="h-full w-full object-cover" />
                                    )}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-neutral-800">{t(product.vendor.store.name)}</p>
                                    <p className="text-xs text-neutral-400">{__('Visit store →')}</p>
                                </div>
                            </Link>
                        )}

                        {formatDeliveryEstimate(product.vendor?.delivery_days_min, product.vendor?.delivery_days_max, __) && (
                            <p className="mt-3 flex items-center gap-1.5 text-xs text-neutral-500">
                                <svg className="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {formatDeliveryEstimate(product.vendor.delivery_days_min, product.vendor.delivery_days_max, __)}
                            </p>
                        )}

                        <form onSubmit={submit} className="mt-6">
                            {options.length > 0 && (
                                <div className="mb-5">
                                    <VariantOptions options={options} selection={selection} onSelect={selectValue} />
                                </div>
                            )}

                            {options.length > 0 && !variant && (
                                <p className="mb-4 text-sm text-neutral-500">Select an option combination to see price and availability.</p>
                            )}

                            <div className="mb-2 flex items-center gap-3">
                                <label className="text-sm font-medium text-neutral-700">{__('Quantity')}</label>
                                <div className="flex items-center gap-1">
                                    <button
                                        type="button"
                                        onClick={() => changeQuantity(quantity - 1)}
                                        disabled={updatingCart || (!cartLine && quantity <= moq)}
                                        aria-label={cartLine && quantity <= moq ? __('Remove from cart') : __('Decrease quantity')}
                                        className="flex h-10 w-10 items-center justify-center rounded-md bg-orange-600 text-2xl font-bold leading-none text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        −
                                    </button>
                                    <span className="w-12 text-center text-base font-semibold text-neutral-800" aria-live="polite">
                                        {quantity}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => changeQuantity(quantity + 1)}
                                        disabled={!canIncrease}
                                        aria-label={__('Increase quantity')}
                                        className="flex h-10 w-10 items-center justify-center rounded-md bg-orange-600 text-2xl font-bold leading-none text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        +
                                    </button>
                                </div>
                                {availableStock > 0 && availableStock <= 10 && (
                                    <span className="text-xs font-medium text-red-600">
                                        {__('Only :count left in stock.', { count: availableStock })}
                                    </span>
                                )}
                                {moq > 1 && (
                                    <span className="text-xs text-neutral-500">
                                        {__('Minimum order: :count', { count: moq })}
                                    </span>
                                )}
                            </div>

                            {!meetsMoq && (
                                <p className="mb-3 text-xs text-red-600">
                                    {__('This item has a minimum order quantity of :count.', { count: moq })}
                                </p>
                            )}

                            {activeTiers.length > 1 && (
                                <div className="mb-4 overflow-hidden rounded-md border border-neutral-200">
                                    <table className="w-full text-xs">
                                        <thead className="bg-neutral-50 text-left uppercase text-neutral-500">
                                            <tr>
                                                <th className="px-3 py-1.5">{__('Quantity')}</th>
                                                <th className="px-3 py-1.5">{__('Price per unit')}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-100">
                                            {activeTiers.map((tier) => (
                                                <tr
                                                    key={tier.id}
                                                    className={data.quantity >= tier.min_quantity && (tier.max_quantity == null || data.quantity <= tier.max_quantity) ? 'bg-orange-50' : ''}
                                                >
                                                    <td className="px-3 py-1.5 text-neutral-700">
                                                        {tier.max_quantity ? `${tier.min_quantity}–${tier.max_quantity}` : `${tier.min_quantity}+`}
                                                    </td>
                                                    <td className="px-3 py-1.5 font-semibold text-neutral-800">{formatNaira(tier.price_kobo)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <InputError message={quantityError} className="mb-2" />

                            {cartLine ? (
                                <div className="flex flex-wrap items-center gap-3 text-sm">
                                    <span className="inline-flex items-center gap-1.5 font-semibold text-green-700">
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                                        </svg>
                                        {updatingCart ? __('Updating cart…') : __('In your cart')}
                                    </span>
                                    <Link href={route('cart.index')} className="font-semibold text-orange-600 hover:underline">
                                        {__('View cart')} →
                                    </Link>
                                </div>
                            ) : (
                                <button
                                    type="submit"
                                    disabled={processing || !canAddToCart}
                                    className="w-full rounded-md bg-orange-600 py-3 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50 sm:w-auto sm:px-10"
                                >
                                    {availableStock > 0 ? __('Add to Cart') : __('Out of Stock')}
                                </button>
                            )}
                        </form>

                        <CouponStrip coupons={coupons} />

                        <ReportProductButton product={product} />

                        {descriptionHtml && (
                            <div className="mt-8">
                                <h2 className="mb-2 text-sm font-semibold text-neutral-800">{__('Description')}</h2>
                                <div
                                    className="text-sm leading-relaxed text-neutral-600 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:list-disc [&_ul]:pl-5"
                                    dangerouslySetInnerHTML={{ __html: descriptionHtml }}
                                />
                            </div>
                        )}
                    </div>
                </div>

                {/* Reviews */}
                <section className="mt-10 border-t border-neutral-100 pt-8">
                    <h2 className="mb-4 text-lg font-bold text-neutral-900">{__('Reviews')} ({reviews.length})</h2>
                    {reviews.length === 0 ? (
                        <p className="text-sm text-neutral-400">{__('No reviews yet.')}</p>
                    ) : (
                        <div className="space-y-4">
                            {reviews.map((review) => (
                                <div key={review.id} className="rounded-lg border border-neutral-200 p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold text-neutral-800">{review.user?.name ?? 'Anonymous'}</span>
                                            <span className="flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-700">
                                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {__('Verified Purchase')}
                                            </span>
                                        </div>
                                        <StarRating rating={review.rating} />
                                    </div>
                                    {review.comment && <p className="mt-2 text-sm text-neutral-600">{review.comment}</p>}
                                </div>
                            ))}
                        </div>
                    )}
                </section>
                </div>

                {/* Other sellers of the same product */}
                {otherSellers.length > 0 && (
                    <section className="mt-12">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-neutral-900">
                                {__('Other Sellers')} ({otherSellers.length})
                            </h2>
                            <Link
                                href={`${route('compare.index')}?q=${encodeURIComponent(t(product.title))}`}
                                className="text-sm font-semibold text-orange-600 hover:underline"
                            >
                                {__('Compare all prices')}
                            </Link>
                        </div>
                        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                                    <tr>
                                        <th className="px-4 py-2">{__('Seller')}</th>
                                        <th className="px-4 py-2">{__('Rating')}</th>
                                        <th className="px-4 py-2">{__('Price')}</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100">
                                    {otherSellers.map((listing) => (
                                        <tr key={listing.product_id}>
                                            <td className="px-4 py-3">
                                                <span className="font-medium text-neutral-800">{listing.vendor.business_name}</span>
                                                {listing.vendor.location && (
                                                    <p className="text-xs text-neutral-400">{listing.vendor.location}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {listing.vendor.rating ? (
                                                    <StarRating rating={listing.vendor.rating} count={listing.vendor.reviews_count} />
                                                ) : (
                                                    <span className="text-xs text-neutral-400">{__('No reviews yet')}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-neutral-800">{formatNaira(listing.price)}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Link href={route('products.show', listing.product_slug)} className="font-semibold text-orange-600 hover:underline">
                                                    {__('View')}
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {/* Related products */}
                {relatedProducts.length > 0 && (
                    <section className="mt-12">
                        <h2 className="mb-4 text-lg font-bold text-neutral-900">{__('Related Products')}</h2>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            {relatedProducts.map((p) => (
                                <ProductCard key={p.id} product={p} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </StorefrontLayout>
    );
}
