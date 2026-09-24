import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DOMPurify from 'dompurify';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import PriceTag from '@/Components/PriceTag';
import StarRating from '@/Components/StarRating';
import InputError from '@/Components/InputError';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';
import { isColorOption, resolveColorSwatch } from '@/utils/colorSwatch';
import { formatNaira } from '@/utils/money';
import { minOrderQuantity, resolveTierPrice } from '@/utils/tieredPricing';
import { formatDeliveryEstimate } from '@/utils/deliveryEstimate';

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

/**
 * Given the buyer's current { [optionId]: selectedValueId } selection and the product's full
 * variant list, finds the variant whose optionValues set exactly matches the selection.
 */
function resolveVariant(variants, options, selection) {
    if (!options || options.length === 0) return null;

    const selectedIds = options.map((opt) => selection[opt.id]).filter((id) => id != null);
    if (selectedIds.length !== options.length) return null;

    return (
        variants.find((variant) => {
            // Eloquent serializes relation keys as snake_case regardless of the PHP method's
            // camelCase name, so the loaded `optionValues` relation arrives as `option_values`.
            const variantIds = variant.option_values.map((ov) => ov.id);
            if (variantIds.length !== selectedIds.length) return false;
            return selectedIds.every((id) => variantIds.includes(id));
        }) ?? null
    );
}

function defaultSelection(options) {
    const selection = {};
    (options ?? []).forEach((option) => {
        if (option.values?.length > 0) {
            selection[option.id] = option.values[0].id;
        }
    });
    return selection;
}

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

