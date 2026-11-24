import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import StatusBadge from '@/Components/StatusBadge';
import VerticalStepper from '@/Components/VerticalStepper';
import EvidencePhotoUploader from '@/Components/EvidencePhotoUploader';
import PaymentResultModal from '@/Components/PaymentResultModal';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { buildFulfillmentSteps } from '@/utils/orderSteps';
import { formatDeliveryEstimate } from '@/utils/deliveryEstimate';
import { useTranslation } from '@/hooks/useTranslation';
import confirm from '@/utils/confirm';

const REASON_LABELS = {
    damaged: 'Damaged item',
    wrong_item: 'Wrong item',
    not_as_described: 'Not as described',
    missing_item: 'Missing item',
    other: 'Other',
};

function DisputeStatus({ dispute }) {
    const { __ } = useTranslation();
    const overdue = dispute.status === 'open' && !dispute.vendor_responded_at
        && dispute.vendor_response_deadline && new Date(dispute.vendor_response_deadline) < new Date();

    return (
        <div className="space-y-3 rounded-md bg-amber-50 px-3 py-3 text-sm">
            <div>
                <p className="font-semibold text-amber-800">{__('Your report')}: {__(REASON_LABELS[dispute.reason] ?? dispute.reason)}</p>
                <p className="mt-0.5 text-xs text-amber-700">{dispute.description}</p>
                {dispute.evidence_urls?.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-2">
                        {dispute.evidence_urls.map((url, i) => (
                            <a key={i} href={url} target="_blank" rel="noopener noreferrer">
                                <img src={url} alt="" className="h-14 w-14 rounded-md border border-amber-200 object-cover" />
                            </a>
                        ))}
                    </div>
                )}
            </div>

            {dispute.status === 'open' ? (
                dispute.vendor_responded_at ? (
                    <div className="border-t border-amber-100 pt-2">
                        <p className="text-xs font-semibold text-amber-800">{__('Vendor responded')}</p>
                        <p className="mt-0.5 text-xs text-amber-700">{dispute.vendor_response}</p>
                        {dispute.vendor_evidence_urls?.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-2">
                                {dispute.vendor_evidence_urls.map((url, i) => (
                                    <a key={i} href={url} target="_blank" rel="noopener noreferrer">
                                        <img src={url} alt="" className="h-14 w-14 rounded-md border border-amber-200 object-cover" />
                                    </a>
                                ))}
                            </div>
                        )}
                        <p className="mt-2 text-xs text-amber-600">{__('Our team is now reviewing both sides and will make a final decision.')}</p>
                    </div>
                ) : (
                    <p className="border-t border-amber-100 pt-2 text-xs text-amber-700">
                        {overdue
                            ? __('The vendor did not respond in time — our team will review and decide shortly.')
                            : __('The vendor has until :date to respond. Our team will step in either way if needed.', {
                                date: new Date(dispute.vendor_response_deadline).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' }),
                            })}
                    </p>
                )
            ) : (
                <div className="border-t border-amber-100 pt-2">
                    <p className="text-xs font-semibold text-amber-800">
                        {{
                            approved_refund: __('Resolved — you were refunded.'),
                            approved_replace: __('Resolved — the vendor is sending a replacement.'),
                            approved_release: __('Resolved — no refund was issued.'),
                        }[dispute.status] ?? __('Resolved.')}
                    </p>
                    {dispute.resolution_note && <p className="mt-0.5 text-xs text-amber-700">{dispute.resolution_note}</p>}
                </div>
            )}
        </div>
    );
}

