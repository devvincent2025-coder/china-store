import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import EmptyState from '@/Components/EmptyState';
import PaymentBadges from '@/Components/PaymentBadges';
import InputError from '@/Components/InputError';
import { formatNaira } from '@/utils/money';
import { NIGERIAN_STATES } from '@/utils/nigerianStates';
import { showToast } from '@/Components/ToastHost';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

const ADDRESS_FIELDS = ['name', 'phone', 'line1', 'city', 'state'];

/** Same rules as CheckoutController::store() — checked here first so mistakes show instantly. */
function validateAddress(address) {
    const errors = {};
    const name = address.name.trim();
    const phone = address.phone.replace(/[\s\-()]/g, '');
    const line1 = address.line1.trim();
    const city = address.city.trim();

    if (!name) errors.name = 'Full name is required.';
    else if (name.length < 2) errors.name = 'Please enter your full name.';

    if (!phone) errors.phone = 'Phone number is required.';
    else if (!/^(\+?234|0)[789][01]\d{8}$/.test(phone)) errors.phone = 'Enter a valid Nigerian phone number, e.g. 08031234567.';

    if (!line1) errors.line1 = 'Address is required.';
    else if (line1.length < 5) errors.line1 = 'Please enter your full street address.';

    if (!city) errors.city = 'City is required.';
    else if (city.length < 2) errors.city = 'Please enter a valid city.';

    if (!address.state) errors.state = 'State is required.';
    else if (!NIGERIAN_STATES.includes(address.state)) errors.state = 'Please choose a state from the list.';

    return errors;
}

/** Scrolls the first invalid shipping field into view and focuses it. */
function focusFirstInvalid(fields) {
    const first = ADDRESS_FIELDS.find((f) => fields.includes(f));
    const el = first && document.getElementById(`shipping-${first}`);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.focus({ preventScroll: true });
}

const fieldClass = (hasError) =>
    'mt-1 block w-full rounded-lg text-base text-neutral-900 placeholder:text-neutral-400 shadow-sm transition ' +
    (hasError
        ? 'border-2 border-red-500 bg-red-50/40 focus:border-red-500 focus:ring-red-500'
        : 'border-neutral-300 focus:border-orange-500 focus:ring-orange-500');

function AddressField({ id, label, error, children, className = '' }) {
    return (
        <div className={className}>
            <label htmlFor={`shipping-${id}`} className="block text-sm font-semibold text-neutral-800">
                {label} <span className="text-red-500">*</span>
            </label>
            {children}
            {error && (
                <p id={`shipping-${id}-error`} className="mt-1 flex items-center gap-1 text-sm font-medium text-red-600">
                    <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    {error}
                </p>
            )}
        </div>
    );
}

/**
 * One vendor group's coupon input. Applying/removing a coupon is its own request (not part of the
 * final order submission) so the discount is validated server-side and staged in the session
 * BEFORE the buyer pays — see CheckoutController's applyCoupon()/removeCoupon(). The numbers this
 * page shows are always exactly what will be charged, never a client-side guess.
 */
