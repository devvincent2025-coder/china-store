import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import EmptyState from '@/Components/EmptyState';
import PaymentBadges from '@/Components/PaymentBadges';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * One vendor group's coupon input. Applying/removing a coupon is its own request (not part of the
 * final order submission) so the discount is validated server-side and staged in the session
 * BEFORE the buyer pays — see CheckoutController's applyCoupon()/removeCoupon(). The numbers this
 * page shows are always exactly what will be charged, never a client-side guess.
 */
function CouponForm({ vendorId, appliedCode, errors }) {
    const [code, setCode] = useState('');
    const [applying, setApplying] = useState(false);

    const apply = (e) => {
        e.preventDefault();
        if (!code.trim()) return;

        setApplying(true);
        router.post(
            route('checkout.coupons.apply'),
            { vendor_id: vendorId, code: code.trim() },
            {
                preserveScroll: true,
                onFinish: () => setApplying(false),
                onSuccess: () => setCode(''),
            },
        );
    };

    const remove = () => {
        router.delete(route('checkout.coupons.remove', vendorId), { preserveScroll: true });
    };

    if (appliedCode) {
        return (
            <div className="flex items-center justify-between gap-2 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                <span>
                    Coupon <span className="font-semibold">{appliedCode}</span> applied
                </span>
                <button type="button" onClick={remove} className="text-xs font-medium text-green-700 underline hover:text-green-800">
                    Remove
                </button>
            </div>
        );
    }

    return (
        <form onSubmit={apply}>
            <div className="flex items-center gap-2">
                <input
                    type="text"
                    placeholder="Coupon code for this vendor"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                />
                <button
                    type="submit"
                    disabled={applying || !code.trim()}
                    className="shrink-0 rounded-md bg-neutral-800 px-4 py-2 text-xs font-semibold text-white hover:bg-neutral-900 disabled:opacity-50"
                >
                    Apply
                </button>
            </div>
            <InputError message={errors[`coupons.${vendorId}`]} className="mt-1" />
        </form>
    );
}

