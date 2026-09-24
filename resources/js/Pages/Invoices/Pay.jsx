import { Head, useForm } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import StatusBadge from '@/Components/StatusBadge';
import { formatNaira, koboToNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

export default function Pay({ invoice }) {
    const { __ } = useTranslation();
    const isVoid = invoice.status === 'void';
    const isPaid = invoice.balance_due <= 0;

    const minAmount = invoice.allow_partial_payment
        ? Math.min(koboToNaira(invoice.minimum_partial_amount || 0) || 0.01, koboToNaira(invoice.balance_due))
        : koboToNaira(invoice.balance_due);

    const { data, setData, post, processing, errors } = useForm({
        amount: koboToNaira(invoice.balance_due),
        payer_name: invoice.customer_name ?? '',
        payer_email: invoice.customer_email ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('invoices.pay.store', invoice.public_token));
    };

    return (
        <div className="min-h-screen bg-neutral-50 py-10">
            <Head title={`${__('Pay Invoice')} ${invoice.invoice_number}`} />

            <div className="mx-auto max-w-2xl px-4">
                <div className="mb-6 flex justify-center">
                    <ApplicationLogo className="h-14 w-auto" />
                </div>

                <div className="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h1 className="text-lg font-bold text-neutral-900">{invoice.invoice_number}</h1>
                            <p className="text-sm text-neutral-500">{invoice.vendor.business_name}</p>
                        </div>
                        <StatusBadge status={invoice.status} />
                    </div>

                    <div className="mb-6 divide-y divide-neutral-100 rounded-md border border-neutral-100">
                        {invoice.items.map((item) => (
                            <div key={item.id} className="flex items-center justify-between px-3 py-2 text-sm">
                                <div>
                                    <p className="text-neutral-700">{item.description}</p>
                                    <p className="text-xs text-neutral-400">{item.quantity} × {formatNaira(item.unit_price)}</p>
                                </div>
                                <p className="font-medium text-neutral-700">{formatNaira(item.line_total)}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mb-6 space-y-1 text-sm">
                        <div className="flex justify-between"><span className="text-neutral-500">{__('Subtotal')}</span><span>{formatNaira(invoice.subtotal)}</span></div>
                        {invoice.discount > 0 && <div className="flex justify-between"><span className="text-neutral-500">{__('Discount')}</span><span>-{formatNaira(invoice.discount)}</span></div>}
                        {invoice.vat_amount > 0 && <div className="flex justify-between"><span className="text-neutral-500">{__('VAT')}</span><span>{formatNaira(invoice.vat_amount)}</span></div>}
                        {invoice.shipping_amount > 0 && <div className="flex justify-between"><span className="text-neutral-500">{__('Shipping')}</span><span>{formatNaira(invoice.shipping_amount)}</span></div>}
                        <div className="flex justify-between text-base font-semibold text-neutral-900"><span>{__('Total')}</span><span>{formatNaira(invoice.total)}</span></div>
                        {invoice.amount_paid > 0 && (
                            <div className="flex justify-between text-neutral-600"><span>{__('Paid so far')}</span><span>{formatNaira(invoice.amount_paid)}</span></div>
                        )}
                        <div className="flex justify-between font-semibold text-orange-600"><span>{__('Balance Due')}</span><span>{formatNaira(invoice.balance_due)}</span></div>
                    </div>

                    {invoice.due_date && (
                        <p className="mb-4 text-xs text-neutral-500">{__('Due')}: {invoice.due_date}</p>
                    )}

                    {isVoid && (
                        <div className="rounded-md bg-neutral-100 p-4 text-center text-sm text-neutral-600">
                            {__('This invoice has been cancelled by the vendor.')}
                        </div>
                    )}

                    {!isVoid && isPaid && (
                        <div className="rounded-md bg-green-50 p-4 text-center text-sm font-semibold text-green-700">
                            {__('This invoice has been paid in full. Thank you!')}
                        </div>
                    )}

                    {!isVoid && !isPaid && (
                        <form onSubmit={submit} className="space-y-4 border-t border-neutral-100 pt-4">
                            {invoice.allow_partial_payment && (
                                <p className="text-xs text-neutral-500">
                                    {__('This vendor accepts part payments. Enter any amount between :min and the balance due.', {
                                        min: formatNaira(Math.round(minAmount * 100)),
                                    })}
                                </p>
                            )}
                            <div>
                                <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Amount to Pay (₦)')}</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min={minAmount}
                                    max={koboToNaira(invoice.balance_due)}
                                    value={data.amount}
                                    disabled={!invoice.allow_partial_payment}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    className="w-full rounded-md border-neutral-300 text-sm disabled:bg-neutral-50"
                                />
                                {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Your Name')}</label>
                                    <input
                                        type="text"
                                        value={data.payer_name}
                                        onChange={(e) => setData('payer_name', e.target.value)}
                                        className="w-full rounded-md border-neutral-300 text-sm"
                                    />
                                    {errors.payer_name && <p className="mt-1 text-xs text-red-600">{errors.payer_name}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Your Email')}</label>
                                    <input
                                        type="email"
                                        value={data.payer_email}
                                        onChange={(e) => setData('payer_email', e.target.value)}
                                        className="w-full rounded-md border-neutral-300 text-sm"
                                    />
                                    {errors.payer_email && <p className="mt-1 text-xs text-red-600">{errors.payer_email}</p>}
                                </div>
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-orange-600 px-5 py-3 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                            >
                                {__('Pay with Korapay')}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}
