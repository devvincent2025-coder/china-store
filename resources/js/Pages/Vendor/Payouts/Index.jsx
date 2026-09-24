import { Head, router } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import Pagination from '@/Components/Pagination';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

const TYPE_STYLES = {
    sale: 'bg-green-100 text-green-700',
    commission: 'bg-neutral-100 text-neutral-600',
    payout: 'bg-blue-100 text-blue-700',
    refund: 'bg-red-100 text-red-700',
    adjustment: 'bg-amber-100 text-amber-700',
};

export default function Index({ vendor, entries, filters, payoutTotals, balance }) {
    const { __ } = useTranslation();

    const applyFilter = (key, value) => {
        router.get(route('vendor.payouts.index'), { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true });
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Payouts')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Payouts & Ledger')}</h1>

            <div className="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div className="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
                    <p className="text-xs text-neutral-500">{__('Escrow Balance')}</p>
                    <p className="mt-1 break-words text-base font-bold text-neutral-900 sm:text-lg">{formatNaira(balance)}</p>
                </div>
                <div className="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
                    <p className="text-xs text-neutral-500">{__('Pending Payouts')}</p>
                    <p className="mt-1 break-words text-base font-bold text-amber-600 sm:text-lg">{formatNaira(payoutTotals.pending)}</p>
                    <p className="text-[11px] text-neutral-400">{payoutTotals.pendingCount} {__('transaction(s)')}</p>
                </div>
                <div className="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
                    <p className="text-xs text-neutral-500">{__('Successful Payouts')}</p>
                    <p className="mt-1 break-words text-base font-bold text-green-600 sm:text-lg">{formatNaira(payoutTotals.success)}</p>
                    <p className="text-[11px] text-neutral-400">{payoutTotals.successCount} {__('transaction(s)')}</p>
                </div>
                <div className="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
                    <p className="text-xs text-neutral-500">{__('Failed Payouts')}</p>
                    <p className="mt-1 break-words text-base font-bold text-red-600 sm:text-lg">{formatNaira(payoutTotals.failed)}</p>
                    <p className="text-[11px] text-neutral-400">{payoutTotals.failedCount} {__('transaction(s)')}</p>
                </div>
            </div>

            <div className="mb-4 flex flex-wrap gap-3">
                <select
                    value={filters.type ?? ''}
                    onChange={(e) => applyFilter('type', e.target.value)}
                    className="rounded-md border-neutral-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                >
                    <option value="">{__('All types')}</option>
                    <option value="sale">{__('Sale')}</option>
                    <option value="commission">{__('Commission')}</option>
                    <option value="payout">{__('Payout')}</option>
                    <option value="refund">{__('Refund')}</option>
                    <option value="adjustment">{__('Adjustment')}</option>
                </select>
                <select
                    value={filters.status ?? ''}
                    onChange={(e) => applyFilter('status', e.target.value)}
                    className="rounded-md border-neutral-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                >
                    <option value="">{__('All statuses')}</option>
                    <option value="pending">{__('Pending')}</option>
                    <option value="success">{__('Success')}</option>
                    <option value="failed">{__('Failed')}</option>
                </select>
            </div>

            <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                <table className="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead className="bg-neutral-50">
                        <tr>
                            <th className="px-4 py-2 text-left font-medium text-neutral-500">{__('Date')}</th>
                            <th className="px-4 py-2 text-left font-medium text-neutral-500">{__('Type')}</th>
                            <th className="px-4 py-2 text-left font-medium text-neutral-500">{__('Description')}</th>
                            <th className="px-4 py-2 text-right font-medium text-neutral-500">{__('Amount')}</th>
                            <th className="px-4 py-2 text-left font-medium text-neutral-500">{__('Status')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100">
                        {entries.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-neutral-400">{__('No transactions yet.')}</td>
                            </tr>
                        )}
                        {entries.data.map((entry) => (
                            <tr key={entry.id}>
                                <td className="px-4 py-2.5 text-neutral-500">{new Date(entry.created_at).toLocaleDateString()}</td>
                                <td className="px-4 py-2.5">
                                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${TYPE_STYLES[entry.type] ?? 'bg-neutral-100 text-neutral-600'}`}>
                                        {entry.type}
                                    </span>
                                </td>
                                <td className="px-4 py-2.5 text-neutral-600">{entry.description ?? '—'}</td>
                                <td className={`px-4 py-2.5 text-right font-semibold ${entry.amount >= 0 ? 'text-green-700' : 'text-red-600'}`}>
                                    {entry.amount >= 0 ? '+' : '-'}{formatNaira(Math.abs(entry.amount))}
                                </td>
                                <td className="px-4 py-2.5"><StatusBadge status={entry.status} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={entries} />
        </VendorLayout>
    );
}
