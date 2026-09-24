import { Head } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import StatusBadge from '@/Components/StatusBadge';
import VerticalStepper from '@/Components/VerticalStepper';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { buildFulfillmentSteps } from '@/utils/orderSteps';
import { useTranslation } from '@/hooks/useTranslation';

export default function Show({ order }) {
    const { __ } = useTranslation();

    return (
        <StorefrontLayout>
            <Head title={`${__('Track Order')} ${order.order_number}`} />

            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-bold text-neutral-900">{__('Order')} {order.order_number}</h1>
                    <StatusBadge status={order.payment_status} />
                </div>

                <div className="mb-6 rounded-lg border border-neutral-200 bg-white p-4 text-sm text-neutral-600">
                    <p>{__('Placed on')} {new Date(order.created_at).toLocaleString('en-NG')}</p>
                    {order.shipping_address && (
                        <p className="mt-1">
                            {__('Shipping to')}: {order.shipping_address.name}, {order.shipping_address.line1}, {order.shipping_address.city}, {order.shipping_address.state}
                        </p>
                    )}
                    <p className="mt-1 font-semibold text-neutral-800">{__('Total')}: {formatNaira(order.total)}</p>
                </div>

                <div className="space-y-6">
                    {order.vendor_orders.map((vendorOrder) => (
                        <div key={vendorOrder.id} className="rounded-lg border border-neutral-200 bg-white">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 px-4 py-3">
                                <span className="text-sm font-semibold text-neutral-800">
                                    {vendorOrder.vendor?.store ? t(vendorOrder.vendor.store.name) : vendorOrder.vendor?.business_name}
                                </span>
                                <div className="flex items-center gap-2">
                                    <StatusBadge status={vendorOrder.fulfillment_status} />
                                    <StatusBadge status={vendorOrder.escrow_status} />
                                </div>
                            </div>

                            {vendorOrder.tracking_number && (
                                <div className="border-b border-neutral-100 px-4 py-2 text-xs text-neutral-500">
                                    {__('Tracking')}: {vendorOrder.tracking_carrier} — {vendorOrder.tracking_number}
                                </div>
                            )}

                            <div className="border-b border-neutral-100 px-4 py-4">
                                <VerticalStepper steps={buildFulfillmentSteps(vendorOrder)} />
                            </div>

                            <div className="divide-y divide-neutral-100">
                                {vendorOrder.items.map((item) => (
                                    <div key={item.id} className="flex items-center gap-4 px-4 py-3">
                                        <div className="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-neutral-100">
                                            {item.product_image_snapshot && (
                                                <img src={item.product_image_snapshot} alt="" className="h-full w-full object-cover" />
                                            )}
                                        </div>
                                        <div className="flex-1">
                                            <p className="text-sm text-neutral-800">
                                                {t(item.product_title_snapshot)} × {item.quantity}
                                            </p>
                                            {item.variant_label_snapshot?.length > 0 && (
                                                <p className="text-xs text-neutral-500">
                                                    {item.variant_label_snapshot.map((pair) => `${t(pair.option)}: ${t(pair.value)}`).join(' · ')}
                                                </p>
                                            )}
                                            <p className="text-xs text-neutral-400">{formatNaira(item.line_total)}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </StorefrontLayout>
    );
}