export default function Index({ itemsByVendor, errors, savedAddresses = [], escrowReturnWindowDays = 7 }) {
    const { __ } = useTranslation();
    const originalSubtotal = itemsByVendor.reduce((sum, group) => sum + group.original_subtotal, 0);
    const subtotal = itemsByVendor.reduce((sum, group) => sum + group.subtotal, 0);
    const couponDiscountTotal = itemsByVendor.reduce((sum, group) => sum + (group.discount ?? 0), 0);
    const vatTotal = itemsByVendor.reduce((sum, group) => sum + group.vat_amount, 0);
    const shippingTotal = itemsByVendor.reduce((sum, group) => sum + group.shipping_amount, 0);
    const grandTotal = itemsByVendor.reduce((sum, group) => sum + group.total, 0);
    const hasItemDiscount = originalSubtotal > subtotal;
    const priceAfterAllDiscounts = subtotal - couponDiscountTotal;

    // Pre-selects the buyer's default saved address (or their only one) so returning customers
    // never have to re-pick it — `address_id: null` is what tells the backend "this is a brand
    // new address" (see CheckoutController::store()'s `$request->filled('address_id')` branch).
    const defaultAddress = savedAddresses.find((a) => a.is_default) ?? savedAddresses[0] ?? null;

    const { data, setData, post, processing, errors: formErrors } = useForm({
        address_id: defaultAddress?.id ?? null,
        shipping_address: {
            name: '',
            phone: '',
            line1: '',
            city: '',
            state: '',
        },
        save_address: true,
    });

    const addingNewAddress = data.address_id === null;

    const setAddressField = (field, value) => {
        setData('shipping_address', { ...data.shipping_address, [field]: value });
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('checkout.store'));
    };

    if (itemsByVendor.length === 0) {
        return (
            <StorefrontLayout>
                <Head title="Checkout" />
                <div className="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
                    <EmptyState
                        title={__('Your cart is empty')}
                        description="Add some products before checking out."
                        actionHref={route('products.index')}
                        actionLabel="Start Shopping"
                    />
                </div>
            </StorefrontLayout>
        );
    }

    return (
        <StorefrontLayout>
            <Head title="Checkout" />

            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Checkout')}</h1>

                <form onSubmit={submit} className="flex flex-col gap-8 lg:flex-row">
                    <div className="flex-1 space-y-6">
                        {/* Shipping address */}
                        <div className="rounded-lg border border-neutral-200 bg-white p-5">
                            <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Shipping Address')}</h2>

                            {savedAddresses.length > 0 && (
                                <div className="mb-4 space-y-2">
                                    {savedAddresses.map((addr) => (
                                        <label
                                            key={addr.id}
                                            className={
                                                'flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm transition ' +
                                                (data.address_id === addr.id ? 'border-orange-500 bg-orange-50' : 'border-neutral-200 hover:border-neutral-300')
                                            }
                                        >
                                            <input
                                                type="radio"
                                                name="address_id"
                                                checked={data.address_id === addr.id}
                                                onChange={() => setData('address_id', addr.id)}
                                                className="mt-1 shrink-0 text-orange-600 focus:ring-orange-500"
                                            />
                                            <div className="min-w-0">
                                                <p className="flex items-center gap-2 font-medium text-neutral-800">
                                                    {addr.name}
                                                    {addr.is_default && (
                                                        <span className="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                                            {__('Default')}
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="text-neutral-500">{addr.line1}, {addr.city}, {addr.state}</p>
                                                <p className="text-neutral-400">{addr.phone}</p>
                                            </div>
                                        </label>
                                    ))}

                                    <label
                                        className={
                                            'flex cursor-pointer items-center gap-3 rounded-md border border-dashed p-3 text-sm transition ' +
                                            (addingNewAddress ? 'border-orange-500 bg-orange-50' : 'border-neutral-300 hover:border-neutral-400')
                                        }
                                    >
                                        <input
                                            type="radio"
                                            name="address_id"
                                            checked={addingNewAddress}
                                            onChange={() => setData('address_id', null)}
                                            className="shrink-0 text-orange-600 focus:ring-orange-500"
                                        />
                                        <span className="font-medium text-orange-600">+ {__('Add new address')}</span>
                                    </label>
                                </div>
                            )}

                            {addingNewAddress && (
                                <>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor="name" value={__('Full Name')} />
                                            <TextInput
                                                id="name"
                                                className="mt-1 block w-full"
                                                value={data.shipping_address.name}
                                                onChange={(e) => setAddressField('name', e.target.value)}
                                            />
                                            <InputError message={formErrors['shipping_address.name']} className="mt-1" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="phone" value="Phone" />
                                            <TextInput
                                                id="phone"
                                                className="mt-1 block w-full"
                                                value={data.shipping_address.phone}
                                                onChange={(e) => setAddressField('phone', e.target.value)}
                                            />
                                            <InputError message={formErrors['shipping_address.phone']} className="mt-1" />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <InputLabel htmlFor="line1" value="Address" />
                                            <TextInput
                                                id="line1"
                                                className="mt-1 block w-full"
                                                value={data.shipping_address.line1}
                                                onChange={(e) => setAddressField('line1', e.target.value)}
                                            />
                                            <InputError message={formErrors['shipping_address.line1']} className="mt-1" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="city" value="City" />
                                            <TextInput
                                                id="city"
                                                className="mt-1 block w-full"
                                                value={data.shipping_address.city}
                                                onChange={(e) => setAddressField('city', e.target.value)}
                                            />
                                            <InputError message={formErrors['shipping_address.city']} className="mt-1" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="state" value="State" />
                                            <TextInput
                                                id="state"
                                                className="mt-1 block w-full"
                                                value={data.shipping_address.state}
                                                onChange={(e) => setAddressField('state', e.target.value)}
                                            />
                                            <InputError message={formErrors['shipping_address.state']} className="mt-1" />
                                        </div>
                                    </div>
                                    <label className="mt-4 flex items-center gap-2 text-sm text-neutral-600">
                                        <input
                                            type="checkbox"
                                            checked={data.save_address}
                                            onChange={(e) => setData('save_address', e.target.checked)}
                                            className="rounded border-neutral-300 text-orange-600 focus:ring-orange-500"
                                        />
                                        {__('Save this address for next time')}
                                    </label>
                                </>
                            )}
                        </div>

                        {/* Per-vendor order summary + coupon */}
                        {itemsByVendor.map((group) => (
                            <div key={group.vendor.id} className="rounded-lg border border-neutral-200 bg-white">
                                <div className="flex items-center justify-between border-b border-neutral-100 px-4 py-3">
                                    <span className="text-sm font-semibold text-neutral-800">
                                        {group.vendor.store ? t(group.vendor.store.name) : group.vendor.business_name}
                                    </span>
                                    <span className="flex items-baseline gap-2 text-sm text-neutral-500">
                                        {group.original_subtotal > group.subtotal && (
                                            <span className="text-xs text-neutral-400 line-through">
                                                {formatNaira(group.original_subtotal)}
                                            </span>
                                        )}
                                        <span>{formatNaira(group.subtotal)}</span>
                                    </span>
                                </div>
                                <div className="divide-y divide-neutral-100 px-4">
                                    {group.items.map((item) => (
                                        <div key={item.id} className="flex items-center justify-between py-2 text-sm text-neutral-600">
                                            <span className="line-clamp-1">
                                                {item.product ? t(item.product.title) : 'Product'} × {item.quantity}
                                            </span>
                                            <span>{formatNaira(item.unit_price * item.quantity)}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="border-t border-neutral-100 px-4 py-2 text-xs text-neutral-500">
                                    <div className="flex items-center justify-between py-0.5">
                                        <span>{__('Subtotal')}</span>
                                        <span>{formatNaira(group.subtotal)}</span>
                                    </div>
                                    {group.discount > 0 && (
                                        <div className="flex items-center justify-between py-0.5 text-green-600">
                                            <span>Coupon discount ({group.applied_coupon_code})</span>
                                            <span>−{formatNaira(group.discount)}</span>
                                        </div>
                                    )}
                                    <div className="flex items-center justify-between py-0.5">
                                        <span>{__('VAT')}</span>
                                        <span>{formatNaira(group.vat_amount)}</span>
                                    </div>
                                    <div className="flex items-center justify-between py-0.5">
                                        <span>{__('Shipping')}</span>
                                        <span>{formatNaira(group.shipping_amount)}</span>
                                    </div>
                                    <div className="flex items-center justify-between border-t border-neutral-100 py-1 pt-1.5 font-semibold text-neutral-700">
                                        <span>{__('Total')}</span>
                                        <span>{formatNaira(group.total)}</span>
                                    </div>
                                </div>
                                <div className="border-t border-neutral-100 px-4 py-3">
                                    <CouponForm vendorId={group.vendor.id} appliedCode={group.applied_coupon_code} errors={errors ?? {}} />
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="w-full lg:w-72">
                        <div className="rounded-lg border border-neutral-200 bg-white p-5">
                            <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Order Summary')}</h2>
                            <div className="space-y-2 border-b border-neutral-100 pb-4 text-sm">
                                {hasItemDiscount && (
                                    <div className="flex items-center justify-between text-neutral-400">
                                        <span>{__('Original Price')}</span>
                                        <span className="line-through">{formatNaira(originalSubtotal)}</span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between text-neutral-600">
                                    <span>{__('Subtotal')}</span>
                                    <span className="font-medium text-neutral-800">{formatNaira(subtotal)}</span>
                                </div>
                                {couponDiscountTotal > 0 && (
                                    <div className="flex items-center justify-between text-green-600">
                                        <span>Coupon Discount</span>
                                        <span>−{formatNaira(couponDiscountTotal)}</span>
                                    </div>
                                )}
                                {(hasItemDiscount || couponDiscountTotal > 0) && (
                                    <div className="flex items-center justify-between text-neutral-600">
                                        <span>{__('Price After Discount')}</span>
                                        <span className="font-medium text-neutral-800">{formatNaira(priceAfterAllDiscounts)}</span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between text-neutral-600">
                                    <span>{__('VAT')}</span>
                                    <span>{formatNaira(vatTotal)}</span>
                                </div>
                                <div className="flex items-center justify-between text-neutral-600">
                                    <span>{__('Shipping Fees')}</span>
                                    <span>{formatNaira(shippingTotal)}</span>
                                </div>
                            </div>
                            <div className="mb-4 flex items-center justify-between pt-4">
                                <span className="text-sm font-semibold text-neutral-800">{__('Grand Total')}</span>
                                <span className="text-2xl font-bold text-orange-600">{formatNaira(grandTotal)}</span>
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-orange-600 py-3 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                            >
                                {processing ? __('Processing…') : __('Complete Payment')}
                            </button>
                            <p className="mt-3 text-center text-xs text-neutral-400">
                                {__("You'll be redirected to Korapay's secure checkout page.")}
                            </p>
                        </div>

                        <PaymentBadges />

                        <div className="mt-4 rounded-lg border border-green-100 bg-green-50 p-4">
                            <div className="flex items-start gap-2">
                                <svg className="mt-0.5 h-5 w-5 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <div>
                                    <p className="text-sm font-semibold text-green-800">
                                        {__("Your money stays protected until you confirm you're happy — or your return window passes.")}
                                    </p>
                                    <p className="mt-1 text-xs text-green-700">
                                        {__('You will have :days days after delivery to confirm receipt or report a problem. We only release payment to the vendor once you do, or once that window passes.', { days: escrowReturnWindowDays })}
                                    </p>
                                    <Link href={route('legal.customer-protection')} className="mt-1 inline-block text-xs font-semibold text-green-800 hover:underline">
                                        {__('Learn how buyer protection works')} →
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </StorefrontLayout>
    );
}
