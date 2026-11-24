import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import EmptyState from '@/Components/EmptyState';
import PriceTag from '@/Components/PriceTag';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ itemsByVendor }) {
    const { __ } = useTranslation();
    const [quantityErrors, setQuantityErrors] = useState({});
    const grandTotal = itemsByVendor.reduce((sum, group) => sum + group.subtotal, 0);
    const isEmpty = itemsByVendor.length === 0;

    const stockFor = (item) => Number(item.variant ? item.variant.stock : item.product?.stock) || 0;

    const updateQuantity = (item, quantity) => {
        const moq = item.min_order_quantity ?? 1;
        if (quantity < moq) return;

        router.patch(route('cart.update', item.id), { quantity }, {
            preserveScroll: true,
            onError: (errors) => setQuantityErrors((prev) => ({ ...prev, [item.id]: errors.quantity })),
            onSuccess: () => setQuantityErrors((prev) => ({ ...prev, [item.id]: undefined })),
        });
    };

    const removeItem = (item) => {
        router.delete(route('cart.destroy', item.id), { preserveScroll: true });
    };

    return (
        <StorefrontLayout>
            <Head title="Your Cart" />

            <div className="mx-auto max-w-marketplace px-4 py-6 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-2xl font-bold text-neutral-900">Your Cart</h1>

                {isEmpty ? (
                    <EmptyState
                        title={__('Your cart is empty')}
                        description="Browse products and add something you like."
                        actionHref={route('products.index')}
                        actionLabel="Start Shopping"
                    />
                ) : (
                    <div className="flex flex-col gap-8 lg:flex-row">
                        <div className="flex-1 space-y-6">
                            {itemsByVendor.map((group) => (
                                <div key={group.vendor.id} className="overflow-hidden rounded-2xl bg-white">
                                    <div className="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                                        <span className="text-base font-semibold text-neutral-800">
                                            {group.vendor.store ? t(group.vendor.store.name) : group.vendor.business_name}
                                        </span>
                                        <span className="flex items-baseline gap-2 text-base text-neutral-500">
                                            {__('Subtotal')}:
                                            {group.original_subtotal > group.subtotal && (
                                                <span className="text-sm text-neutral-400 line-through">
                                                    {formatNaira(group.original_subtotal)}
                                                </span>
                                            )}
                                            <span className="font-semibold text-neutral-700">{formatNaira(group.subtotal)}</span>
                                        </span>
                                    </div>
                                    <div className="divide-y divide-neutral-100">
                                        {group.items.map((item) => (
                                            <div key={item.id} className="flex items-center gap-4 px-5 py-5">
                                                <div className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-neutral-100">
                                                    {item.product?.images?.[0] && (
                                                        <img
                                                            src={item.product.images[0].enhanced_url ?? item.product.images[0].original_url}
                                                            alt=""
                                                            className="h-full w-full object-cover"
                                                        />
                                                    )}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <Link
                                                        href={item.product ? route('products.show', item.product.slug) : '#'}
                                                        className="line-clamp-2 text-base font-medium text-neutral-800 hover:text-orange-600"
                                                    >
                                                        {item.product ? t(item.product.title) : 'Product'}
                                                    </Link>
                                                    {item.variant?.option_values?.length > 0 && (
                                                        <p className="mt-0.5 text-sm text-neutral-500">
                                                            {item.variant.option_values.map((ov) => t(ov.value)).join(' / ')}
                                                        </p>
                                                    )}
                                                    <PriceTag
                                                        kobo={item.unit_price}
                                                        comparePriceKobo={item.original_unit_price}
                                                        className="mt-1"
                                                        size="base"
                                                    />
                                                    {item.min_order_quantity > 1 && (
                                                        <p className="mt-0.5 text-sm text-neutral-500">
                                                            {__('Minimum order: :count', { count: item.min_order_quantity })}
                                                        </p>
                                                    )}
                                                    {item.quantity > stockFor(item) && (
                                                        <p className="mt-0.5 text-sm font-medium text-red-600">
                                                            {stockFor(item) > 0
                                                                ? __('Only :count left in stock.', { count: stockFor(item) })
                                                                : __('This item is out of stock.')}
                                                        </p>
                                                    )}
                                                    {quantityErrors[item.id] && (
                                                        <p className="mt-0.5 text-sm text-red-600">{quantityErrors[item.id]}</p>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        onClick={() => updateQuantity(item, item.quantity - 1)}
                                                        disabled={item.quantity <= (item.min_order_quantity ?? 1)}
                                                        aria-label={__('Decrease quantity')}
                                                        className="flex h-9 w-9 items-center justify-center rounded-md bg-orange-600 text-xl font-bold leading-none text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-40"
                                                    >
                                                        −
                                                    </button>
                                                    <span className="w-10 text-center text-lg font-semibold">{item.quantity}</span>
                                                    <button
                                                        onClick={() => updateQuantity(item, item.quantity + 1)}
                                                        disabled={item.quantity >= stockFor(item)}
                                                        aria-label={__('Increase quantity')}
                                                        className="flex h-9 w-9 items-center justify-center rounded-md bg-orange-600 text-xl font-bold leading-none text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-40"
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                                <button
                                                    onClick={() => removeItem(item)}
                                                    className="p-2 text-neutral-400 hover:text-red-600"
                                                    aria-label="Remove"
                                                >
                                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="w-full lg:w-80">
                            <div className="rounded-2xl bg-white p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <span className="text-base font-medium text-neutral-700">Grand Total</span>
                                    <PriceTag kobo={grandTotal} size="xl" />
                                </div>
                                <Link
                                    href={route('checkout.index')}
                                    className="block w-full rounded-full bg-orange-600 py-3.5 text-center text-base font-semibold text-white hover:bg-orange-700"
                                >
                                    Proceed to Checkout
                                </Link>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
