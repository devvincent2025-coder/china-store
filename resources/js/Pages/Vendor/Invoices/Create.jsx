import { Head, useForm } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import InvoiceForm from './InvoiceForm';
import { useTranslation } from '@/hooks/useTranslation';

export default function Create({ vendor }) {
    const { __ } = useTranslation();

    const { data, setData, post, processing, errors } = useForm({
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        items: [{ description: '', quantity: 1, unit_price: '' }],
        discount: '',
        vat_amount: '',
        shipping_amount: '',
        allow_partial_payment: false,
        minimum_partial_amount: '',
        due_date: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('vendor.invoices.store'));
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('New Invoice')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('New Invoice')}</h1>

            <InvoiceForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={submit}
                submitLabel={__('Create Invoice')}
            />
        </VendorLayout>
    );
}