export default function Show({ product, relatedProducts, otherSellers = [], coupons = [], seo }) {
    const { __ } = useTranslation();
    const images = product.images ?? [];
    const [activeImage, setActiveImage] = useState(0);
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

    // Reset quantity to the (possibly new) MOQ whenever it changes — e.g. switching to a variant
    // with a higher minimum order quantity than the one just selected.
    useEffect(() => {
        setData('quantity', moq);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [moq]);

    const submit = (e) => {
        e.preventDefault();
        post(route('cart.store'));
    };

    const basePrice = variant ? variant.final_price : product.price;
    const tieredPrice = resolveTierPrice(activeTiers, data.quantity, basePrice);
    const displayPrice = tieredPrice ?? activeTiers[0]?.price_kobo ?? basePrice;
    const availableStock = options.length > 0 ? (variant ? variant.stock : 0) : product.stock;
    const meetsMoq = data.quantity >= moq;
    const canAddToCart = (options.length > 0 ? Boolean(variant) && availableStock > 0 : availableStock > 0) && meetsMoq;

    const selectValue = (optionId, valueId) => {
        setSelection((prev) => ({ ...prev, [optionId]: valueId }));
    };

    const activeImageUrl = images[activeImage]
        ? (images[activeImage].enhanced_url ?? images[activeImage].original_url)
        : null;

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

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    {/* Gallery */}
                    <div>
                        <div className="aspect-square w-full overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100">
                            {activeImageUrl ? (
                                <img src={activeImageUrl} alt={t(product.title)} className="h-full w-full object-cover" />
                            ) : (
                                <div className="flex h-full items-center justify-center text-neutral-300">No image</div>
                            )}
                        </div>
                        {images.length > 1 && (
                            <div className="mt-3 flex gap-2 overflow-x-auto">
                                {images.map((img, i) => (
                                    <button
                                        key={img.id}
                                        onClick={() => setActiveImage(i)}
                                        className={`h-16 w-16 shrink-0 overflow-hidden rounded-md border-2 ${
                                            i === activeImage ? 'border-orange-600' : 'border-neutral-200'
                                        }`}
                                    >
                                        <img src={img.enhanced_url ?? img.original_url} alt="" className="h-full w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Details */}
                    <div>
                        <h1 className="text-xl font-bold text-neutral-900 sm:text-2xl">{t(product.title)}</h1>

                        <div className="mt-2 flex items-center gap-3">
                            <StarRating rating={avgRating} count={reviews.length} size="lg" />
                        </div>

                        <div className="mt-4 rounded-lg bg-orange-50 p-4">
                            <PriceTag kobo={displayPrice} comparePriceKobo={product.compare_price} size="xl" />
                        </div>

                        {product.vendor?.store && (
                            <Link
                                href={route('vendors.show', product.vendor.store.slug)}
                                className="mt-4 flex items-center gap-3 rounded-lg border border-neutral-200 p-3 hover:border-orange-300"
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
                            {options.map((option) => {
                                const asSwatches = isColorOption(option.name);

                                return (
                                    <div key={option.id} className="mb-4">
                                        <label className="mb-2 block text-sm font-medium text-neutral-700">
                                            {t(option.name)}
                                        </label>
                                        <div className="flex flex-wrap items-center gap-2.5">
                                            {option.values.map((value) => {
                                                const selected = selection[option.id] === value.id;
                                                const swatch = asSwatches ? resolveColorSwatch(value.value) : null;
                                                const label = t(value.value);

                                                if (swatch) {
                                                    const isLight = ['#ffffff', '#e8d5b7', '#eab308', '#c0c0c0'].includes(
                                                        swatch.toLowerCase(),
                                                    );

                                                    return (
                                                        <button
                                                            type="button"
                                                            key={value.id}
                                                            onClick={() => selectValue(option.id, value.id)}
                                                            title={label}
                                                            aria-label={label}
                                                            aria-pressed={selected}
                                                            className={`relative h-9 w-9 rounded-full transition ${
                                                                selected
                                                                    ? 'ring-2 ring-tb ring-offset-2'
                                                                    : 'ring-1 ring-neutral-300 hover:ring-tb/60'
                                                            }`}
                                                            style={{ backgroundColor: swatch }}
                                                        >
                                                            {isLight && (
                                                                <span className="pointer-events-none absolute inset-0 rounded-full border border-neutral-200" />
                                                            )}
                                                        </button>
                                                    );
                                                }

                                                return (
                                                    <button
                                                        type="button"
                                                        key={value.id}
                                                        onClick={() => selectValue(option.id, value.id)}
                                                        className={`rounded-md border px-3 py-1.5 text-sm ${
                                                            selected
                                                                ? 'border-orange-600 bg-orange-600 text-white'
                                                                : 'border-neutral-300 text-neutral-700 hover:border-orange-400'
                                                        }`}
                                                    >
                                                        {label}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}

                            {options.length > 0 && !variant && (
                                <p className="mb-4 text-sm text-neutral-500">Select an option combination to see price and availability.</p>
                            )}

                            <div className="mb-2 flex items-center gap-3">
                                <label className="text-sm font-medium text-neutral-700">{__('Quantity')}</label>
                                <div className="flex items-center rounded-md border border-neutral-300">
                                    <button
                                        type="button"
                                        onClick={() => setData('quantity', Math.max(moq, data.quantity - 1))}
                                        className="px-3 py-1 text-neutral-600 hover:bg-neutral-100"
                                    >
                                        −
                                    </button>
                                    <span className="w-10 text-center text-sm">{data.quantity}</span>
                                    <button
                                        type="button"
                                        onClick={() => setData('quantity', data.quantity + 1)}
                                        className="px-3 py-1 text-neutral-600 hover:bg-neutral-100"
                                    >
                                        +
                                    </button>
                                </div>
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

                            <InputError message={errors.quantity || errors.product_id} className="mb-2" />

                            <button
                                type="submit"
                                disabled={processing || !canAddToCart}
                                className="w-full rounded-md bg-orange-600 py-3 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50 sm:w-auto sm:px-10"
                            >
                                {availableStock > 0 ? __('Add to Cart') : __('Out of Stock')}
                            </button>
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
                <section className="mt-12">
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
