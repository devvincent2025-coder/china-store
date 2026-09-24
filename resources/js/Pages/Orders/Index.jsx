import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import OrderTableSkeleton from '@/Components/OrderTableSkeleton';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ orders }) {
    const { __ } = useTranslation();
    const [loading, setLoading] = useState(false);
    return (
        <StorefrontLayout>
            <Head title={__('My Orders')} />

            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
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
                        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
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
