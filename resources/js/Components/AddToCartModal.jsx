import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import VariantOptions from '@/Components/VariantOptions';
import { showToast } from '@/Components/ToastHost';
import { useTranslation } from '@/hooks/useTranslation';
import { formatNaira } from '@/utils/money';
import { priceOf, wasPriceOf } from '@/utils/productPrice';
import { minOrderQuantity, resolveTierPrice } from '@/utils/tieredPricing';
import { t } from '@/utils/translatable';
import { defaultSelection, resolveVariant } from '@/utils/variants';

/**
 * Quick-add for products with variants, opened from a ProductCard's "Add to Cart". `product` is
 * the payload from ProductController::quickView (options, variants, tiers). Pricing/MOQ/stock
 * follow exactly the same rules as Pages/Products/Show.jsx; CartController re-checks all of it.
 */
export default function AddToCartModal({ show, product, onClose }) {
    const { __ } = useTranslation();
    const options = product?.options ?? [];
    const variants = product?.variants ?? [];

    const [selection, setSelection] = useState(() => defaultSelection(options));
    const [quantity, setQuantity] = useState(1);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    // Fresh picks each time a (possibly different) product is opened.
    useEffect(() => {
        if (show && product) {
            setSelection(defaultSelection(product.options));
            setError(null);
        }
    }, [show, product?.id]);

    const variant = resolveVariant(variants, options, selection);
    const tiers = variant?.price_tiers ?? [];
    const moq = minOrderQuantity(tiers);
    const stock = variant ? variant.stock : 0;

    useEffect(() => {
        setQuantity(moq);
        setError(null);
    }, [moq, variant?.id]);

    const unitPrice = resolveTierPrice(tiers, quantity, variant?.final_price ?? priceOf(product)) ?? tiers[0]?.price_kobo ?? priceOf(product);
    // A variant with its own explicit price isn't discounted, so only show a "was" price otherwise.
    const wasPrice = variant?.price != null ? (product?.compare_price > unitPrice ? product.compare_price : null) : wasPriceOf(product);
    const canSubmit = Boolean(variant) && stock > 0 && quantity >= moq && quantity <= stock && !submitting;

    // Show the selected variant's own image when it has one, else the product's primary image.
    const imageUrl = useMemo(() => {
        if (variant?.image_url) return variant.image_url;
        const img = product?.images?.find((i) => i.is_primary) ?? product?.images?.[0];
        return img?.enhanced_url ?? img?.original_url ?? null;
    }, [variant?.image_url, product?.images]);

    const step = (delta) => setQuantity((q) => Math.min(Math.max(moq, q + delta), Math.max(moq, stock)));

    const submit = () => {
        if (!canSubmit) return;
        setSubmitting(true);
        setError(null);

        router.post(
            route('cart.store'),
            { product_id: product.id, product_variant_id: variant.id, quantity },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['flash', 'cartCount', 'errors'],
                onSuccess: () => onClose(),
                onError: (errors) => {
                    const message = errors.quantity ?? errors.product_variant_id ?? Object.values(errors)[0] ?? __('Could not add this item to your cart.');
                    setError(message);
                    showToast('error', message);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Transition show={show} appear>
            <Dialog as="div" className="relative z-[90]" onClose={submitting ? () => {} : onClose}>
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-tb-ink/40 backdrop-blur-sm" aria-hidden="true" />
                </TransitionChild>

                <div className="fixed inset-0 overflow-y-auto">
                    <div className="flex min-h-full items-end justify-center sm:items-center sm:p-6">
                        <TransitionChild
                            enter="ease-out duration-300"
                            enterFrom="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                            enterTo="opacity-100 translate-y-0 sm:scale-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                            leaveTo="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                        >
                            <DialogPanel className="relative w-full overflow-hidden rounded-t-3xl bg-white shadow-float sm:max-w-2xl sm:rounded-3xl">
                                {/* Mobile grab handle */}
                                <div className="mx-auto mt-2.5 h-1.5 w-10 rounded-full bg-neutral-200 sm:hidden" />

                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-tb-ink shadow-soft transition hover:bg-neutral-100"
                                    aria-label={__('Close')}
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.4} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>

                                {product && (
                                    <div className="grid sm:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                                        {/* Image */}
                                        <div className="relative hidden bg-gradient-to-br from-neutral-50 to-neutral-100 sm:block">
                                            {imageUrl && <img src={imageUrl} alt={t(product.title)} className="h-full max-h-[460px] w-full object-cover" />}
                                        </div>

                                        {/* Details */}
                                        <div className="flex flex-col p-5 sm:p-6">
                                            <div className="flex gap-3 sm:block">
                                                {imageUrl && (
                                                    <img src={imageUrl} alt="" className="h-20 w-20 shrink-0 rounded-2xl object-cover sm:hidden" />
                                                )}
                                                <div className="min-w-0 pr-8">
                                                    <DialogTitle className="line-clamp-2 font-display text-[17px] font-bold leading-snug text-tb-ink sm:text-[19px]">
                                                        {t(product.title)}
                                                    </DialogTitle>
                                                    <div className="mt-1.5 flex items-baseline gap-2">
                                                        <span className="font-display text-[24px] font-extrabold tracking-tight text-tb">
                                                            {formatNaira(unitPrice)}
                                                        </span>
                                                        {wasPrice > unitPrice && (
                                                            <span className="text-[13px] text-neutral-400 line-through">{formatNaira(wasPrice)}</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="mt-5 border-t border-neutral-100 pt-5">
                                                <VariantOptions
                                                    options={options}
                                                    selection={selection}
                                                    onSelect={(optionId, valueId) => setSelection((prev) => ({ ...prev, [optionId]: valueId }))}
                                                />

                                                {!variant && (
                                                    <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-[12px] text-amber-800">
                                                        {__('This combination is not available. Please choose another.')}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Quantity */}
                                            <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <p className="text-[13px] font-semibold text-tb-ink">{__('Quantity')}</p>
                                                    <p className="mt-0.5 text-[11.5px] text-neutral-500">
                                                        {variant && stock <= 0
                                                            ? __('Out of stock')
                                                            : variant && stock <= 10
                                                                ? __('Only :count left in stock.', { count: stock })
                                                                : moq > 1
                                                                    ? __('Minimum order: :count', { count: moq })
                                                                    : __('In stock')}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-1 rounded-full bg-neutral-100 p-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => step(-1)}
                                                        disabled={!variant || quantity <= moq}
                                                        aria-label={__('Decrease quantity')}
                                                        className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-lg font-bold text-tb-ink shadow-sm transition hover:text-tb disabled:cursor-not-allowed disabled:opacity-40"
                                                    >
                                                        −
                                                    </button>
                                                    <span className="w-10 text-center font-display text-[15px] font-bold tabular-nums text-tb-ink" aria-live="polite">
                                                        {quantity}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => step(1)}
                                                        disabled={!variant || quantity >= stock}
                                                        aria-label={__('Increase quantity')}
                                                        className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-lg font-bold text-tb-ink shadow-sm transition hover:text-tb disabled:cursor-not-allowed disabled:opacity-40"
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                            </div>

                                            {tiers.length > 1 && (
                                                <div className="mt-3 flex flex-wrap gap-1.5">
                                                    {tiers.map((tier) => {
                                                        const active = quantity >= tier.min_quantity && (tier.max_quantity == null || quantity <= tier.max_quantity);
                                                        return (
                                                            <span
                                                                key={tier.id}
                                                                className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${active ? 'bg-tb text-white' : 'bg-neutral-100 text-neutral-600'}`}
                                                            >
                                                                {tier.max_quantity ? `${tier.min_quantity}–${tier.max_quantity}` : `${tier.min_quantity}+`}: {formatNaira(tier.price_kobo)}
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            )}

                                            {error && <p className="mt-3 text-[12px] font-medium text-tb-red">{error}</p>}

                                            <div className="mt-6 flex items-center justify-between gap-3 border-t border-neutral-100 pt-4">
                                                <div>
                                                    <p className="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{__('Total')}</p>
                                                    <p className="font-display text-[18px] font-extrabold tracking-tight text-tb-ink">
                                                        {formatNaira((unitPrice ?? 0) * quantity)}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={submit}
                                                    disabled={!canSubmit}
                                                    className="cin-gradient inline-flex h-12 items-center gap-2 rounded-full px-7 font-display text-[14px] font-bold text-white shadow-glow transition hover:brightness-110 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                                >
                                                    {submitting && (
                                                        <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden>
                                                            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.3" strokeWidth="3" />
                                                            <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                                                        </svg>
                                                    )}
                                                    {variant && stock <= 0 ? __('Out of Stock') : submitting ? __('Adding…') : __('Continue')}
                                                    {!submitting && !(variant && stock <= 0) && <span aria-hidden>→</span>}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}
