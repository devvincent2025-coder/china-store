import { Head, useForm } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import TrackOrderIllustration from '@/Components/illustrations/TrackOrderIllustration';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index() {
    const { __ } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        order_number: '',
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('track-order.store'));
    };

    return (
        <StorefrontLayout>
            <Head title={__('Track Your Order')} />

            <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    {/* Illustration — desktop only by design, keeps the mobile page focused on the form */}
                    <div className="hidden lg:block">
                        <TrackOrderIllustration className="mx-auto w-full max-w-md" />
                    </div>

                    <div>
                        <h1 className="mb-2 text-2xl font-bold text-neutral-900 sm:text-3xl">{__('Track Your Order')}</h1>
                        <p className="mb-6 text-sm leading-relaxed text-neutral-500">
                            {__('Enter your order number and the email address you used to place the order.')}
                        </p>

                        <form onSubmit={submit} className="space-y-4 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-7">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Order Number')}</label>
                                <input
                                    type="text"
                                    value={data.order_number}
                                    onChange={(e) => setData('order_number', e.target.value)}
                                    placeholder="CIN-20260921-XXXXXX"
                                    className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                                />
                                {errors.order_number && <p className="mt-1 text-xs text-red-600">{errors.order_number}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-neutral-600">{__('Email')}</label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                                />
                                {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                            >
                                {__('Track Order')}
                            </button>
                        </form>

                        <div className="mt-6 flex items-start gap-2 rounded-lg bg-orange-50 px-4 py-3 text-xs text-orange-700">
                            <svg className="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p>{__('Your order number was emailed to you when you checked out — no account or login needed to track it here.')}</p>
                        </div>
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
