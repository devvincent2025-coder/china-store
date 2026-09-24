import { Head, Link, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import VendorLayout from '@/Layouts/VendorLayout';
import StatusBadge from '@/Components/StatusBadge';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ tickets, vendor }) {
    const { __ } = useTranslation();
    const { auth } = usePage().props;
    const Layout = auth?.isVendor ? VendorLayout : StorefrontLayout;
    const layoutProps = auth?.isVendor ? { vendor } : {};

    return (
        <Layout {...layoutProps}>
            <Head title={__('Support')} />

            <div className={auth?.isVendor ? '' : 'mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8'}>
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-bold text-neutral-900">{__('Support Tickets')}</h1>
                    <Link
                        href={route('support.create')}
                        className="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700"
                    >
                        + {__('New Ticket')}
                    </Link>
                </div>

                {tickets.data.length === 0 ? (
                    <EmptyState
                        title={__('No support tickets yet')}
                        description={__('Need help with an order, payment, or your account? Open a ticket and our team will respond.')}
                        actionHref={route('support.create')}
                        actionLabel={__('New Ticket')}
                    />
                ) : (
                    <>
                        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                                    <tr>
                                        <th className="px-4 py-2">{__('Subject')}</th>
                                        <th className="px-4 py-2">{__('Category')}</th>
                                        <th className="px-4 py-2">{__('Status')}</th>
                                        <th className="px-4 py-2">{__('Last Updated')}</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100">
                                    {tickets.data.map((ticket) => (
                                        <tr key={ticket.id}>
                                            <td className="px-4 py-3 font-medium text-neutral-800">{ticket.subject}</td>
                                            <td className="px-4 py-3 capitalize text-neutral-600">{ticket.category}</td>
                                            <td className="px-4 py-3"><StatusBadge status={ticket.status} /></td>
                                            <td className="px-4 py-3 text-neutral-500">{new Date(ticket.updated_at).toLocaleString('en-NG')}</td>
                                            <td className="px-4 py-3 text-right">
                                                <Link href={route('support.show', ticket.id)} className="font-medium text-orange-600 hover:underline">
                                                    {__('View')}
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination paginator={tickets} />
                    </>
                )}
            </div>
        </Layout>
    );
}
