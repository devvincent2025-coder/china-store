import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import StatusBadge from '@/Components/StatusBadge';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';
import confirm from '@/utils/confirm';

/**
 * Explains every field on the create-coupon form in plain language — reachable via the "?" button
 * next to the page title. Nothing here is fetched or computed; it's static reference copy kept in
 * sync with Vendor\CouponController's validation rules and Coupon::calculateDiscount().
 */
function CouponFieldsGuide({ show, onClose }) {
    const { __ } = useTranslation();

    const ITEMS = [
        {
            term: __('Code'),
            body: __('The word or phrase a buyer types at checkout to apply the discount (e.g. SAVE10). Letters, numbers, and dashes only — it must be unique across your own coupons.'),
        },
        {
            term: __('Type'),
            body: __('Percentage takes a % off the order subtotal. Flat (₦) takes a fixed Naira amount off instead, capped so it never makes the order free.'),
        },
        {
            term: __('Value'),
            body: __('The size of the discount — a number like 10 for 10% (Percentage) or a Naira amount like 2000 for ₦2,000 off (Flat).'),
        },
        {
            term: __('Minimum Spend'),
            body: __("The smallest order subtotal (before this discount) a buyer must reach for the coupon to apply. Leave at 0 for no minimum."),
        },
        {
            term: __('Usage Limit'),
            body: __('The total number of times this coupon can ever be redeemed across all buyers, combined. Leave blank for unlimited uses.'),
        },
        {
            term: __('Active'),
            body: __("Switches the coupon on or off instantly — turning it off doesn't delete it or affect past orders that already used it, it just stops new redemptions."),
        },
        {
            term: __('Starts At / Expires At'),
            body: __('The window during which the coupon can be redeemed. Leave either blank for no start date or no expiry.'),
        },
        {
            term: __('Used'),
            body: __('How many times this coupon has been redeemed so far (out of its Usage Limit, if it has one). Click "View Redemptions" on any coupon to see exactly which orders used it.'),
        },
    ];

    return (
        <Modal show={show} onClose={onClose} maxWidth="xl">
            <div className="p-6">
                <h2 className="text-lg font-bold text-neutral-900">{__('Coupon Fields Guide')}</h2>
                <p className="mt-1 text-sm text-neutral-500">{__('What each field on this page means.')}</p>

                <dl className="mt-5 space-y-4">
                    {ITEMS.map((item) => (
                        <div key={item.term}>
                            <dt className="text-sm font-semibold text-neutral-800">{item.term}</dt>
                            <dd className="mt-0.5 text-sm text-neutral-600">{item.body}</dd>
                        </div>
                    ))}
                </dl>

                <div className="mt-6 flex justify-end">
                    <button type="button" onClick={onClose} className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50">
                        {__('Close')}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

function RedemptionsModal({ coupon, onClose }) {
    const { __ } = useTranslation();

    return (
        <Modal show={coupon !== null} onClose={onClose} maxWidth="xl">
            <div className="p-6">
                <h2 className="text-lg font-bold text-neutral-900">
                    {__('Redemptions for :code', { code: coupon?.code ?? '' })}
                </h2>
                <p className="mt-1 text-sm text-neutral-500">
                    {coupon?.usages_count ?? 0} {__('transaction(s)')}
                </p>

                <div className="mt-4 max-h-96 overflow-y-auto">
                    {!coupon?.usages || coupon.usages.length === 0 ? (
                        <p className="py-8 text-center text-sm text-neutral-400">{__('No transactions yet.')}</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                                <tr>
                                    <th className="px-3 py-2">{__('Order #')}</th>
                                    <th className="px-3 py-2">{__('Buyer')}</th>
                                    <th className="px-3 py-2">{__('Discount')}</th>
                                    <th className="px-3 py-2">{__('Date')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {coupon.usages.map((usage) => (
                                    <tr key={usage.id}>
                                        <td className="px-3 py-2 font-medium text-neutral-800">{usage.vendor_order?.order?.order_number ?? '—'}</td>
                                        <td className="px-3 py-2 text-neutral-600">{usage.user?.name ?? '—'}</td>
                                        <td className="px-3 py-2 text-neutral-600">{formatNaira(usage.discount_amount)}</td>
                                        <td className="px-3 py-2 text-neutral-500">{new Date(usage.created_at).toLocaleDateString('en-NG')}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                <div className="mt-6 flex justify-end">
                    <button type="button" onClick={onClose} className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50">
                        {__('Close')}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

export default function Index({ coupons, vendor }) {
    const { __ } = useTranslation();
    const [guideOpen, setGuideOpen] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);
    const [viewingCoupon, setViewingCoupon] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        type: 'percentage',
        value: '',
        minimum_spend: '',
        usage_limit: '',
        starts_at: '',
        expires_at: '',
        is_active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('vendor.coupons.store'), {
            preserveScroll: true,
            onSuccess: () => { reset(); setCreateOpen(false); },
        });
    };

    const destroy = async (coupon) => {
        if (await confirm(__('Delete this coupon?'), { tone: 'danger' })) {
            router.delete(route('vendor.coupons.destroy', coupon.id));
        }
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Coupons')} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-neutral-900">{__('Coupons')}</h1>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setGuideOpen(true)}
                        className="flex items-center gap-1.5 rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {__('What do these fields mean?')}
                    </button>
                    <button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700"
                    >
                        + {__('Create Coupon')}
                    </button>
                </div>
            </div>

            <Modal show={createOpen} onClose={() => setCreateOpen(false)} maxWidth="3xl">
                <form onSubmit={submit} className="p-6">
                    <h2 className="mb-4 text-lg font-bold text-neutral-900">{__('Create Coupon')}</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel htmlFor="code" value={__('Code')} />
                            <TextInput id="code" className="mt-1 block w-full" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} />
                            <InputError message={errors.code} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="type" value={__('Type')} />
                            <select id="type" value={data.type} onChange={(e) => setData('type', e.target.value)} className="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                <option value="percentage">{__('Percentage')}</option>
                                <option value="flat">{__('Flat (₦)')}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="value" value={data.type === 'percentage' ? __('Value (%)') : __('Value (₦)')} />
                            <TextInput id="value" type="number" className="mt-1 block w-full" value={data.value} onChange={(e) => setData('value', e.target.value)} />
                            <InputError message={errors.value} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="minimum_spend" value={__('Minimum Spend (₦)')} />
                            <TextInput id="minimum_spend" type="number" className="mt-1 block w-full" value={data.minimum_spend} onChange={(e) => setData('minimum_spend', e.target.value)} />
                        </div>
                        <div>
                            <InputLabel htmlFor="usage_limit" value={__('Usage Limit')} />
                            <TextInput id="usage_limit" type="number" className="mt-1 block w-full" value={data.usage_limit} onChange={(e) => setData('usage_limit', e.target.value)} />
                        </div>
                        <div className="flex items-end gap-2">
                            <input id="is_active" type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-neutral-300 text-orange-600" />
                            <InputLabel htmlFor="is_active" value={__('Active')} />
                        </div>
                        <div>
                            <InputLabel htmlFor="starts_at" value={__('Starts At')} />
                            <TextInput id="starts_at" type="date" className="mt-1 block w-full" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                        </div>
                        <div>
                            <InputLabel htmlFor="expires_at" value={__('Expires At')} />
                            <TextInput id="expires_at" type="date" className="mt-1 block w-full" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} />
                            <InputError message={errors.expires_at} className="mt-1" />
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-2">
                        <button type="button" onClick={() => setCreateOpen(false)} className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50">
                            {__('Cancel')}
                        </button>
                        <button type="submit" disabled={processing} className="rounded-md bg-orange-600 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50">
                            {__('Create Coupon')}
                        </button>
                    </div>
                </form>
            </Modal>

            {coupons.length === 0 ? (
                <EmptyState
                    title={__('No coupons yet')}
                    description={__('Click "Create Coupon" above to offer your first discount.')}
                />
            ) : (
                <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                            <tr>
                                <th className="px-4 py-2">{__('Code')}</th>
                                <th className="px-4 py-2">{__('Type')}</th>
                                <th className="px-4 py-2">{__('Value')}</th>
                                <th className="px-4 py-2">{__('Used')}</th>
                                <th className="px-4 py-2">{__('Active')}</th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100">
                            {coupons.map((coupon) => (
                                <tr key={coupon.id}>
                                    <td className="px-4 py-3 font-medium text-neutral-800">{coupon.code}</td>
                                    <td className="px-4 py-3 capitalize text-neutral-600">{coupon.type}</td>
                                    <td className="px-4 py-3 text-neutral-600">
                                        {coupon.type === 'percentage' ? `${coupon.value}%` : formatNaira(coupon.value)}
                                    </td>
                                    <td className="px-4 py-3 text-neutral-600">
                                        <button
                                            type="button"
                                            onClick={() => setViewingCoupon(coupon)}
                                            className="font-medium text-orange-600 hover:underline disabled:cursor-default disabled:text-neutral-400 disabled:no-underline"
                                            disabled={!coupon.usages_count}
                                        >
                                            {coupon.used_count}{coupon.usage_limit ? ` / ${coupon.usage_limit}` : ''}
                                            {coupon.usages_count > 0 && <span className="ml-1 text-xs">({__('View')})</span>}
                                        </button>
                                    </td>
                                    <td className="px-4 py-3"><StatusBadge status={coupon.is_active ? 'active' : 'inactive'} /></td>
                                    <td className="px-4 py-3 text-right">
                                        <button onClick={() => destroy(coupon)} className="font-medium text-red-500 hover:underline">{__('Delete')}</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <CouponFieldsGuide show={guideOpen} onClose={() => setGuideOpen(false)} />
            <RedemptionsModal coupon={viewingCoupon} onClose={() => setViewingCoupon(null)} />
        </VendorLayout>
    );
}
