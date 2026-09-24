import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import VerticalStepper from '@/Components/VerticalStepper';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import EvidencePhotoUploader from '@/Components/EvidencePhotoUploader';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { buildFulfillmentSteps } from '@/utils/orderSteps';
import { useTranslation } from '@/hooks/useTranslation';

const REASON_LABELS = {
    damaged: 'Damaged item',
    wrong_item: 'Wrong item',
    not_as_described: 'Not as described',
    missing_item: 'Missing item',
    other: 'Other',
};

/**
 * A buyer's open dispute, from the vendor's side — shows the buyer's report and evidence, and,
 * while nothing has been sent yet, lets the vendor respond with their own side within the 48-72h
 * window (see VendorOrderDispute::RESPONSE_WINDOW_HOURS) before an admin steps in regardless.
 */
function DisputeCard({ vendorOrder, dispute }) {
    const { __ } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        vendor_response: '',
        vendor_evidence: [],
    });

    const overdue = !dispute.vendor_responded_at
        && dispute.vendor_response_deadline && new Date(dispute.vendor_response_deadline) < new Date();

    const submit = (e) => {
        e.preventDefault();
        post(route('vendor.orders.disputes.respond', [vendorOrder.id, dispute.id]), { forceFormData: true });
    };

    return (
        <div className="rounded-md bg-amber-50 px-3 py-3 text-xs">
            <p className="font-semibold text-amber-800">
                {__('Buyer reported')}: {__(REASON_LABELS[dispute.reason] ?? dispute.reason)}
            </p>
            <p className="mt-0.5 text-amber-700">{dispute.description}</p>
            {dispute.evidence_urls?.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                    {dispute.evidence_urls.map((url, i) => (
                        <a key={i} href={url} target="_blank" rel="noopener noreferrer">
                            <img src={url} alt="" className="h-14 w-14 rounded-md border border-amber-200 object-cover" />
                        </a>
                    ))}
                </div>
            )}

            {dispute.vendor_responded_at ? (
                <p className="mt-2 border-t border-amber-100 pt-2 text-amber-700">
                    {__('You responded on :date. Our team is reviewing both sides.', {
                        date: new Date(dispute.vendor_responded_at).toLocaleDateString('en-NG'),
                    })}
                </p>
            ) : (
                <form onSubmit={submit} className="mt-2 space-y-2 border-t border-amber-100 pt-2">
                    <p className={`font-medium ${overdue ? 'text-red-600' : 'text-amber-700'}`}>
                        {overdue
                            ? __('Your response window has passed — respond as soon as possible, but an admin may resolve this without waiting further.')
                            : __('Respond by :date with your side and any evidence (photos, packaging, delivery confirmation).', {
                                date: new Date(dispute.vendor_response_deadline).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' }),
                            })}
                    </p>
                    <textarea
                        value={data.vendor_response}
                        onChange={(e) => setData('vendor_response', e.target.value)}
                        rows={3}
                        placeholder={__('Explain your side of what happened')}
                        className="w-full rounded-md border-neutral-300 text-xs"
                    />
                    {errors.vendor_response && <p className="text-red-600">{errors.vendor_response}</p>}
                    <EvidencePhotoUploader files={data.vendor_evidence} onChange={(files) => setData('vendor_evidence', files)} />
                    {errors.vendor_evidence && <p className="text-red-600">{errors.vendor_evidence}</p>}
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-amber-600 px-3 py-1.5 font-semibold text-white hover:bg-amber-700 disabled:opacity-50"
                    >
                        {__('Send Response')}
                    </button>
                </form>
            )}
        </div>
    );
}

