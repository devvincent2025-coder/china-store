import { Head, useForm } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatNaira, koboToNaira, nairaToKobo } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

export default function Settings({ vendor }) {
    const { __ } = useTranslation();

    const SHIPPING_FEE_TYPES = [
        { value: 'fixed', label: __('Fixed amount') },
        { value: 'percentage', label: __('Percentage of order') },
        { value: 'both', label: __('Both') },
    ];

    const { data, setData, patch, processing, errors } = useForm({
        vat_rate: vendor.vat_rate ?? '0',
        shipping_fee_type: vendor.shipping_fee_type ?? 'fixed',
        shipping_fee_fixed_amount: koboToNaira(vendor.shipping_fee_fixed_amount ?? 0),
        shipping_fee_percentage: vendor.shipping_fee_percentage ?? '0',
    });

    const preferencesForm = useForm({
        vacation_mode: vendor.vacation_mode ?? false,
        vacation_message: vendor.vacation_message ?? '',
        low_stock_threshold: vendor.low_stock_threshold ?? 5,
        notify_new_order_email: vendor.notify_new_order_email ?? true,
        notify_new_order_push: vendor.notify_new_order_push ?? true,
        auto_publish_products: vendor.auto_publish_products ?? true,
        delivery_days_min: vendor.delivery_days_min ?? '',
        delivery_days_max: vendor.delivery_days_max ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('vendor.settings.update'));
    };

    const submitPreferences = (e) => {
        e.preventDefault();
        preferencesForm.patch(route('vendor.settings.preferences.update'), { preserveScroll: true });
    };

    const showFixed = data.shipping_fee_type === 'fixed' || data.shipping_fee_type === 'both';
    const showPercentage = data.shipping_fee_type === 'percentage' || data.shipping_fee_type === 'both';

    // Small live example so a vendor can see what their current settings mean in practice.
    const exampleOrderKobo = nairaToKobo(10000);
    let exampleShippingKobo = 0;
    if (showFixed) {
        exampleShippingKobo += nairaToKobo(Number(data.shipping_fee_fixed_amount) || 0);
    }
    if (showPercentage) {
        exampleShippingKobo += Math.round((exampleOrderKobo * (Number(data.shipping_fee_percentage) || 0)) / 100);
    }

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Settings')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Settings')}</h1>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            <form onSubmit={submit} className="space-y-6">
                {/* VAT */}
                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('VAT Rate')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__("The VAT you collect on your sales is yours to remit — the platform doesn't take any cut of it, it's just passed on to buyers at checkout.")}
                    </p>
                    <div className="max-w-xs">
                        <InputLabel htmlFor="vat_rate" value={__('VAT Rate (%)')} />
                        <TextInput
                            id="vat_rate"
                            type="number"
                            min="0"
                            max="20"
                            step="0.5"
                            className="mt-1 block w-full"
                            value={data.vat_rate}
                            onChange={(e) => setData('vat_rate', e.target.value)}
                        />
                        <InputError message={errors.vat_rate} className="mt-1" />
                    </div>
                </div>

                {/* Shipping fees */}
                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Shipping Fees')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__('Choose how you charge buyers for shipping on your orders.')}
                    </p>

                    <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:gap-4">
                        {SHIPPING_FEE_TYPES.map((option) => (
                            <label
                                key={option.value}
                                className={`flex flex-1 cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm ${
                                    data.shipping_fee_type === option.value
                                        ? 'border-orange-600 bg-orange-50 text-orange-700'
                                        : 'border-neutral-300 text-neutral-600 hover:border-orange-300'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="shipping_fee_type"
                                    value={option.value}
                                    checked={data.shipping_fee_type === option.value}
                                    onChange={(e) => setData('shipping_fee_type', e.target.value)}
                                    className="text-orange-600 focus:ring-orange-500"
                                />
                                {option.label}
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.shipping_fee_type} className="mb-4" />

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {showFixed && (
                            <div>
                                <InputLabel htmlFor="shipping_fee_fixed_amount" value={__('Fixed Amount (₦)')} />
                                <TextInput
                                    id="shipping_fee_fixed_amount"
                                    type="number"
                                    min="0"
                                    step="1"
                                    className="mt-1 block w-full"
                                    value={data.shipping_fee_fixed_amount}
                                    onChange={(e) => setData('shipping_fee_fixed_amount', e.target.value)}
                                />
                                <InputError message={errors.shipping_fee_fixed_amount} className="mt-1" />
                            </div>
                        )}
                        {showPercentage && (
                            <div>
                                <InputLabel htmlFor="shipping_fee_percentage" value={__('Percentage of Order (%)')} />
                                <TextInput
                                    id="shipping_fee_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    className="mt-1 block w-full"
                                    value={data.shipping_fee_percentage}
                                    onChange={(e) => setData('shipping_fee_percentage', e.target.value)}
                                />
                                <InputError message={errors.shipping_fee_percentage} className="mt-1" />
                            </div>
                        )}
                    </div>

                    {(showFixed || showPercentage) && (
                        <p className="mt-4 rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                            {__('Example: on a ₦10,000 order, you\'d charge')}{' '}
                            <span className="font-semibold text-neutral-700">{formatNaira(exampleShippingKobo)}</span>{' '}
                            {__('in shipping.')}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                >
                    {__('Save Settings')}
                </button>
            </form>

            {/* Store preferences — a separate form/request from VAT & shipping above, since these
                are unrelated settings a vendor may change independently. */}
            <form onSubmit={submitPreferences} className="space-y-6">
                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Vacation Mode')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__('Temporarily hide your store and products from the marketplace — buyers already checking out with your items are unaffected.')}
                    </p>

                    <label className="mb-3 flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={preferencesForm.data.vacation_mode}
                            onChange={(e) => preferencesForm.setData('vacation_mode', e.target.checked)}
                            className="rounded text-orange-600 focus:ring-orange-500"
                        />
                        <span className="text-sm text-neutral-700">{__('My store is on vacation')}</span>
                    </label>

                    {preferencesForm.data.vacation_mode && (
                        <div>
                            <InputLabel htmlFor="vacation_message" value={__('Message shown to buyers (optional)')} />
                            <TextInput
                                id="vacation_message"
                                className="mt-1 block w-full"
                                placeholder={__("e.g. \"Back on Monday, thanks for your patience!\"")}
                                value={preferencesForm.data.vacation_message}
                                onChange={(e) => preferencesForm.setData('vacation_message', e.target.value)}
                            />
                            <InputError message={preferencesForm.errors.vacation_message} className="mt-1" />
                        </div>
                    )}
                </div>

                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Delivery Estimate')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__('Shown to buyers on your products, your store page, and their order details — e.g. "Delivers in 2-4 days". Leave both blank to show no estimate.')}
                    </p>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="delivery_days_min" value={__('Minimum days')} />
                            <TextInput
                                id="delivery_days_min"
                                type="number"
                                min="1"
                                max="60"
                                className="mt-1 block w-full"
                                value={preferencesForm.data.delivery_days_min}
                                onChange={(e) => preferencesForm.setData('delivery_days_min', e.target.value)}
                            />
                            <InputError message={preferencesForm.errors.delivery_days_min} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="delivery_days_max" value={__('Maximum days')} />
                            <TextInput
                                id="delivery_days_max"
                                type="number"
                                min="1"
                                max="60"
                                className="mt-1 block w-full"
                                value={preferencesForm.data.delivery_days_max}
                                onChange={(e) => preferencesForm.setData('delivery_days_max', e.target.value)}
                            />
                            <InputError message={preferencesForm.errors.delivery_days_max} className="mt-1" />
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Inventory Alerts')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__('Get a low-stock warning on your dashboard once a product drops to or below this quantity.')}
                    </p>
                    <div className="max-w-xs">
                        <InputLabel htmlFor="low_stock_threshold" value={__('Low Stock Threshold (units)')} />
                        <TextInput
                            id="low_stock_threshold"
                            type="number"
                            min="1"
                            max="100"
                            className="mt-1 block w-full"
                            value={preferencesForm.data.low_stock_threshold}
                            onChange={(e) => preferencesForm.setData('low_stock_threshold', e.target.value)}
                        />
                        <InputError message={preferencesForm.errors.low_stock_threshold} className="mt-1" />
                    </div>
                </div>

                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('New Order Notifications')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__('Every new order always appears in your in-app notifications — choose which other channels also alert you.')}
                    </p>
                    <div className="space-y-2">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={preferencesForm.data.notify_new_order_email}
                                onChange={(e) => preferencesForm.setData('notify_new_order_email', e.target.checked)}
                                className="rounded text-orange-600 focus:ring-orange-500"
                            />
                            <span className="text-sm text-neutral-700">{__('Email me')}</span>
                        </label>
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={preferencesForm.data.notify_new_order_push}
                                onChange={(e) => preferencesForm.setData('notify_new_order_push', e.target.checked)}
                                className="rounded text-orange-600 focus:ring-orange-500"
                            />
                            <span className="text-sm text-neutral-700">{__('Send a push notification')}</span>
                        </label>
                    </div>
                </div>

                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('New Products')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__("Choose what happens when you create a new product — publish it immediately, or save it as a draft to review first.")}
                    </p>
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={preferencesForm.data.auto_publish_products}
                            onChange={(e) => preferencesForm.setData('auto_publish_products', e.target.checked)}
                            className="rounded text-orange-600 focus:ring-orange-500"
                        />
                        <span className="text-sm text-neutral-700">{__('Publish new products immediately')}</span>
                    </label>
                </div>

                <button
                    type="submit"
                    disabled={preferencesForm.processing}
                    className="rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                >
                    {__('Save Preferences')}
                </button>
            </form>
            </div>
        </VendorLayout>
    );
}
