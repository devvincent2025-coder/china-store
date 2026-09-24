import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import ExportMenu from '@/Components/ExportMenu';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ invoices, vendor, filters = {} }) {
    const { __ } = useTranslation();

    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const applyFilters = (overrides = {}) => {
        const params = { q, status, date_from: dateFrom, date_to: dateTo, ...overrides };

        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === undefined || params[key] === false) {
                delete params[key];
            }
        });

        router.get(route('vendor.invoices.index'), params, { preserveState: true, replace: true });
    };

    const submitSearch = (e) => {
        e.preventDefault();
        applyFilters();
    };

    const clearFilters = () => {
        setQ('');
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get(route('vendor.invoices.index'), {}, { preserveState: true, replace: true });
    };

    const hasActiveFilters = Boolean(filters.q || filters.status || filters.date_from || filters.date_to);

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Invoices')} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-bold text-neutral-900">{__('Invoices')}</h1>
                <div className="flex items-center gap-2">
                    <ExportMenu routeName="vendor.exports.invoices" />
                    <Link
                        href={route('vendor.invoices.create')}
                        className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700"
                    >
                        + {__('New Invoice')}
                    </Link>
                </div>
            </div>

            <form onSubmit={submitSearch} className="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-neutral-200 bg-white p-3">
                <input
                    type="text"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={__('Search by customer or invoice #...')}
                    className="min-w-[200px] flex-1 rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                />
                <select
                    value={status}
                    onChange={(e) => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                    className="rounded-md border-neutral-300 text-sm"
                >
                    <option value="">{__('All statuses')}</option>
                    <option value="draft">{__('Draft')}</option>
                    <option value="sent">{__('Sent')}</option>
                    <option value="partially_paid">{__('Partially Paid')}</option>
                    <option value="paid">{__('Paid')}</option>
                    <option value="void">{__('Void')}</option>
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
                <button type="submit" className="rounded-md bg-neutral-800 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-900">
                    {__('Search')}
                </button>
                {hasActiveFilters && (
                    <button type="button" onClick={clearFilters} className="text-sm text-neutral-400 underline hover:text-neutral-600">
                        {__('Clear filters')}
                    </button>
                )}
            </form>

            {invoices.data.length === 0 ? (
                <EmptyState
                    title={hasActiveFilters ? __('No Invoices Found') : __('No invoices yet')}
                    description={hasActiveFilters ? __('Try adjusting or clearing your filters.') : __('Create an invoice for an out-of-website sale and get paid via Korapay.')}
                    actionHref={hasActiveFilters ? undefined : route('vendor.invoices.create')}
                    actionLabel={hasActiveFilters ? undefined : __('New Invoice')}
                />
            ) : (
                <>
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        <table className="w-full text-sm">
                            <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                                <tr>
                                    <th className="px-4 py-2">{__('Invoice #')}</th>
                                    <th className="px-4 py-2">{__('Customer')}</th>
                                    <th className="px-4 py-2">{__('Total')}</th>
                                    <th className="px-4 py-2">{__('Balance')}</th>
                                    <th className="px-4 py-2">{__('Status')}</th>
                                    <th className="px-4 py-2">{__('Date')}</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {invoices.data.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <td className="px-4 py-3 font-medium text-neutral-800">{invoice.invoice_number}</td>
                                        <td className="px-4 py-3 text-neutral-600">
                                            <div>{invoice.customer_name}</div>
                                            <div className="text-xs text-neutral-400">{invoice.customer_email}</div>
                                        </td>
                                        <td className="px-4 py-3 text-neutral-600">{formatNaira(invoice.total)}</td>
                                        <td className="px-4 py-3 text-neutral-600">{formatNaira(invoice.total - invoice.amount_paid)}</td>
                                        <td className="px-4 py-3"><StatusBadge status={invoice.status} /></td>
                                        <td className="px-4 py-3 text-neutral-500">{new Date(invoice.created_at).toLocaleDateString('en-NG')}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={route('vendor.invoices.show', invoice.id)} className="font-medium text-orange-600 hover:underline">
                                                {__('View')}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination paginator={invoices} />
                </>
            )}
        </VendorLayout>
    );
}