export default function Show({ vendorOrder, vendor }) {
    const { __ } = useTranslation();
    const { data, setData, patch, processing, errors, reset } = useForm({
        fulfillment_status: vendorOrder.fulfillment_status,
        tracking_number: vendorOrder.tracking_number ?? '',
        tracking_carrier: vendorOrder.tracking_carrier ?? '',
        note: '',
    });
    const openDispute = vendorOrder.disputes?.find((d) => d.status === 'open');
    // Once delivered, tracking/status are frozen — the buyer's own "Confirm Receipt"/dispute clock
    // has already started against this delivered_at timestamp (see VendorOrderStatusHistory), so a
    // vendor changing the status or tracking details afterwards could no longer mean anything
    // consistent to the buyer.
    const isLocked = vendorOrder.fulfillment_status === 'delivered';

    const steps = buildFulfillmentSteps(vendorOrder);

    const submit = (e) => {
        e.preventDefault();
        patch(route('vendor.orders.update', vendorOrder.id), {
            onSuccess: () => setData('note', ''),
        });
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={`Order ${vendorOrder.order?.order_number}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                <h1 className="text-xl font-bold text-neutral-900">{__('Order')} #{vendorOrder.order?.order_number}</h1>
                <StatusBadge status={vendorOrder.fulfillment_status} />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-6">
                    <div className="rounded-lg border border-neutral-200 bg-white p-5">
                        <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Buyer')}</h2>
                        <p className="text-sm text-neutral-600">{vendorOrder.order?.user?.name}</p>
                        <p className="text-sm text-neutral-400">{vendorOrder.order?.user?.email}</p>
                        {vendorOrder.order?.shipping_address && (
                            <div className="mt-3 border-t border-neutral-100 pt-3 text-sm text-neutral-600">
                                <p className="mb-1 text-xs font-semibold uppercase text-neutral-400">{__('Shipping Address')}</p>
                                <p>{vendorOrder.order.shipping_address.name} — {vendorOrder.order.shipping_address.phone}</p>
                                <p>{vendorOrder.order.shipping_address.line1}, {vendorOrder.order.shipping_address.city}, {vendorOrder.order.shipping_address.state}</p>
                            </div>
                        )}
                    </div>

                    <div className="rounded-lg border border-neutral-200 bg-white">
                        <h2 className="border-b border-neutral-100 px-5 py-3 text-sm font-semibold text-neutral-800">{__('Items')}</h2>
                        <div className="divide-y divide-neutral-100">
                            {vendorOrder.items.map((item) => (
                                <div key={item.id} className="flex items-center gap-4 px-5 py-3">
                                    <div className="h-14 w-14 shrink-0 overflow-hidden rounded-md bg-neutral-100">
                                        {item.product_image_snapshot && (
                                            <img src={item.product_image_snapshot} alt="" className="h-full w-full object-cover" />
                                        )}
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-sm text-neutral-800">{t(item.product_title_snapshot)}</p>
                                        {item.variant_label_snapshot?.length > 0 && (
                                            <p className="text-xs text-neutral-500">
                                                {item.variant_label_snapshot.map((pair) => `${t(pair.option)}: ${t(pair.value)}`).join(' · ')}
                                            </p>
                                        )}
                                        <p className="mt-0.5 text-xs text-neutral-400">
                                            {item.quantity} × {formatNaira(item.unit_price)}
                                        </p>
                                    </div>
                                    <span className="text-sm font-medium text-neutral-700">{formatNaira(item.line_total)}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-lg border border-neutral-200 bg-white p-5">
                        <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Order Breakdown')}</h2>
                        <div className="space-y-1.5 text-sm">
                            <div className="flex justify-between"><span className="text-neutral-500">{__('Subtotal')}</span><span>{formatNaira(vendorOrder.subtotal)}</span></div>
                            {vendorOrder.discount > 0 && (
                                <div className="flex justify-between text-green-700">
                                    <span>{__('Discount')}{vendorOrder.coupon ? ` (${vendorOrder.coupon.code})` : ''}</span>
                                    <span>-{formatNaira(vendorOrder.discount)}</span>
                                </div>
                            )}
                            {vendorOrder.vat_amount > 0 && (
                                <div className="flex justify-between"><span className="text-neutral-500">{__('VAT')}</span><span>{formatNaira(vendorOrder.vat_amount)}</span></div>
                            )}
                            {vendorOrder.shipping_amount > 0 && (
                                <div className="flex justify-between"><span className="text-neutral-500">{__('Shipping')}</span><span>{formatNaira(vendorOrder.shipping_amount)}</span></div>
                            )}
                            <div className="flex justify-between border-t border-neutral-100 pt-1.5 text-base font-semibold text-neutral-900">
                                <span>{__('Total (paid by buyer)')}</span><span>{formatNaira(vendorOrder.total)}</span>
                            </div>
                            <div className="flex justify-between text-neutral-500">
                                <span>{__('Platform Commission')}{vendorOrder.commission_rate != null ? ` (${Number(vendorOrder.commission_rate)}%)` : ''}</span>
                                <span>-{formatNaira(vendorOrder.commission_amount)}</span>
                            </div>
                            <div className="flex justify-between font-semibold text-orange-600">
                                <span>{__('Your Payout')}</span><span>{formatNaira(vendorOrder.payout_amount)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="rounded-lg border border-neutral-200 bg-white p-5">
                        <div className="mb-1 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-neutral-800">{__('Escrow')}</h2>
                            <StatusBadge status={vendorOrder.escrow_status} />
                        </div>
                        <p className="text-xs text-neutral-500">
                            {vendorOrder.escrow_status === 'holding' && (vendorOrder.return_window_expires_at
                                ? `${__('Released automatically on')} ${new Date(vendorOrder.return_window_expires_at).toLocaleDateString('en-NG')}, sooner if the buyer confirms receipt.`
                                : __('Held until the buyer confirms delivery or their return window ends.'))}
                            {vendorOrder.escrow_status === 'releasing' && __('Payout has been initiated and is processing.')}
                            {vendorOrder.escrow_status === 'released' && __('Payout has been sent to your bank account.')}
                            {vendorOrder.escrow_status === 'refund_pending' && __('A refund to the buyer is processing.')}
                            {vendorOrder.escrow_status === 'refunded' && __('This order was refunded to the buyer.')}
                        </p>
                        {openDispute && (
                            <div className="mt-2">
                                <DisputeCard vendorOrder={vendorOrder} dispute={openDispute} />
                            </div>
                        )}
                    </div>

                    <div className="rounded-lg border border-neutral-200 bg-white p-5">
                        <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Tracking')}</h2>
                        <VerticalStepper steps={steps} />
                    </div>

                    <form onSubmit={submit} className="rounded-lg border border-neutral-200 bg-white p-5">
                        <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Update Fulfillment')}</h2>
                        {isLocked && (
                            <p className="mb-4 rounded-md bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                                {__('This order has been delivered and can no longer be updated.')}
                            </p>
                        )}
                        <div className="space-y-4">
                            <div>
                                <InputLabel htmlFor="fulfillment_status" value={__('Status')} />
                                <select
                                    id="fulfillment_status"
                                    value={data.fulfillment_status}
                                    onChange={(e) => setData('fulfillment_status', e.target.value)}
                                    disabled={isLocked}
                                    className="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 disabled:bg-neutral-50 disabled:text-neutral-400"
                                >
                                    <option value="processing">{__('Processing')}</option>
                                    <option value="shipped">{__('Shipped')}</option>
                                    <option value="delivered">{__('Delivered')}</option>
                                    <option value="cancelled">{__('Cancelled')}</option>
                                </select>
                                <InputError message={errors.fulfillment_status} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="tracking_number" value={__('Tracking Number')} />
                                <TextInput
                                    id="tracking_number"
                                    className="mt-1 block w-full disabled:bg-neutral-50 disabled:text-neutral-400"
                                    value={data.tracking_number}
                                    onChange={(e) => setData('tracking_number', e.target.value)}
                                    disabled={isLocked}
                                />
                                <InputError message={errors.tracking_number} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="tracking_carrier" value={__('Tracking Carrier')} />
                                <TextInput
                                    id="tracking_carrier"
                                    className="mt-1 block w-full disabled:bg-neutral-50 disabled:text-neutral-400"
                                    value={data.tracking_carrier}
                                    onChange={(e) => setData('tracking_carrier', e.target.value)}
                                    disabled={isLocked}
                                />
                                <InputError message={errors.tracking_carrier} className="mt-1" />
                            </div>
                            {!isLocked && (
                                <div>
                                    <InputLabel htmlFor="note" value={__('Note (optional)')} />
                                    <textarea
                                        id="note"
                                        rows={2}
                                        value={data.note}
                                        onChange={(e) => setData('note', e.target.value)}
                                        placeholder={__('Visible to the buyer on this status update, e.g. a delay reason')}
                                        className="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                    />
                                    <InputError message={errors.note} className="mt-1" />
                                </div>
                            )}
                            {!isLocked && (
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-md bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                                >
                                    {__('Save')}
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>
        </VendorLayout>
    );
}