function CouponForm({ vendorId, appliedCode, discount, errors }) {
    const { __ } = useTranslation();
    const [code, setCode] = useState('');
    const [applying, setApplying] = useState(false);
    const [removing, setRemoving] = useState(false);
    const error = errors[`coupons.${vendorId}`];

    // NOT a <form>: this sits inside the main checkout <form>, and browsers drop nested forms —
    // which made "Apply" submit the whole checkout instead. Enter in the input applies too.
    const apply = () => {
        if (!code.trim() || applying) return;

        setApplying(true);
        router.post(
            route('checkout.coupons.apply'),
            { vendor_id: vendorId, code: code.trim() },
            {
                preserveScroll: true,
                // Keeps whatever the buyer already typed into the shipping address form.
                preserveState: true,
                onSuccess: () => setCode(''),
                onError: (errs) => showToast('error', errs[`coupons.${vendorId}`] ?? __('This coupon could not be applied.')),
                onFinish: () => setApplying(false),
            },
        );
    };

    const remove = () => {
        setRemoving(true);
        router.delete(route('checkout.coupons.remove', vendorId), {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setRemoving(false),
        });
    };

    if (appliedCode) {
        return (
            <div className="flex items-center justify-between gap-3 rounded-xl bg-green-50 px-4 py-3 text-base text-green-800">
                <span className="flex min-w-0 items-center gap-2">
                    <svg className="h-5 w-5 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="min-w-0">
                        <span className="font-bold tracking-wide">{appliedCode}</span> {__('applied')}
                        {discount > 0 && <span className="ml-1 font-semibold">· −{formatNaira(discount)}</span>}
                    </span>
                </span>
                <button
                    type="button"
                    onClick={remove}
                    disabled={removing}
                    className="shrink-0 text-sm font-medium text-green-800 underline hover:text-green-900 disabled:opacity-50"
                >
                    {removing ? __('Removing…') : __('Remove')}
                </button>
            </div>
        );
    }

    return (
        <div>
            <label htmlFor={`coupon-${vendorId}`} className="mb-1.5 block text-sm font-semibold text-neutral-800">
                {__('Have a coupon for this store?')}
            </label>
            <div className="flex items-center gap-2">
                <input
                    id={`coupon-${vendorId}`}
                    type="text"
                    placeholder={__('Enter coupon code')}
                    value={code}
                    onChange={(e) => setCode(e.target.value.toUpperCase())}
                    onKeyDown={(e) => {
                        // Enter would otherwise submit the outer checkout form.
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            apply();
                        }
                    }}
                    autoComplete="off"
                    autoCapitalize="characters"
                    aria-invalid={!!error}
                    className={
                        'w-full rounded-lg text-base uppercase tracking-wide text-neutral-900 placeholder:normal-case placeholder:tracking-normal placeholder:text-neutral-500 ' +
                        (error ? 'border-2 border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-neutral-300 focus:border-orange-500 focus:ring-orange-500')
                    }
                />
                <button
                    type="button"
                    onClick={apply}
                    disabled={applying || !code.trim()}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black disabled:opacity-50"
                >
                    {applying && (
                        <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden>
                            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.3" strokeWidth="3" />
                            <path d="M21 12a9 9 0 00-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                        </svg>
                    )}
                    {applying ? __('Applying…') : __('Apply')}
                </button>
            </div>
            {error && <p className="mt-1.5 text-sm font-medium text-red-600">{error}</p>}
        </div>
    );
}

