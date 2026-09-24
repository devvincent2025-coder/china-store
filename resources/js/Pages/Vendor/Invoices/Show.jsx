import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';
import confirm from '@/utils/confirm';

export default function Show({ vendor, invoice, payUrl }) {
    const { __ } = useTranslation();
    const [copied, setCopied] = useState(false);

    const balanceDue = invoice.total - invoice.amount_paid;
    const isEditable = invoice.status === 'draft';
    const canSend = invoice.status !== 'void';
    const canVoid = invoice.status !== 'void' && invoice.amount_paid < invoice.total;

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(payUrl);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard API can be unavailable (e.g. insecure context) — the link is still
            // visible in the box below for the vendor to select and copy manually.
        }
    };

    const send = async () => {
        if (await confirm(__('Send this invoice to :email?', { email: invoice.customer_email }))) {
            router.post(route('vendor.invoices.send', invoice.id));
        }
    };

    const voidInvoice = async () => {
        if (await confirm(__('Void this invoice? The customer will no longer be able to pay it.'), { tone: 'danger' })) {
            router.post(route('vendor.invoices.void', invoice.id));
        }
    };

    const destroy = async () => {
        if (await confirm(__('Delete this draft invoice?'), { tone: 'danger' })) {
            router.delete(route('vendor.invoices.destroy', invoice.id));
        }
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={`${__('Invoice')} ${invoice.invoice_number}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-neutral-900">{invoice.invoice_number}</h1>
                    <div className="mt-1"><StatusBadge status={invoice.status} /></div>
                </div>
                <div className="flex flex-wrap gap-2">
                    {isEditable && (
                        <Link href={route('vendor.invoices.edit', invoice.id)} className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                            {__('Edit')}
                        </Link>
                    )}
                    {canSend && (
                        <button onClick={send} className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">
                            {invoice.status === 'draft' ? __('Send Invoice') : __('Resend Invoice')}
                        </button>
                    )}
                    {canVoid && (
                        <button onClick={voidInvoice} className="rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                            {__('Void')}
                        </button>
                    )}
                    {isEditable && (
                        <button onClick={destroy} className="rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                            {__('Delete')}
                        </button>
                    )}
                </div>
            </div>

            {invoice.status !== 'draft' && (
                <div className="mb-6 rounded-lg border border-neutral-200 bg-white p-4">
                    <h2 className="mb-2 text-sm font-semibold text-neutral-800">{__('Payment Link')}</h2>
                    <div className="flex flex-wrap items-center gap-2">
                        <input readOnly value={payUrl} className="min-w-[240px] flex-1 rounded-md border-neutral-300 bg-neutral-50 text-sm" />
                        <button onClick={copyLink} className="rounded-md bg-neutral-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-neutral-900">
                            {copied ? __('Copied!') : __('Copy')}
                        </button>
                    </div>
                    <p className="mt-2 text-xs text-neutral-500">{__('Share this link with the customer — they can pay it via Korapay without needing an account.')}</p>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-6">
                    <div className="rounded-lg border border-neutral-200 bg-white p-4">
                        <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Line Items')}</h2>
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase text-neutral-500">
                                <tr>
                                    <th className="py-1">{__('Description')}</th>
                                    <th className="py-1">{__('Qty')}</th>
                                    <th className="py-1">{__('Unit Price')}</th>
                                    <th className="py-1 text-right">{__('Total')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {invoice.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="py-2 text-neutral-700">{item.description}</td>
                                        <td className="py-2 text-neutral-600">{item.quantity}</td>
                                        <td className="py-2 text-neutral-600">{formatNaira(item.unit_price)}</td>
                                        <td className="py-2 text-right text-neutral-700">{formatNaira(item.line_total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="mt-4 space-y-1 border-t border-neutral-100 pt-3 text-sm">
                            <div className="flex justify-between"><span className="text-neutral-500">{__('Subtotal')}</span><span>{formatNaira(invoice.subtotal)}</span></div>
                            {invoice.discount > 0 && <div className="flex justify-between"><span className="text-neutral-500">{__('Discount')}</span><span>-{formatNaira(invoice.discount)}</span></div>}
                            {invoice.vat_amount > 0 && <div className="flex justify-between"><span className="text-neutral-500">{__('VAT')}</span><span>{formatNaira(invoice.vat_amount)}</span></div>}
                            {invoice.shipping_amount > 0 && <div className="flex justify-between"><span className="text-neutral-500">{__('Shipping')}</span><span>{formatNaira(invoice.shipping_amount)}</span></div>}
                            <div className="flex justify-between text-base font-semibold text-neutral-900"><span>{__('Total')}</span><span>{formatNaira(invoice.total)}</span></div>
                            <div className="flex justify-between text-neutral-600"><span>{__('Paid')}</span><span>{formatNaira(invoice.amount_paid)}</span></div>
                            <div className="flex justify-between font-semibold text-orange-600"><span>{__('Balance Due')}</span><span>{formatNaira(balanceDue)}</span></div>
                        </div>
                    </div>

                    <div className="rounded-lg border border-neutral-200 bg-white p-4">
                        <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Payment History')}</h2>
                        {invoice.payments.length === 0 ? (
                            <p className="text-sm text-neutral-400">{__('No payments recorded yet.')}</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-neutral-500">
                                    <tr>
                                        <th className="py-1">{__('Date')}</th>
                                        <th className="py-1">{__('Payer')}</th>
                                        <th className="py-1">{__('Amount')}</th>
                                        <th className="py-1">{__('Status')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100">
                                    {invoice.payments.map((payment) => (
                                        <tr key={payment.id}>
                                            <td className="py-2 text-neutral-500">{new Date(payment.created_at).toLocaleDateString('en-NG')}</td>
                                            <td className="py-2 text-neutral-700">{payment.payer_name}</td>
                                            <td className="py-2 text-neutral-700">{formatNaira(payment.amount)}</td>
                                            <td className="py-2"><StatusBadge status={payment.status} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="rounded-lg border border-neutral-200 bg-white p-4">
                        <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Customer')}</h2>
                        <p className="text-sm text-neutral-700">{invoice.customer_name}</p>
                        <p className="text-sm text-neutral-500">{invoice.customer_email}</p>
                        {invoice.customer_phone && <p className="text-sm text-neutral-500">{invoice.customer_phone}</p>}
                    </div>

                    <div className="rounded-lg border border-neutral-200 bg-white p-4">
                        <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Details')}</h2>
                        <dl className="space-y-2 text-sm">
                            {invoice.due_date && (
                                <div className="flex justify-between"><dt className="text-neutral-500">{__('Due Date')}</dt><dd>{invoice.due_date}</dd></div>
                            )}
                            <div className="flex justify-between"><dt className="text-neutral-500">{__('Part Payments')}</dt><dd>{invoice.allow_partial_payment ? __('Allowed') : __('Not allowed')}</dd></div>
                            {invoice.allow_partial_payment && invoice.minimum_partial_amount > 0 && (
                                <div className="flex justify-between"><dt className="text-neutral-500">{__('Minimum Partial')}</dt><dd>{formatNaira(invoice.minimum_partial_amount)}</dd></div>
                            )}
                        </dl>
                        {invoice.notes && (
                            <p className="mt-3 whitespace-pre-line rounded-md bg-neutral-50 p-2 text-xs text-neutral-600">{invoice.notes}</p>
                        )}
                    </div>
                </div>
            </div>
        </VendorLayout>
    );
}
