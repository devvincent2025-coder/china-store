import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import ExportMenu from '@/Components/ExportMenu';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ vendorOrders, vendor, filters = {} }) {
    const { __ } = useTranslation();

    const [q, setQ] = useState(filters.q ?? '');
    const [fulfillmentStatus, setFulfillmentStatus] = useState(filters.fulfillment_status ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [hasCoupon, setHasCoupon] = useState(Boolean(filters.has_coupon));

    const applyFilters = (overrides = {}) => {
        const params = {
            q,
            fulfillment_status: fulfillmentStatus,
            date_from: dateFrom,
            date_to: dateTo,
            has_coupon: hasCoupon,
            ...overrides,
        };

        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === undefined || params[key] === false) {
                delete params[key];
            }
        });

        router.get(route('vendor.orders.index'), params, { preserveState: true, replace: true });
    };

    const submitSearch = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const clearFilters = () => {
        setQ('');
        setFulfillmentStatus('');
        setDateFrom('');
        setDateTo('');
        setHasCoupon(false);
        router.get(route('vendor.orders.index'), {}, { preserveState: true, replace: true });
    };

    const hasActiveFilters = Boolean(filters.q || filters.fulfillment_status || filters.date_from || filters.date_to || filters.has_coupon);

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Orders')} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-bold text-neutral-900">{__('Orders')}</h1>
                <ExportMenu routeName="vendor.exports.orders" />
            </div>

            <form onSubmit={submitSearch} className="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-neutral-200 bg-white p-3">
                <input
                    type="text"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={__('Search by order # or buyer...')}
                    className="w-full flex-1 rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500 sm:min-w-[200px] sm:w-auto"
                />
                <select
                    value={fulfillmentStatus}
                    onChange={(e) => { setFulfillmentStatus(e.target.value); applyFilters({ fulfillment_status: e.target.value }); }}
                    className="rounded-md border-neutral-300 text-sm"
                >
                    <option value="">{__('All statuses')}</option>
                    <option value="processing">{__('Processing')}</option>
                    <option value="shipped">{__('Shipped')}</option>
                    <option value="delivered">{__('Delivered')}</option>
                    <option value="cancelled">{__('Cancelled')}</option>
                </select>
                <label className="flex items-center gap-1.5 text-sm text-neutral-600">
                    {__('From')}
                    <input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => { setDateFrom(e.target.value); applyFilters({ date_from: e.target.value }); }}
                        className="rounded-md border-neutral-300 text-sm"
                    />
                </label>
                <label className="flex items-center gap-1.5 text-sm text-neutral-600">
                    {__('To')}
                    <input
                        type="date"
                        value={dateTo}
                        onChange={(e) => { setDateTo(e.target.value); applyFilters({ date_to: e.target.value }); }}
                        className="rounded-md border-neutral-300 text-sm"
                    />
                </label>
                <label className="flex items-center gap-1.5 text-sm text-neutral-600">
                    <input
                        type="checkbox"
                        checked={hasCoupon}
                        onChange={(e) => { setHasCoupon(e.target.checked); applyFilters({ has_coupon: e.target.checked }); }}
                        className="rounded text-orange-600 focus:ring-orange-500"
                    />
                    {__('Coupon applied')}
                </label>
                <button type="submit" className="rounded-md bg-neutral-800 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-900">
                    {__('Search')}
                </button>
                {hasActiveFilters && (
                    <button type="button" onClick={clearFilters} className="text-sm text-neutral-400 underline hover:text-neutral-600">
                        {__('Clear filters')}
                    </button>
                )}
            </form>

            {vendorOrders.data.length === 0 ? (
                <EmptyState
                    title={hasActiveFilters ? __('No Orders Found') : __('No orders yet')}
                    description={hasActiveFilters ? __('Try adjusting or clearing your filters.') : __('Orders from buyers will show up here.')}
                />
            ) : (
                <>
                    {/* Mobile: card list — avoids the wide 7-column table forcing horizontal
                        scroll on a phone. Same data/actions as the table below, just stacked. */}
                    <div className="space-y-3 sm:hidden">
                        {vendorOrders.data.map((vo) => (
                            <Link
                                key={vo.id}
                                href={route('vendor.orders.show', vo.id)}
                                className="block rounded-lg border border-neutral-200 bg-white p-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-medium text-neutral-800">{vo.order?.order_number}</p>
                                        <p className="text-xs text-neutral-500">{vo.order?.user?.name}</p>
                                    </div>
                                    <StatusBadge status={vo.fulfillment_status} />
                                </div>
                                <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500">
                                    <span className="font-semibold text-neutral-700">{formatNaira(vo.total)}</span>
                                    <span>{new Date(vo.created_at).toLocaleDateString('en-NG')}</span>
                                    {vo.coupon && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-pink-100 px-2 py-0.5 font-semibold text-pink-700">
                                            {vo.coupon.code}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>

                    {/* Desktop/tablet: table */}
                    <div className="hidden overflow-x-auto rounded-lg border border-neutral-200 bg-white sm:block">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                                <tr>
                                    <th className="px-4 py-2">{__('Order #')}</th>
                                    <th className="px-4 py-2">{__('Buyer')}</th>
                                    <th className="px-4 py-2">{__('Total')}</th>
                                    <th className="px-4 py-2">{__('Coupon')}</th>
                                    <th className="px-4 py-2">{__('Status')}</th>
                                    <th className="px-4 py-2">{__('Date')}</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {vendorOrders.data.map((vo) => (
                                    <tr key={vo.id}>
                                        <td className="px-4 py-3 font-medium text-neutral-800">{vo.order?.order_number}</td>
                                        <td className="px-4 py-3 text-neutral-600">{vo.order?.user?.name}</td>
                                        <td className="px-4 py-3 text-neutral-600">{formatNaira(vo.total)}</td>
                                        <td className="px-4 py-3">
                                            {vo.coupon ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-pink-100 px-2 py-0.5 text-xs font-semibold text-pink-700">
                                                    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                                    </svg>
                                                    {vo.coupon.code}
                                                </span>
                                            ) : (
                                                <span className="text-neutral-300">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3"><StatusBadge status={vo.fulfillment_status} /></td>
                                        <td className="px-4 py-3 text-neutral-500">{new Date(vo.created_at).toLocaleDateString('en-NG')}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={route('vendor.orders.show', vo.id)} className="font-medium text-orange-600 hover:underline">
                                                {__('View')}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination paginator={vendorOrders} />
                </>
            )}
        </VendorLayout>
    );
}
