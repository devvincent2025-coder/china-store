import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import OrderTableSkeleton from '@/Components/OrderTableSkeleton';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

function orderSummary(order) {
    const items = (order.vendor_orders ?? []).flatMap((vo) => vo.items ?? []);
    const stores = (order.vendor_orders ?? [])
        .map((vo) => (vo.vendor?.store ? t(vo.vendor.store.name) : vo.vendor?.business_name))
        .filter(Boolean);

    return {
        firstItem: items[0],
        quantity: items.reduce((sum, item) => sum + (item.quantity ?? 0), 0),
        stores,
    };
}

export default function Index({ orders }) {
    const { __ } = useTranslation();
    const [loading, setLoading] = useState(false);
    return (
        <StorefrontLayout>
            <Head title={__('My Orders')} />

            <div className="mx-auto max-w-marketplace px-4 py-6 sm:px-6 lg:px-8">
                <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('My Orders')}</h1>

                {loading ? (
                    <OrderTableSkeleton rows={orders.data.length || 6} />
                ) : orders.data.length === 0 ? (
                    <EmptyState
                        title="No orders yet"
                        description="Once you place an order, it'll show up here."
                        actionHref={route('products.index')}
                        actionLabel="Start Shopping"
                    />
                ) : (
                    <>
                        {/* Mobile: card list, same pattern as Vendor/Products/Index.jsx — the
                            five-column table below doesn't fit a phone and clipped its last columns. */}
                        <div className="space-y-3 sm:hidden">
                            {orders.data.map((order) => {
                                const { firstItem, quantity, stores } = orderSummary(order);

                                return (
                                    <Link
                                        key={order.id}
                                        href={route('orders.show', order.order_number)}
                                        className="block rounded-lg border border-neutral-200 bg-white p-3 active:bg-neutral-50"
                                    >
                                        <div className="flex items-start gap-3">
                                            <div className="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-neutral-100">
                                                {firstItem?.product_image_snapshot && (
                                                    <img src={firstItem.product_image_snapshot} alt="" className="h-full w-full object-cover" />
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start justify-between gap-2">
                                                    <p className="truncate text-sm font-medium text-neutral-800">{order.order_number}</p>
                                                    <StatusBadge status={order.payment_status} />
                                                </div>
                                                {firstItem && (
                                                    <p className="mt-0.5 line-clamp-1 text-xs text-neutral-600">
                                                        {t(firstItem.product_title_snapshot)}
                                                    </p>
                                                )}
                                                {stores.length > 0 && (
                                                    <p className="line-clamp-1 text-xs text-neutral-400">{stores.join(', ')}</p>
                                                )}
                                                <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500">
                                                    <span className="font-semibold text-neutral-700">{formatNaira(order.total)}</span>
                                                    {quantity > 0 && <span>{quantity === 1 ? __('1 item') : __(':count items', { count: quantity })}</span>}
                                                    <span>{new Date(order.created_at).toLocaleDateString('en-NG')}</span>
                                                    <span className="ml-auto font-medium text-orange-600">{__('View')} →</span>
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>

                        {/* Tablet/desktop: table */}
                        <div className="hidden overflow-hidden rounded-lg border border-neutral-200 bg-white sm:block">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                                    <tr>
                                        <th className="px-4 py-3">Order #</th>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="px-4 py-3">{__('Total')}</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100">
                                    {orders.data.map((order) => (
                                        <tr key={order.id}>
                                            <td className="px-4 py-3 font-medium text-neutral-800">{order.order_number}</td>
                                            <td className="px-4 py-3 text-neutral-500">
                                                {new Date(order.created_at).toLocaleDateString('en-NG')}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-700">{formatNaira(order.total)}</td>
                                            <td className="px-4 py-3"><StatusBadge status={order.payment_status} /></td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={route('orders.show', order.order_number)}
                                                    className="font-medium text-orange-600 hover:underline"
                                                >
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination
                            paginator={orders}
                            onStart={() => setLoading(true)}
                            onFinish={() => setLoading(false)}
                        />
                    </>
                )}
            </div>
        </StorefrontLayout>
    );
}
