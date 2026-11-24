import { Head, useForm, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

export default function Show({ ticket, vendor }) {
    const { __ } = useTranslation();
    const { auth } = usePage().props;
    const Layout = auth?.isVendor ? VendorLayout : StorefrontLayout;
    const layoutProps = auth?.isVendor ? { vendor } : {};

    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('support.reply', ticket.id), { onSuccess: () => reset('body') });
    };

    return (
        <Layout {...layoutProps}>
            <Head title={ticket.subject} />

            <div className={auth?.isVendor ? 'max-w-2xl' : 'mx-auto max-w-marketplace px-4 py-6 sm:px-6 lg:px-8'}>
                <div className="mb-6 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-bold text-neutral-900">{ticket.subject}</h1>
                        <p className="text-xs capitalize text-neutral-500">{ticket.category} · {ticket.priority} {__('priority')}</p>
                    </div>
                    <StatusBadge status={ticket.status} />
                </div>

                <div className="mb-4 space-y-4">
                    {ticket.messages.map((message) => {
                        return (
                            <div
                                key={message.id}
                                className={`max-w-[85%] rounded-lg border p-4 ${
                                    message.user_id === auth.user.id
                                        ? 'ml-auto border-orange-200 bg-orange-50'
                                        : 'border-neutral-200 bg-white'
                                }`}
                            >
                                <p className="mb-1 text-xs font-semibold text-neutral-500">
                                    {message.user_id === auth.user.id ? __('You') : `${message.user.name} (${__('Support Team')})`}
                                </p>
                                <p className="whitespace-pre-line text-sm text-neutral-800">{message.body}</p>
                                <p className="mt-1 text-[11px] text-neutral-400">{new Date(message.created_at).toLocaleString('en-NG')}</p>
                            </div>
                        );
                    })}
                </div>

                {ticket.status !== 'closed' && (
                    <form onSubmit={submit} className="rounded-lg border border-neutral-200 bg-white p-4">
                        <textarea
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            rows={3}
                            placeholder={__('Write a reply…')}
                            className="w-full rounded-md border-neutral-300 text-sm focus:border-orange-500 focus:ring-orange-500"
                        />
                        {errors.body && <p className="mt-1 text-xs text-red-600">{errors.body}</p>}
                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-2 rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            {__('Send Reply')}
                        </button>
                    </form>
                )}
            </div>
        </Layout>
    );
}