export default function Index({ itemsByVendor, errors, savedAddresses = [], escrowReturnWindowDays = 7, couponNotices = [] }) {
    const { __ } = useTranslation();

    // A previously applied coupon that no longer qualifies (e.g. cart dropped below its minimum).
    useEffect(() => {
        couponNotices.forEach((notice) => showToast('warning', notice));
    }, [couponNotices]);
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

    const { data, setData, post, processing, errors: formErrors, setError, clearErrors } = useForm({
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
        // Clear that field's red state as soon as the buyer starts fixing it.
        if (formErrors[`shipping_address.${field}`]) clearErrors(`shipping_address.${field}`);
    };

    const addressError = (field) => formErrors[`shipping_address.${field}`];

    const submit = (e) => {
        e.preventDefault();

        if (addingNewAddress) {
            const problems = validateAddress(data.shipping_address);
            const fields = Object.keys(problems);
            if (fields.length > 0) {
                clearErrors();
                setError(Object.fromEntries(fields.map((f) => [`shipping_address.${f}`, problems[f]])));
                showToast('error', __('Please complete your shipping details.'));
                focusFirstInvalid(fields);
                return;
            }
        }

        post(route('checkout.store'), {
            preserveScroll: true,
            onError: (errors) => {
                const fields = ADDRESS_FIELDS.filter((f) => errors[`shipping_address.${f}`]);
                if (fields.length > 0) {
                    showToast('error', __('Please check your shipping details.'));
                    focusFirstInvalid(fields);
                }
            },
        });
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

            <div className="mx-auto max-w-marketplace px-4 py-6 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-2xl font-bold text-neutral-900">{__('Checkout')}</h1>

                <form onSubmit={submit} className="flex flex-col gap-8 lg:flex-row">
                    <div className="flex-1 space-y-6">
                        {/* Shipping address */}
                        <div className="rounded-2xl bg-white p-6">
                            <h2 className="mb-4 text-lg font-bold text-neutral-900">{__('Shipping Address')}</h2>

                            {savedAddresses.length > 0 && (
                                <div className="mb-4 space-y-2">
                                    {savedAddresses.map((addr) => (
                                        <label
                                            key={addr.id}
                                            className={
                                                'flex cursor-pointer items-start gap-3 rounded-xl p-4 text-base transition ' +
                                                (data.address_id === addr.id ? 'bg-orange-50' : 'bg-neutral-50 hover:bg-neutral-100')
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
                                                <p className="flex items-center gap-2 font-semibold text-neutral-900">
                                                    {addr.name}
                                                    {addr.is_default && (
                                                        <span className="rounded bg-neutral-200 px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-700">
                                                            {__('Default')}
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="text-neutral-700">{addr.line1}, {addr.city}, {addr.state}</p>
                                                <p className="text-neutral-600">{addr.phone}</p>
                                            </div>
                                        </label>
                                    ))}

                                    <label
                                        className={
                                            'flex cursor-pointer items-center gap-3 rounded-xl p-4 text-base transition ' +
                                            (addingNewAddress ? 'bg-orange-50' : 'bg-neutral-50 hover:bg-neutral-100')
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
                                        <AddressField id="name" label={__('Full Name')} error={addressError('name')}>
                                            <input
                                                id="shipping-name"
                                                type="text"
                                                autoComplete="name"
                                                placeholder="e.g. Chinedu Okafor"
                                                className={fieldClass(addressError('name'))}
                                                value={data.shipping_address.name}
                                                onChange={(e) => setAddressField('name', e.target.value)}
                                                aria-invalid={!!addressError('name')}
                                                aria-describedby={addressError('name') ? 'shipping-name-error' : undefined}
                                            />
                                        </AddressField>
                                        <AddressField id="phone" label={__('Phone')} error={addressError('phone')}>
                                            <input
                                                id="shipping-phone"
                                                type="tel"
                                                inputMode="tel"
                                                autoComplete="tel"
                                                placeholder="e.g. 08031234567"
                                                className={fieldClass(addressError('phone'))}
                                                value={data.shipping_address.phone}
                                                onChange={(e) => setAddressField('phone', e.target.value)}
                                                aria-invalid={!!addressError('phone')}
                                                aria-describedby={addressError('phone') ? 'shipping-phone-error' : undefined}
                                            />
                                        </AddressField>
                                        <AddressField id="line1" label={__('Address')} error={addressError('line1')} className="sm:col-span-2">
                                            <input
                                                id="shipping-line1"
                                                type="text"
                                                autoComplete="street-address"
                                                placeholder={__('House number, street, area')}
                                                className={fieldClass(addressError('line1'))}
                                                value={data.shipping_address.line1}
                                                onChange={(e) => setAddressField('line1', e.target.value)}
                                                aria-invalid={!!addressError('line1')}
                                                aria-describedby={addressError('line1') ? 'shipping-line1-error' : undefined}
                                            />
                                        </AddressField>
                                        <AddressField id="city" label={__('City')} error={addressError('city')}>
                                            <input
                                                id="shipping-city"
                                                type="text"
                                                autoComplete="address-level2"
                                                placeholder="e.g. Ikeja"
                                                className={fieldClass(addressError('city'))}
                                                value={data.shipping_address.city}
                                                onChange={(e) => setAddressField('city', e.target.value)}
                                                aria-invalid={!!addressError('city')}
                                                aria-describedby={addressError('city') ? 'shipping-city-error' : undefined}
                                            />
                                        </AddressField>
                                        <AddressField id="state" label={__('State')} error={addressError('state')}>
                                            <select
                                                id="shipping-state"
                                                autoComplete="address-level1"
                                                className={fieldClass(addressError('state'))}
                                                value={data.shipping_address.state}
                                                onChange={(e) => setAddressField('state', e.target.value)}
                                                aria-invalid={!!addressError('state')}
                                                aria-describedby={addressError('state') ? 'shipping-state-error' : undefined}
                                            >
                                                <option value="">{__('Select a state')}</option>
                                                {NIGERIAN_STATES.map((state) => (
                                                    <option key={state} value={state}>
                                                        {state}
                                                    </option>
                                                ))}
                                            </select>
                                        </AddressField>
                                    </div>
                                    <label className="mt-4 flex items-center gap-2 text-base text-neutral-800">
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
                            <div key={group.vendor.id} className="overflow-hidden rounded-2xl bg-white">
                                <div className="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                                    <span className="text-base font-bold text-neutral-900">
                                        {group.vendor.store ? t(group.vendor.store.name) : group.vendor.business_name}
                                    </span>
                                    <span className="flex items-baseline gap-2 text-base font-semibold text-neutral-900">
                                        {group.original_subtotal > group.subtotal && (
                                            <span className="text-sm font-normal text-neutral-500 line-through">
                                                {formatNaira(group.original_subtotal)}
                                            </span>
                                        )}
                                        <span>{formatNaira(group.subtotal)}</span>
                                    </span>
                                </div>
                                <div className="divide-y divide-neutral-100 px-5">
                                    {group.items.map((item) => (
                                        <div key={item.id} className="flex items-center justify-between gap-4 py-3 text-base text-neutral-800">
                                            <span className="line-clamp-1">
                                                {item.product ? t(item.product.title) : 'Product'} × {item.quantity}
                                            </span>
                                            <span className="shrink-0 font-medium text-neutral-900">{formatNaira(item.unit_price * item.quantity)}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="border-t border-neutral-100 px-5 py-3 text-sm text-neutral-700">
                                    <div className="flex items-center justify-between py-0.5">
                                        <span>{__('Subtotal')}</span>
                                        <span>{formatNaira(group.subtotal)}</span>
                                    </div>
                                    {group.discount > 0 && (
                                        <div className="flex items-center justify-between py-0.5 font-medium text-green-700">
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
                                    <div className="mt-1 flex items-center justify-between border-t border-neutral-100 pt-2 text-base font-bold text-neutral-900">
                                        <span>{__('Total')}</span>
                                        <span>{formatNaira(group.total)}</span>
                                    </div>
                                </div>
                                <div className="border-t border-neutral-100 px-5 py-4">
                                    <CouponForm vendorId={group.vendor.id} appliedCode={group.applied_coupon_code} discount={group.discount} errors={errors ?? {}} />
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="w-full lg:w-[380px]">
                        <div className="rounded-2xl bg-white p-6">
                            <h2 className="mb-4 text-lg font-bold text-neutral-900">{__('Order Summary')}</h2>
                            <div className="space-y-2.5 border-b border-neutral-100 pb-4 text-base">
                                {hasItemDiscount && (
                                    <div className="flex items-center justify-between text-neutral-500">
                                        <span>{__('Original Price')}</span>
                                        <span className="line-through">{formatNaira(originalSubtotal)}</span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between text-neutral-800">
                                    <span>{__('Subtotal')}</span>
                                    <span className="font-semibold text-neutral-900">{formatNaira(subtotal)}</span>
                                </div>
                                {couponDiscountTotal > 0 && (
                                    <div className="flex items-center justify-between font-medium text-green-700">
                                        <span>
                                            {__('Coupon Discount')}
                                            <span className="ml-1 text-sm font-semibold">
                                                ({[...new Set(itemsByVendor.map((g) => g.applied_coupon_code).filter(Boolean))].join(', ')})
                                            </span>
                                        </span>
                                        <span>−{formatNaira(couponDiscountTotal)}</span>
                                    </div>
                                )}
                                {(hasItemDiscount || couponDiscountTotal > 0) && (
                                    <div className="flex items-center justify-between text-neutral-800">
                                        <span>{__('Price After Discount')}</span>
                                        <span className="font-semibold text-neutral-900">{formatNaira(priceAfterAllDiscounts)}</span>
                                    </div>
                                )}
                                <div className="flex items-center justify-between text-neutral-800">
                                    <span>{__('VAT')}</span>
                                    <span>{formatNaira(vatTotal)}</span>
                                </div>
                                <div className="flex items-center justify-between text-neutral-800">
                                    <span>{__('Shipping Fees')}</span>
                                    <span>{formatNaira(shippingTotal)}</span>
                                </div>
                            </div>
                            <div className="mb-4 flex items-center justify-between pt-4">
                                <span className="text-base font-bold text-neutral-900">{__('Grand Total')}</span>
                                <span className="text-3xl font-bold text-orange-600">{formatNaira(grandTotal)}</span>
                            </div>
                            {/* CheckoutService's final stock/MOQ gate reports under `cart` — without this
                                the order was rejected with nothing shown to the buyer. */}
                            {formErrors.cart && (
                                <div role="alert" className="mb-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                    <p>{formErrors.cart}</p>
                                    <Link href={route('cart.index')} className="mt-1 inline-block font-semibold underline">
                                        {__('Update your cart')}
                                    </Link>
                                </div>
                            )}
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-full bg-orange-600 py-3.5 text-base font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                            >
                                {processing ? __('Processing…') : __('Complete Payment')}
                            </button>
                            {/* <p className="mt-3 text-center text-sm text-neutral-600">
                                {__("You'll be redirected to Korapay's secure checkout page.")}
                            </p> */}
                        </div>

                        <PaymentBadges />

                        <div className="mt-4 rounded-2xl bg-green-50 p-5">
                            <div className="flex items-start gap-2">
                                <svg className="mt-0.5 h-5 w-5 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <div>
                                    <p className="text-base font-semibold text-green-900">
                                        {__("Your money stays protected until you confirm you're happy — or your return window passes.")}
                                    </p>
                                    <p className="mt-1 text-sm text-green-800">
                                        {__('You will have :days days after delivery to confirm receipt or report a problem. We only release payment to the vendor once you do, or once that window passes.', { days: escrowReturnWindowDays })}
                                    </p>
                                    <Link href={route('legal.customer-protection')} className="mt-2 inline-block text-sm font-semibold text-green-900 hover:underline">
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
