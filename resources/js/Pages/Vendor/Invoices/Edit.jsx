import { Head, useForm } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import InvoiceForm from './InvoiceForm';
import { koboToNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

export default function Edit({ vendor, invoice }) {
    const { __ } = useTranslation();

    const { data, setData, put, processing, errors } = useForm({
        customer_name: invoice.customer_name,
        customer_email: invoice.customer_email,
        customer_phone: invoice.customer_phone ?? '',
        items: invoice.items.map((item) => ({
            description: item.description,
            quantity: item.quantity,
            unit_price: koboToNaira(item.unit_price),
        })),
        discount: koboToNaira(invoice.discount) || '',
        vat_amount: koboToNaira(invoice.vat_amount) || '',
        shipping_amount: koboToNaira(invoice.shipping_amount) || '',
        allow_partial_payment: invoice.allow_partial_payment,
        minimum_partial_amount: invoice.minimum_partial_amount ? koboToNaira(invoice.minimum_partial_amount) : '',
        due_date: invoice.due_date ?? '',
        notes: invoice.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('vendor.invoices.update', invoice.id));
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Edit Invoice')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Edit Invoice')} — {invoice.invoice_number}</h1>

            <InvoiceForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                submitLabel={__('Save Changes')}
            />
        </VendorLayout>
    );
}
