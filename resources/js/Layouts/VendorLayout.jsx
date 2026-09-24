import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StatusBadge from '@/Components/StatusBadge';
import NotificationBell from '@/Components/NotificationBell';
import { useTranslation } from '@/hooks/useTranslation';

// Each nav item gets its own accent color (a small colorful icon "chip") rather than one flat
// palette for the whole sidebar — makes the vendor dashboard easier to scan at a glance.
const NAV_ITEMS = [
    {
        label: 'Dashboard', route: 'vendor.dashboard', color: 'text-orange-600 bg-orange-50',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        label: 'Products', route: 'vendor.products.index', color: 'text-blue-600 bg-blue-50',
        icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    },
    {
        label: 'Coupons', route: 'vendor.coupons.index', color: 'text-pink-600 bg-pink-50',
        icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z',
    },
    {
        label: 'Banners', route: 'vendor.banners.index', color: 'text-purple-600 bg-purple-50',
        icon: 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 3h18v18H3V3z',
    },
    {
        label: 'Orders', route: 'vendor.orders.index', color: 'text-green-600 bg-green-50',
        icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
    },
    {
        label: 'Invoices', route: 'vendor.invoices.index', color: 'text-teal-600 bg-teal-50',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        label: 'Payouts', route: 'vendor.payouts.index', color: 'text-emerald-600 bg-emerald-50',
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-12a9 9 0 100 18 9 9 0 000-18z',
    },
    {
        label: 'Boost (Ads)', route: 'vendor.ads.index', color: 'text-rose-600 bg-rose-50',
        icon: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
    },
    {
        label: 'Profile', route: 'vendor.profile.edit', color: 'text-indigo-600 bg-indigo-50',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
    {
        label: 'Support', route: 'support.index', color: 'text-amber-600 bg-amber-50',
        icon: 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M5.636 5.636l3.536 3.536m0 5.656l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z',
    },
    {
        label: 'Settings', route: 'vendor.settings.edit', color: 'text-neutral-600 bg-neutral-100',
        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    },
];

const LANGUAGES = [
    { code: 'en', label: 'EN' },
    { code: 'zh', label: '中文' },
    { code: 'fr', label: 'FR' },
];

export default function VendorLayout({ children, vendor = null }) {
    const { auth, locale = 'en' } = usePage().props;
    const user = auth?.user;
    const storeSlug = auth?.vendorStoreSlug;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const { __ } = useTranslation();

    const langHref = (code) => {
        if (typeof window === 'undefined') return `?lang=${code}`;
        const url = new URL(window.location.href);
        url.searchParams.set('lang', code);
        return `${url.pathname}?${url.searchParams.toString()}`;
    };

    const NavLinks = () => (
        <nav className="flex flex-col gap-1">
            {NAV_ITEMS.map((item) => {
                const active = route().current(`${item.route}*`);

                return (
                    <Link
                        key={item.route}
                        href={route(item.route)}
                        onClick={() => setMobileNavOpen(false)}
                        className={`flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium ${
                            active ? 'bg-orange-600 text-white' : 'text-neutral-600 hover:bg-orange-50 hover:text-orange-600'
                        }`}
                    >
                        <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-md ${active ? 'bg-white/20 text-white' : item.color}`}>
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={item.icon} />
                            </svg>
                        </span>
                        {__(item.label)}
                    </Link>
                );
            })}
            <div className="mt-4 border-t border-neutral-200 pt-4">
                <Link href={route('legal.vendor-protection')} onClick={() => setMobileNavOpen(false)} className="block rounded-md px-3 py-2 text-sm text-neutral-500 hover:bg-neutral-100">
                    {__('Vendor Protection')}
                </Link>
                <Link href={route('legal.terms')} onClick={() => setMobileNavOpen(false)} className="block rounded-md px-3 py-2 text-sm text-neutral-500 hover:bg-neutral-100">
                    {__('Terms & Conditions')}
                </Link>
                <Link
                    href={storeSlug ? route('vendors.show', storeSlug) : route('home')}
                    onClick={() => setMobileNavOpen(false)}
                    className="block rounded-md px-3 py-2 text-sm text-neutral-500 hover:bg-neutral-100"
                >
                    {__('← Back to storefront')}
                </Link>
                <Link href={route('logout')} method="post" as="button" className="block w-full rounded-md px-3 py-2 text-left text-sm text-neutral-500 hover:bg-neutral-100">
                    {__('Logout')}
                </Link>
            </div>
        </nav>
    );

    return (
        <div className="flex min-h-screen bg-neutral-50">
            {/* Desktop sidebar */}
            <aside className="hidden w-56 shrink-0 border-r border-neutral-200 bg-white p-4 lg:block">
                <Link href={route('vendor.dashboard')} className="mb-6 block">
                    <img src="/images/branding/lockup.svg" alt="ChinaInNigeria" className="h-16 w-auto" />
                </Link>
                <NavLinks />
            </aside>

            <div className="flex flex-1 flex-col">
                {/* Top bar */}
                <div className="flex items-center justify-between border-b border-neutral-200 bg-white px-4 py-3 lg:px-6">
                    <div className="flex items-center gap-3">
                        <button
                            className="p-1 text-neutral-600 lg:hidden"
                            onClick={() => setMobileNavOpen((o) => !o)}
                            aria-label={__('Toggle menu')}
                        >
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        {user?.avatar_url && (
                            <Link href={route('vendor.profile.edit')} className="hidden h-8 w-8 shrink-0 overflow-hidden rounded-full bg-orange-100 sm:block">
                                <img src={user.avatar_url} alt="" className="h-full w-full object-cover" />
                            </Link>
                        )}
                        <div>
                            <p className="text-sm font-semibold text-neutral-800">{vendor?.business_name ?? __('Vendor Dashboard')}</p>
                            {user && <p className="text-xs text-neutral-400">{user.email}</p>}
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2 font-ui text-[12px]" role="navigation" aria-label={__('Language')}>
                            {LANGUAGES.map((lang) => (
                                <a
                                    key={lang.code}
                                    href={langHref(lang.code)}
                                    className={
                                        locale === lang.code
                                            ? 'font-bold text-orange-600'
                                            : 'text-neutral-500 hover:text-orange-600'
                                    }
                                    aria-current={locale === lang.code ? 'true' : undefined}
                                >
                                    {lang.label}
                                </a>
                            ))}
                        </div>
                        <NotificationBell />
                        {vendor?.status && <StatusBadge status={vendor.status} />}
                    </div>
                </div>

                {mobileNavOpen && (
                    <div className="border-b border-neutral-200 bg-white p-4 lg:hidden">
                        <NavLinks />
                    </div>
                )}

                <main className="flex-1 p-4 lg:p-6">{children}</main>
            </div>
        </div>
    );
}
