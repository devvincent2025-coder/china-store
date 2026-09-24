import { useMemo } from 'react';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Shared line-item + totals form used by both Create.jsx and Edit.jsx. All money fields are edited
 * in naira and converted to kobo server-side (see Vendor\InvoiceController::validated()).
 */
export default function InvoiceForm({ data, setData, errors, processing, onSubmit, submitLabel }) {
    const { __ } = useTranslation();

    const subtotal = useMemo(
        () => data.items.reduce((sum, item) => sum + (Number(item.quantity) || 0) * (Number(item.unit_price) || 0), 0),
        [data.items],
    );

    const total = subtotal - (Number(data.discount) || 0) + (Number(data.vat_amount) || 0) + (Number(data.shipping_amount) || 0);

    const updateItem = (index, field, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const addItem = () => {
        setData('items', [...data.items, { description: '', quantity: 1, unit_price: '' }]);
    };

    const removeItem = (index) => {
        setData('items', data.items.filter((_, i) => i !== index));
    };

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="rounded-lg border border-neutral-200 bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Customer')}</h2>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Name')}</label>
                        <input
                            type="text"
                            value={data.customer_name}
                            onChange={(e) => setData('customer_name', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                        />
                        {errors.customer_name && <p className="mt-1 text-xs text-red-600">{errors.customer_name}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Email')}</label>
                        <input
                            type="email"
                            value={data.customer_email}
                            onChange={(e) => setData('customer_email', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                        />
                        {errors.customer_email && <p className="mt-1 text-xs text-red-600">{errors.customer_email}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Phone')} ({__('optional')})</label>
                        <input
                            type="text"
                            value={data.customer_phone}
                            onChange={(e) => setData('customer_phone', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                        />
                    </div>
                </div>
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-4">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-neutral-800">{__('Line Items')}</h2>
                    <button type="button" onClick={addItem} className="text-sm font-medium text-orange-600 hover:underline">
                        + {__('Add Item')}
                    </button>
                </div>
                {errors.items && <p className="mb-2 text-xs text-red-600">{errors.items}</p>}
                <div className="space-y-2">
                    {data.items.map((item, index) => (
                        <div key={index} className="flex flex-wrap items-start gap-2 rounded-md border border-neutral-100 bg-neutral-50 p-2">
                            <input
                                type="text"
                                value={item.description}
                                onChange={(e) => updateItem(index, 'description', e.target.value)}
                                placeholder={__('Description')}
                                className="min-w-[180px] flex-1 rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                            />
                            <input
                                type="number"
                                min="1"
                                value={item.quantity}
                                onChange={(e) => updateItem(index, 'quantity', e.target.value)}
                                placeholder={__('Qty')}
                                className="w-20 rounded-md border-neutral-300 text-sm"
                            />
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={item.unit_price}
                                onChange={(e) => updateItem(index, 'unit_price', e.target.value)}
                                placeholder={__('Unit Price (₦)')}
                                className="w-32 rounded-md border-neutral-300 text-sm"
                            />
                            <div className="w-28 self-center text-right text-sm text-neutral-600">
                                ₦{((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)).toLocaleString('en-NG', { minimumFractionDigits: 2 })}
                            </div>
                            {data.items.length > 1 && (
                                <button type="button" onClick={() => removeItem(index)} className="self-center text-sm text-red-500 hover:underline">
                                    {__('Remove')}
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Adjustments & Totals')}</h2>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Discount (₦)')}</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.discount}
                            onChange={(e) => setData('discount', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('VAT (₦)')}</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.vat_amount}
                            onChange={(e) => setData('vat_amount', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Shipping (₦)')}</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.shipping_amount}
                            onChange={(e) => setData('shipping_amount', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm"
                        />
                    </div>
                </div>
                <div className="mt-4 flex items-center justify-between border-t border-neutral-100 pt-3 text-sm">
                    <span className="text-neutral-500">{__('Subtotal')}</span>
                    <span className="text-neutral-700">₦{subtotal.toLocaleString('en-NG', { minimumFractionDigits: 2 })}</span>
                </div>
                <div className="mt-1 flex items-center justify-between text-base font-semibold">
                    <span className="text-neutral-800">{__('Total')}</span>
                    <span className="text-orange-600">₦{total.toLocaleString('en-NG', { minimumFractionDigits: 2 })}</span>
                </div>
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-4">
                <h2 className="mb-3 text-sm font-semibold text-neutral-800">{__('Payment Options')}</h2>
                <label className="flex items-center gap-2 text-sm text-neutral-700">
                    <input
                        type="checkbox"
                        checked={data.allow_partial_payment}
                        onChange={(e) => setData('allow_partial_payment', e.target.checked)}
                        className="rounded border-neutral-300 text-orange-600 focus:ring-orange-500"
                    />
                    {__('Allow the customer to pay in part payments (installments)')}
                </label>
                {data.allow_partial_payment && (
                    <div className="mt-3 max-w-xs">
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Minimum partial amount (₦)')} ({__('optional')})</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.minimum_partial_amount}
                            onChange={(e) => setData('minimum_partial_amount', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm"
                        />
                    </div>
                )}
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Due Date')} ({__('optional')})</label>
                        <input
                            type="date"
                            value={data.due_date}
                            onChange={(e) => setData('due_date', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm"
                        />
                    </div>
                </div>
                <div className="mt-4">
                    <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Notes')} ({__('optional')})</label>
                    <textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={3}
                        className="w-full rounded-md border-neutral-300 text-sm"
                    />
                </div>
            </div>

            <button
                type="submit"
                disabled={processing}
                className="rounded-md bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
            >
                {submitLabel}
            </button>
        </form>
    );
}