function ReviewForm({ orderItem }) {
    const { __ } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        rating: 5,
        comment: '',
    });

    if (orderItem.review) {
        return (
            <p className="mt-1 text-xs text-green-600">
                You rated this {orderItem.review.rating}★{orderItem.review.comment ? ` — "${orderItem.review.comment}"` : ''}
            </p>
        );
    }

    const submit = (e) => {
        e.preventDefault();
        post(route('reviews.store', orderItem.id), {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    if (!open) {
        return (
            <button onClick={() => setOpen(true)} className="mt-1 text-xs font-semibold text-orange-600 hover:underline">
                Leave a review
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="mt-2 space-y-2 rounded-md bg-neutral-50 p-3">
            <div className="flex items-center gap-2">
                <label className="text-xs text-neutral-600">{__('Rating')}</label>
                <select
                    value={data.rating}
                    onChange={(e) => setData('rating', Number(e.target.value))}
                    className="rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                >
                    {[5, 4, 3, 2, 1].map((n) => (
                        <option key={n} value={n}>{n} ★</option>
                    ))}
                </select>
            </div>
            <textarea
                value={data.comment}
                onChange={(e) => setData('comment', e.target.value)}
                placeholder="Share your experience (optional)"
                rows={2}
                className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
            />
            {errors.rating && <p className="text-xs text-red-600">{errors.rating}</p>}
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                >
                    {__('Submit')}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="px-3 py-1.5 text-xs text-neutral-500">
                    {__('Cancel')}
                </button>
            </div>
        </form>
    );
}

/**
 * Live, ticking HH:MM:SS (or D:HH:MM:SS once the window spans more than a day) countdown to
 * `expiresAt` — the return-window deadline, which is itself computed server-side (see
 * Vendor\OrderController::update()) from the admin-configurable `escrow_return_window_days`
 * setting at the moment the vendor marked the order delivered. This component never re-derives
 * that duration itself; it only ticks down the already-correct absolute deadline every second, so
 * whatever the setting was AT DELIVERY TIME is what the buyer sees counting down here.
 */
function ReturnWindowCountdown({ expiresAt }) {
    const { __ } = useTranslation();
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!expiresAt) return undefined;

        const id = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(id);
    }, [expiresAt]);

    if (!expiresAt) return null;

    const remainingMs = new Date(expiresAt).getTime() - now;

    if (remainingMs <= 0) {
        return (
            <p className="mt-1.5 text-xs font-semibold text-neutral-500">
                {__('Your return window has ended — payment will be released to the vendor shortly.')}
            </p>
        );
    }

    const pad = (n) => String(n).padStart(2, '0');
    const totalSeconds = Math.floor(remainingMs / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const display = days > 0
        ? `${days}d ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
        : `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;

    return (
        <p className="mt-1.5 flex items-baseline gap-1.5 text-xs">
            <span className="font-mono text-base font-bold tracking-wide text-orange-600">{display}</span>
            <span className="font-semibold text-neutral-500">
                {__('left to confirm receipt or report a problem')}
            </span>
        </p>
    );
}

function EscrowActions({ vendorOrder }) {
    const { __ } = useTranslation();
    const [showDisputeForm, setShowDisputeForm] = useState(false);
    const [confirmingReceipt, setConfirmingReceipt] = useState(false);
    // Most recent dispute — an open one takes priority, but a resolved dispute still deserves to be
    // shown rather than silently vanishing once closed.
    const dispute = vendorOrder.disputes?.find((d) => d.status === 'open') ?? vendorOrder.disputes?.[vendorOrder.disputes.length - 1];
    const { data, setData, post, processing, errors, reset } = useForm({
        reason: 'damaged',
        description: '',
        evidence: [],
    });

    if (vendorOrder.fulfillment_status !== 'delivered') {
        return null;
    }

    const confirmReceipt = async () => {
        if (await confirm(__('Confirm that you have received this order? This will release payment to the vendor.'))) {
            setConfirmingReceipt(true);
            router.post(route('vendor-orders.confirm-receipt', vendorOrder.id), {}, {
                onFinish: () => setConfirmingReceipt(false),
            });
        }
    };

    const submitDispute = (e) => {
        e.preventDefault();
        post(route('vendor-orders.disputes.store', vendorOrder.id), {
            forceFormData: true,
            onSuccess: () => { setShowDisputeForm(false); reset(); },
        });
    };

    return (
        <div className="border-t border-neutral-100 px-4 py-4">
            <div className="mb-2 flex items-center gap-2">
                <span className="text-xs font-semibold uppercase text-neutral-400">{__('Escrow')}</span>
                <StatusBadge status={vendorOrder.escrow_status} />
            </div>

            {dispute ? (
                <DisputeStatus dispute={dispute} />
            ) : vendorOrder.escrow_status === 'holding' ? (
                <>
                    <div className="mb-2">
                        <p className="text-xs text-neutral-500">
                            {__('Your payment is held securely in escrow until you confirm receipt or the return window ends.')}
                        </p>
                        <ReturnWindowCountdown expiresAt={vendorOrder.return_window_expires_at} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={confirmReceipt}
                            disabled={confirmingReceipt}
                            className="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            {confirmingReceipt ? __('Confirming…') : __('Confirm Receipt')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowDisputeForm((o) => !o)}
                            className="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                        >
                            {__('Report a Problem')}
                        </button>
                    </div>
                    {showDisputeForm && (
                        <form onSubmit={submitDispute} className="mt-3 space-y-2 rounded-md bg-neutral-50 p-3">
                            <select
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                className="w-full rounded-md border-neutral-300 text-sm"
                            >
                                <option value="damaged">{__('Damaged item')}</option>
                                <option value="wrong_item">{__('Wrong item')}</option>
                                <option value="not_as_described">{__('Not as described')}</option>
                                <option value="missing_item">{__('Missing item')}</option>
                                <option value="other">{__('Other')}</option>
                            </select>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={2}
                                placeholder={__('Describe the problem')}
                                className="w-full rounded-md border-neutral-300 text-sm"
                            />
                            {errors.description && <p className="text-xs text-red-600">{errors.description}</p>}
                            <div>
                                <p className="mb-1 text-xs font-medium text-neutral-600">{__('Photos (optional, but they help resolve this faster)')}</p>
                                <EvidencePhotoUploader files={data.evidence} onChange={(files) => setData('evidence', files)} />
                                {errors.evidence && <p className="mt-1 text-xs text-red-600">{errors.evidence}</p>}
                            </div>
                            <p className="text-xs text-neutral-500">
                                {__('If the item is damaged, wrong, or fake, the vendor covers the return and you get a full refund. The vendor has 48–72 hours to respond, then an admin makes a final call.')}
                            </p>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                {__('Submit Report')}
                            </button>
                        </form>
                    )}
                </>
            ) : null}
        </div>
    );
}

export default function Show({ order }) {
    const { __ } = useTranslation();
    const { props } = usePage();
    const [paymentResult, setPaymentResult] = useState(props.flash?.paymentResult ?? null);

    return (
        <StorefrontLayout>
            <Head title={`Order ${order.order_number}`} />

            {paymentResult && (
                <PaymentResultModal
                    status={paymentResult.status}
                    orderNumber={paymentResult.order_number}
                    total={order.total}
                    onClose={() => setPaymentResult(null)}
                />
            )}

            <div className="mx-auto max-w-marketplace px-4 py-6 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-bold text-neutral-900">Order {order.order_number}</h1>
                    <div className="flex items-center gap-3">
                        <a
                            href={route('orders.invoice', order.order_number)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 3v13m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                            </svg>
                            {__('Download Invoice')}
                        </a>
                        <StatusBadge status={order.payment_status} />
                    </div>
                </div>

                <div className="mb-6 rounded-lg border border-neutral-200 bg-white p-4 text-sm text-neutral-600">
                    <p>Placed on {new Date(order.created_at).toLocaleString('en-NG')}</p>
                    {order.shipping_address && (
                        <p className="mt-1">
                            Shipping to: {order.shipping_address.name}, {order.shipping_address.line1}, {order.shipping_address.city}, {order.shipping_address.state}
                        </p>
                    )}
                    <p className="mt-1 font-semibold text-neutral-800">{__('Total')}: {formatNaira(order.total)}</p>
                </div>

                <div className="space-y-6">
                    {/* Eloquent serializes relation keys as snake_case regardless of the PHP
                        method's camelCase name, so `vendorOrders` arrives as `vendor_orders`. */}
                    {order.vendor_orders.map((vendorOrder) => (
                        <div key={vendorOrder.id} className="rounded-lg border border-neutral-200 bg-white">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 px-4 py-3">
                                <span className="text-sm font-semibold text-neutral-800">
                                    {vendorOrder.vendor?.store ? t(vendorOrder.vendor.store.name) : vendorOrder.vendor?.business_name}
                                </span>
                                <div className="flex items-center gap-2">
                                    <StatusBadge status={vendorOrder.fulfillment_status} />
                                </div>
                            </div>

                            {vendorOrder.tracking_number && (
                                <div className="border-b border-neutral-100 px-4 py-2 text-xs text-neutral-500">
                                    {__('Tracking:')} {vendorOrder.tracking_carrier} — {vendorOrder.tracking_number}
                                </div>
                            )}

                            {formatDeliveryEstimate(vendorOrder.vendor?.delivery_days_min, vendorOrder.vendor?.delivery_days_max, __) && (
                                <div className="border-b border-neutral-100 px-4 py-2 text-xs text-neutral-500">
                                    {formatDeliveryEstimate(vendorOrder.vendor.delivery_days_min, vendorOrder.vendor.delivery_days_max, __)}
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
                                            {vendorOrder.fulfillment_status === 'delivered' && (
                                                <ReviewForm orderItem={item} />
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <EscrowActions vendorOrder={vendorOrder} />
                        </div>
                    ))}
                </div>
            </div>
        </StorefrontLayout>
    );
}
