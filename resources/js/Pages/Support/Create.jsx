import { Head, useForm, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import VendorLayout from '@/Layouts/VendorLayout';
import { useTranslation } from '@/hooks/useTranslation';

export default function Create({ vendor }) {
    const { __ } = useTranslation();
    const { auth } = usePage().props;
    const Layout = auth?.isVendor ? VendorLayout : StorefrontLayout;
    const layoutProps = auth?.isVendor ? { vendor } : {};

    const { data, setData, post, processing, errors } = useForm({
        subject: '',
        category: 'other',
        priority: 'normal',
        body: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('support.store'));
    };

    return (
        <Layout {...layoutProps}>
            <Head title={__('New Support Ticket')} />

            <div className={auth?.isVendor ? 'max-w-xl' : 'mx-auto max-w-marketplace px-4 py-6 sm:px-6 lg:px-8'}>
                <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('New Support Ticket')}</h1>

                <form onSubmit={submit} className="space-y-4 rounded-lg border border-neutral-200 bg-white p-5">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Subject')}</label>
                        <input
                            type="text"
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                            className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                        />
                        {errors.subject && <p className="mt-1 text-xs text-red-600">{errors.subject}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Category')}</label>
                            <select
                                value={data.category}
                                onChange={(e) => setData('category', e.target.value)}
                                className="w-full rounded-md border-neutral-300 text-sm"
                            >
                                <option value="order">{__('Order')}</option>
                                <option value="payment">{__('Payment')}</option>
                                <option value="product">{__('Product')}</option>
                                <option value="account">{__('Account')}</option>
                                <option value="other">{__('Other')}</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Priority')}</label>
                            <select
                                value={data.priority}
                                onChange={(e) => setData('priority', e.target.value)}
                                className="w-full rounded-md border-neutral-300 text-sm"
                            >
                                <option value="low">{__('Low')}</option>
                                <option value="normal">{__('Normal')}</option>
                                <option value="high">{__('High')}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-600">{__('How can we help?')}</label>
                        <textarea
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            rows={6}
                            className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                        />
                        {errors.body && <p className="mt-1 text-xs text-red-600">{errors.body}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                    >
                        {__('Submit Ticket')}
                    </button>
                </form>
            </div>
        </Layout>
    );
}
