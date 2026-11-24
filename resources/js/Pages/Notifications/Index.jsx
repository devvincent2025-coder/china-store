import { Head, router, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import VendorLayout from '@/Layouts/VendorLayout';
import EmptyState from '@/Components/EmptyState';
import Pagination from '@/Components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';

export default function Index({ notifications, vendor }) {
    const { __ } = useTranslation();
    const { auth } = usePage().props;
    const Layout = auth?.isVendor ? VendorLayout : StorefrontLayout;
    const layoutProps = auth?.isVendor ? { vendor } : {};

    const open = (notification) => {
        router.post(route('notifications.read', notification.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                if (notification.data.action_url) {
                    router.visit(notification.data.action_url);
                }
            },
        });
    };

    const markAllRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    return (
        <Layout {...layoutProps}>
            <Head title={__('Notifications')} />

            <div className={auth?.isVendor ? '' : 'mx-auto max-w-marketplace px-4 py-6 sm:px-6 lg:px-8'}>
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-bold text-neutral-900">{__('Notifications')}</h1>
                    <button type="button" onClick={markAllRead} className="text-sm font-semibold text-orange-600 hover:underline">
                        {__('Mark all read')}
                    </button>
                </div>

                {notifications.data.length === 0 ? (
                    <EmptyState title={__('No notifications yet')} description={__('Updates about your orders, account, and more will show up here.')} />
                ) : (
                    <>
                        <div className="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                            {notifications.data.map((notification) => (
                                <button
                                    key={notification.id}
                                    type="button"
                                    onClick={() => open(notification)}
                                    className={`flex w-full items-start gap-3 px-4 py-4 text-left hover:bg-neutral-50 ${
                                        !notification.read_at ? 'bg-orange-50/40' : ''
                                    }`}
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-neutral-800">{notification.data.title}</p>
                                        <p className="mt-0.5 text-sm text-neutral-600">{notification.data.body}</p>
                                        <p className="mt-1 text-xs text-neutral-400">{new Date(notification.created_at).toLocaleString('en-NG')}</p>
                                    </div>
                                    {!notification.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-orange-500" />}
                                </button>
                            ))}
                        </div>
                        <Pagination paginator={notifications} />
                    </>
                )}
            </div>
        </Layout>
    );
}
