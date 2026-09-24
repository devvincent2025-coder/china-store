import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from '@/hooks/useTranslation';
import { getPushSubscriptionStatus, isPushSupported, subscribeToPush, unsubscribeFromPush } from '@/utils/pushNotifications';

const ICONS = {
    bell: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    ),
    order: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
    ),
    money: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-12a9 9 0 100 18 9 9 0 000-18z" />
    ),
    warning: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
    ),
    'check-circle': (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    ),
    package: (
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
    ),
};

function timeAgo(dateString) {
    const seconds = Math.floor((Date.now() - new Date(dateString).getTime()) / 1000);
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(dateString).toLocaleDateString('en-NG');
}

/**
 * In-app notification bell shared by the storefront and vendor layouts — polls the lightweight
 * /notifications/unread endpoint rather than relying on a websocket, since a 30s delay is entirely
 * acceptable for "you have a new order" style alerts and needs no extra infrastructure.
 */
export default function NotificationBell() {
    const { __ } = useTranslation();
    const [open, setOpen] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const [recent, setRecent] = useState([]);
    const [pushStatus, setPushStatus] = useState('unsupported');
    const [pushBusy, setPushBusy] = useState(false);
    const containerRef = useRef(null);

    const fetchUnread = () => {
        fetch(route('notifications.unread'), { headers: { Accept: 'application/json' } })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (!data) return;
                setUnreadCount(data.unread_count);
                setRecent(data.recent);
            })
            .catch(() => {});
    };

    useEffect(() => {
        fetchUnread();
        const interval = setInterval(fetchUnread, 30000);

        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        if (isPushSupported()) {
            getPushSubscriptionStatus().then(setPushStatus);
        }
    }, []);

    const togglePush = async () => {
        setPushBusy(true);
        try {
            if (pushStatus === 'subscribed') {
                await unsubscribeFromPush();
                setPushStatus('unsubscribed');
            } else {
                await subscribeToPush();
                setPushStatus('subscribed');
            }
        } catch {
            setPushStatus(await getPushSubscriptionStatus());
        } finally {
            setPushBusy(false);
        }
    };

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const openNotification = (notification) => {
        setOpen(false);
        router.post(route('notifications.read', notification.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                if (notification.data.action_url) {
                    router.visit(notification.data.action_url);
                }
            },
        });
    };

    const markAllRead = (e) => {
        e.stopPropagation();
        router.post(route('notifications.read-all'), {}, {
            preserveScroll: true,
            onSuccess: fetchUnread,
        });
    };

    return (
        <div className="relative" ref={containerRef}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="relative rounded-full p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                aria-label={__('Notifications')}
            >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    {ICONS.bell}
                </svg>
                {unreadCount > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                // On phones the bell sits mid-bar, so a w-80 panel anchored to its right edge ran off
                // the left of the screen. Below sm it's viewport-fixed and inset from both edges
                // instead; top stays auto, so it still opens directly under the bell.
                <div className="fixed inset-x-2 z-50 mt-2 rounded-lg border border-neutral-200 bg-white shadow-lg sm:absolute sm:inset-x-auto sm:right-0 sm:w-80">
                    <div className="flex items-center justify-between border-b border-neutral-100 px-4 py-2.5">
                        <span className="text-sm font-semibold text-neutral-800">{__('Notifications')}</span>
                        {unreadCount > 0 && (
                            <button type="button" onClick={markAllRead} className="text-xs font-medium text-orange-600 hover:underline">
                                {__('Mark all read')}
                            </button>
                        )}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto sm:max-h-96">
                        {recent.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-neutral-400">{__('No notifications yet.')}</p>
                        ) : (
                            recent.map((notification) => (
                                <button
                                    key={notification.id}
                                    type="button"
                                    onClick={() => openNotification(notification)}
                                    className={`flex w-full items-start gap-3 border-b border-neutral-50 px-4 py-3 text-left last:border-0 hover:bg-neutral-50 ${
                                        !notification.read_at ? 'bg-orange-50/40' : ''
                                    }`}
                                >
                                    <svg className="mt-0.5 h-5 w-5 shrink-0 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        {ICONS[notification.data.icon] ?? ICONS.bell}
                                    </svg>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-neutral-800">{notification.data.title}</p>
                                        <p className="line-clamp-2 text-xs text-neutral-500">{notification.data.body}</p>
                                        <p className="mt-0.5 text-[11px] text-neutral-400">{timeAgo(notification.created_at)}</p>
                                    </div>
                                    {!notification.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-orange-500" />}
                                </button>
                            ))
                        )}
                    </div>

                    {pushStatus !== 'unsupported' && pushStatus !== 'denied' && (
                        <button
                            type="button"
                            onClick={togglePush}
                            disabled={pushBusy}
                            className="flex w-full items-center justify-center gap-1.5 border-t border-neutral-100 px-4 py-2 text-xs font-medium text-neutral-500 hover:bg-neutral-50 disabled:opacity-50"
                        >
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                {ICONS.bell}
                            </svg>
                            {pushStatus === 'subscribed' ? __('Push notifications on — tap to disable') : __('Enable push notifications')}
                        </button>
                    )}

                    <Link
                        href={route('notifications.index')}
                        onClick={() => setOpen(false)}
                        className="block border-t border-neutral-100 px-4 py-2.5 text-center text-xs font-semibold text-orange-600 hover:bg-neutral-50"
                    >
                        {__('View all notifications')}
                    </Link>
                </div>
            )}
        </div>
    );
}
