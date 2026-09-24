import { Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ImageSearchModal from '@/Components/ImageSearchModal';
import NotificationBell from '@/Components/NotificationBell';
import MobileTabBar from '@/Components/MobileTabBar';
import SearchSuggestionsDropdown from '@/Components/SearchSuggestionsDropdown';
import useSearchSuggestions from '@/hooks/useSearchSuggestions';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

const LANGUAGES = [
    { code: 'en', label: 'EN' },
    { code: 'zh', label: '中文' },
    { code: 'fr', label: 'FR' },
];

const CHANNEL_DEFS = [
    {
        labelKey: 'Flash Deals',
        href: '/products',
        color: 'text-tb-red',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        ),
    },
    {
        labelKey: 'Compare Prices',
        href: '/compare',
        color: 'text-tb-green',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17V7m0 10l-3-3m3 3l3-3M15 7v10m0-10l3 3m-3-3l-3 3" />
            </svg>
        ),
    },
    {
        labelKey: 'Verified Vendors',
        href: '/stores',
        color: 'text-tb-green',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        ),
    },
    {
        labelKey: 'New Arrivals',
        href: '/products',
        color: 'text-tb-text',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
            </svg>
        ),
    },
    {
        labelKey: 'Electronics',
        href: '/categories/electronics',
        color: 'text-tb-red',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <rect x="5" y="3" width="14" height="18" rx="2" strokeWidth="2" />
                <path strokeWidth="2" d="M9 17h6" />
            </svg>
        ),
    },
    {
        labelKey: 'Fashion',
        href: '/categories/fashion',
        color: 'text-tb-text',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 4l4 3 4-3 3 3-3 2v11H8V9L5 7l3-3z" />
            </svg>
        ),
    },
    {
        labelKey: 'Beauty',
        href: '/categories/beauty',
        color: 'text-tb-green',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 3v6m0 0a4 4 0 014 4v8H8v-8a4 4 0 014-4z" />
            </svg>
        ),
    },
    {
        labelKey: 'Home & Kitchen',
        href: '/categories/home-kitchen',
        color: 'text-tb-text',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10l9-7 9 7v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z" />
            </svg>
        ),
    },
    {
        labelKey: 'Kids',
        href: '/kids',
        color: 'text-tb-red',
        icon: (
            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="12" cy="8" r="3" strokeWidth={2} />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 20c1.5-3 4-4.5 6-4.5S16.5 17 18 20" />
            </svg>
        ),
    },
];

/**
 * Persistent layout for buyer-facing pages — Taobao-inspired marketplace chrome:
 * utility strip, logo + search, channel links, then page body.
 */
export default function StorefrontLayout({ children, categories = [], showCategoryBar = false }) {
    const { auth, cartCount = 0, locale = 'en' } = usePage().props;
    const user = auth?.user;
    const isVendor = auth?.isVendor ?? false;
    const { __ } = useTranslation();
    const itemCount = Number(cartCount) || 0;

    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [mobileCategoriesOpen, setMobileCategoriesOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [searchCategory, setSearchCategory] = useState('');
    const [imageSearchOpen, setImageSearchOpen] = useState(false);
    const [desktopSuggestOpen, setDesktopSuggestOpen] = useState(false);
    const [mobileSuggestOpen, setMobileSuggestOpen] = useState(false);
    const imageSearchBtnRef = useRef(null);
    const mobileImageSearchBtnRef = useRef(null);

    // Shared between the desktop and mobile search boxes — both bind to the same `search` state,
    // so one fetch covers whichever is actually visible at the current viewport width.
    const { suggestions, loading: suggestionsLoading } = useSearchSuggestions(search);

    const langHref = (code) => {
        if (typeof window === 'undefined') return `?lang=${code}`;
        const url = new URL(window.location.href);
        url.searchParams.set('lang', code);
        return `${url.pathname}?${url.searchParams.toString()}`;
    };

    const submitSearch = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('q', search);
        if (searchCategory) params.set('category', searchCategory);
        window.location.href = `${route('products.index')}?${params.toString()}`;
    };

    const selectSuggestion = (item) => {
        setDesktopSuggestOpen(false);
        setMobileSuggestOpen(false);
        setSearch('');
        router.visit(item.url);
    };

    return (
        <div className="flex min-h-screen flex-col bg-white font-sans text-tb-text">
            <div className="border-b border-tb-border bg-[#f5f5f5] font-ui text-[12px] text-tb-muted">
                <div className="mx-auto flex h-8 max-w-marketplace items-center justify-between px-1.5 sm:px-2">
                    <div className="flex items-center gap-3">
                        <span className="hidden sm:inline">{__('Deliver to Nigeria')}</span>
                        <span className="hidden text-tb-border sm:inline">|</span>
                        {!user && (
                            <>
                                <Link href={route('login')} className="hover:text-tb">
                                    {__('Login')}
                                </Link>
                                <Link href={route('register')} className="hover:text-tb">
                                    {__('Sign Up')}
                                </Link>
                            </>
                        )}
                        {user && (
                            <Link href={route('profile.edit')} className="flex items-center gap-1.5 hover:text-tb">
                                <span className="h-5 w-5 shrink-0 overflow-hidden rounded-full bg-orange-100">
                                    {user.avatar_url ? (
                                        <img src={user.avatar_url} alt="" className="h-full w-full object-cover" />
                                    ) : (
                                        <span className="flex h-full w-full items-center justify-center text-[10px] font-bold text-orange-600">
                                            {user.name?.charAt(0).toUpperCase()}
                                        </span>
                                    )}
                                </span>
                                {__('Hi, :name', { name: user.name })}
                            </Link>
                        )}
                        {isVendor && (
                            <>
                                <span className="hidden text-tb-border sm:inline">|</span>
                                <Link href={route('vendor.dashboard')} className="font-semibold text-tb hover:underline">
                                    {__('Vendor Dashboard')}
                                </Link>
                            </>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="hidden items-center gap-2 sm:flex">
                            {LANGUAGES.map((lang) => (
                                <a
                                    key={lang.code}
                                    href={langHref(lang.code)}
                                    className={locale === lang.code ? 'font-bold text-tb' : 'hover:text-tb'}
                                    aria-current={locale === lang.code ? 'true' : undefined}
                                >
                                    {lang.label}
                                </a>
                            ))}
                        </div>
                        <Link href={route('orders.index')} className="hidden hover:text-tb sm:inline">
                            {__('My Orders')}
                        </Link>
                        <Link href={route('track-order.index')} className="hidden hover:text-tb sm:inline">
                            {__('Track Order')}
                        </Link>
                        {user && (
                            <Link href={route('support.index')} className="hidden hover:text-tb sm:inline">
                                {__('Support')}
                            </Link>
                        )}
                        {user && <NotificationBell />}
                        {/* Hidden on mobile — MobileTabBar's own Cart tab (with the same badge)
                            covers this now, so keeping both would just duplicate the affordance. */}
                        <Link href={route('cart.index')} className="relative hidden items-center gap-1 hover:text-tb sm:inline-flex">
                            {__('Cart')}
                            {itemCount > 0 && (
                                <span className="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-tb-green px-1 text-[10px] font-bold leading-none text-white">
                                    {itemCount > 99 ? '99+' : itemCount}
                                </span>
                            )}
                        </Link>
                        <Link href={isVendor ? route('vendor.dashboard') : route('open-shop')} className="hidden hover:text-tb md:inline">
                            {isVendor ? __('Vendor Dashboard') : __('Sell')}
                        </Link>
                        {/* Mobile-only quick links — "Categories" (its dropdown panel is a
                            full-width white card below the header, same treatment as the "Menu"
                            panel further down, not a small popover anchored to this button) and an
                            "Open a shop" link, alongside the language picker (the desktop language
                            picker above is `sm:flex`, hidden below that breakpoint). */}
                        <div className="flex flex-wrap items-center justify-end gap-2 sm:hidden">
                            <button
                                type="button"
                                onClick={() => {
                                    setMobileMenuOpen(false);
                                    setMobileCategoriesOpen((o) => !o);
                                }}
                                className={mobileCategoriesOpen ? 'font-bold text-tb' : 'hover:text-tb'}
                                aria-expanded={mobileCategoriesOpen}
                            >
                                {__('Categories')}
                            </button>
                            <Link
                                href={isVendor ? route('vendor.dashboard') : route('open-shop')}
                                className="hover:text-tb"
                            >
                                {isVendor ? __('Vendor Dashboard') : __('Open a shop')}
                            </Link>
                            {LANGUAGES.map((lang) => (
                                <a
                                    key={lang.code}
                                    href={langHref(lang.code)}
                                    className={locale === lang.code ? 'font-bold text-tb' : 'hover:text-tb'}
                                    aria-current={locale === lang.code ? 'true' : undefined}
                                >
                                    {lang.label}
                                </a>
                            ))}
                        </div>
                        <button
                            type="button"
                            className="sm:hidden"
                            onClick={() => {
                                setMobileCategoriesOpen(false);
                                setMobileMenuOpen((o) => !o);
                            }}
                            aria-label={__('Menu')}
                        >
                            {__('Menu')}
                        </button>
                    </div>
                </div>
            </div>

            {/* Sticky on every breakpoint — logo/search staying reachable while scrolling is one of
                the cheapest "feels like a native app, not a scrolled-away website" wins on mobile,
                and is a harmless, common pattern on desktop too. */}
            <div className="sticky top-0 z-30 bg-white">
                {/* This whole row collapses to nothing on mobile once the logo (its only child
                    below sm:) is hidden — the mobile search bar lives in its own separate row
                    further down, so there's no py-4 worth of dead space left behind here. */}
                <div className="mx-auto flex max-w-marketplace items-center gap-4 px-1.5 sm:gap-6 sm:px-2 sm:py-4">
                    {/* Hidden on mobile — the logo lockup + tagline take up a lot of width on a
                        phone screen for what the header can otherwise spend on the search bar; the
                        logo still appears in the footer (see below) and the home tab in
                        MobileTabBar already covers "get back home". */}
                    <Link href={route('home')} className="hidden shrink-0 flex-col items-center text-center sm:flex">
                        <img
                            src="/images/branding/lockup.svg"
                            alt="ChinaInNigeria"
                            className="h-16 w-[11rem] origin-center scale-x-[1.12] object-contain object-center sm:h-[4.5rem] sm:w-[13rem] md:h-[4.75rem] md:w-60"
                        />
                        <span className="mt-1 max-w-[14rem] text-[11px] font-medium leading-snug text-tb-muted sm:max-w-[16rem] sm:text-[12px]">
                            {__('Chinese stores · Unbeatable prices')}
                        </span>
                    </Link>

                    <div className="mx-auto hidden w-full max-w-[860px] items-center gap-2.5 md:flex">
                        <form onSubmit={submitSearch} className="relative min-w-0 flex-1">
                            <div className="flex h-10 items-stretch overflow-hidden rounded-lg border-2 border-tb">
                                <select
                                    value={searchCategory}
                                    onChange={(e) => setSearchCategory(e.target.value)}
                                    className="w-[88px] shrink-0 border-0 border-r border-tb-border bg-white py-0 pl-3 pr-1 text-[12px] text-tb-text focus:ring-0"
                                >
                                    <option value="">{__('All')}</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.slug}>
                                            {t(cat.name)}
                                        </option>
                                    ))}
                                </select>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onFocus={() => setDesktopSuggestOpen(true)}
                                    onBlur={() => setDesktopSuggestOpen(false)}
                                    placeholder={__('Search products, stores...')}
                                    className="h-full min-w-0 flex-1 border-0 px-3 text-[13px] text-tb-text placeholder:text-tb-muted focus:ring-0"
                                    autoComplete="off"
                                />
                                <button
                                    type="submit"
                                    className="w-[88px] shrink-0 bg-tb font-display text-[15px] font-semibold text-white hover:bg-tb-hover"
                                >
                                    {__('Search')}
                                </button>
                            </div>
                            <SearchSuggestionsDropdown
                                show={desktopSuggestOpen && search.trim().length >= 2}
                                suggestions={suggestions}
                                loading={suggestionsLoading}
                                onSelect={selectSuggestion}
                                className="inset-x-0 top-full"
                            />
                        </form>

                        <button
                            ref={imageSearchBtnRef}
                            type="button"
                            onClick={() => setImageSearchOpen((o) => !o)}
                            className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg bg-tb-soft px-3.5 py-6 font-display text-[13px] font-bold text-tb hover:bg-[#ffe4d4]"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 7a2 2 0 012-2h3l2-2h4l2 2h3a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                                <circle cx="12" cy="13" r="3.5" />
                            </svg>
                            {__('Search with Image')}
                        </button>
                    </div>

                    <div className="ml-auto hidden shrink-0 lg:block">
                        <Link
                            href={route('cart.index')}
                            className="relative flex h-10 items-center gap-2 rounded-full border border-tb px-4 text-[13px] font-medium text-tb hover:bg-tb-soft"
                        >
                            <span className="relative">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.2 2.4A1 1 0 006.7 17H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                {itemCount > 0 && (
                                    <span className="absolute -right-2.5 -top-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-tb-green px-1 text-[11px] font-bold leading-none text-white">
                                        {itemCount > 99 ? '99+' : itemCount}
                                    </span>
                                )}
                            </span>
                            {__('Cart')}
                        </Link>
                    </div>
                </div>

                <div className="relative border-t border-tb-border px-1.5 py-2 sm:px-2 md:hidden">
                    <form onSubmit={submitSearch}>
                        <div className="flex h-9 overflow-hidden rounded-full border-2 border-tb">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onFocus={() => setMobileSuggestOpen(true)}
                                onBlur={() => setMobileSuggestOpen(false)}
                                placeholder={__('Search products, stores...')}
                                className="min-w-0 flex-1 border-0 px-3 text-[13px] focus:ring-0"
                                autoComplete="off"
                            />
                            <button
                                ref={mobileImageSearchBtnRef}
                                type="button"
                                onClick={() => setImageSearchOpen((o) => !o)}
                                className="shrink-0 border-l border-tb-border bg-tb-soft px-2.5 text-tb"
                                aria-label={__('Search with Image')}
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 7a2 2 0 012-2h3l2-2h4l2 2h3a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                                    <circle cx="12" cy="13" r="3.5" />
                                </svg>
                            </button>
                            <button type="submit" className="bg-tb px-4 font-display text-[13px] font-semibold text-white">
                                {__('Search')}
                            </button>
                        </div>
                    </form>
                    <SearchSuggestionsDropdown
                        show={mobileSuggestOpen && search.trim().length >= 2}
                        suggestions={suggestions}
                        loading={suggestionsLoading}
                        onSelect={selectSuggestion}
                        className="inset-x-1.5 top-full sm:inset-x-2"
                    />
                </div>

                <div className="pointer-events-none absolute inset-x-0 top-full z-50 mx-auto max-w-marketplace px-1.5 sm:px-2">
                    <div className="pointer-events-auto relative">
                        <ImageSearchModal
                            open={imageSearchOpen}
                            onClose={() => setImageSearchOpen(false)}
                            anchorRef={imageSearchBtnRef}
                            mobileAnchorRef={mobileImageSearchBtnRef}
                        />
                    </div>
                </div>
            </div>

            <div className="bg-white">
                <div className="mx-auto hidden w-full max-w-marketplace px-1.5 pb-3 sm:px-2 md:block">
                    <div className="flex w-full items-center justify-between gap-2 font-ui text-[13px] font-semibold">
                        {CHANNEL_DEFS.map((ch) => (
                            <Link key={ch.labelKey} href={ch.href} className={`inline-flex shrink-0 items-center gap-1 ${ch.color} hover:opacity-80`}>
                                {ch.icon}
                                <span>{__(ch.labelKey)}</span>
                            </Link>
                        ))}
                        <Link
                            href={isVendor ? route('vendor.dashboard') : route('open-shop')}
                            className="inline-flex shrink-0 items-center gap-1 text-tb hover:opacity-80"
                        >
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6M4 7h16l-1 12H5L4 7z" />
                            </svg>
                            <span>{isVendor ? __('Vendor Dashboard') : __('Sell on CIN')}</span>
                        </Link>
                    </div>
                </div>
            </div>

            {showCategoryBar && categories.length > 0 && (
                <div className="border-y border-tb-border bg-white">
                    <div className="mx-auto flex max-w-marketplace gap-5 overflow-x-auto px-1.5 py-2 text-[13px] sm:px-2">
                        {categories.map((cat) => (
                            <Link
                                key={cat.id}
                                href={route('categories.show', cat.slug)}
                                className="whitespace-nowrap text-tb-muted hover:text-tb"
                            >
                                {t(cat.name)}
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {mobileCategoriesOpen && categories.length > 0 && (
                <div className="border-b border-tb-border bg-white px-2 py-3 text-[13px] sm:hidden">
                    <div className="grid grid-cols-2 gap-1">
                        {categories.map((cat) => (
                            <Link
                                key={cat.id}
                                href={route('categories.show', cat.slug)}
                                onClick={() => setMobileCategoriesOpen(false)}
                                className="truncate rounded-md px-2 py-2 text-tb-text hover:bg-tb-soft hover:text-tb"
                            >
                                {t(cat.name)}
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {mobileMenuOpen && (
                <div className="border-b border-tb-border bg-white px-2 py-3 text-[13px] sm:hidden">
                    <div className="flex flex-col gap-2">
                        {categories.map((cat) => (
                            <Link key={cat.id} href={route('categories.show', cat.slug)} className="text-tb-text">
                                {t(cat.name)}
                            </Link>
                        ))}
                        <Link href={route('stores.index')} className="text-tb-text">
                            {__('Stores')}
                        </Link>
                        <Link href={route('compare.index')} className="text-tb-text">
                            {__('Compare Prices')}
                        </Link>
                        <Link href={route('favourites.index')} className="text-tb-text">
                            {__('Favourites')}
                        </Link>
                        <Link href={route('orders.index')} className="text-tb-text">
                            {__('My Orders')}
                        </Link>
                        <Link href={route('track-order.index')} className="text-tb-text">
                            {__('Track Order')}
                        </Link>
                        <Link href={route('open-shop')} className="text-tb">
                            {__('Open a shop')}
                        </Link>
                    </div>
                </div>
            )}

            <main className="flex-1">{children}</main>

            <footer className="mt-8 border-t border-tb-border bg-white">
                <div className="mx-auto max-w-marketplace px-1.5 py-10 text-[12px] text-tb-muted sm:px-2">
                    <div className="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:grid-cols-6">
                        <div>
                            <img
                                src="/images/branding/lockup.svg"
                                alt="ChinaInNigeria"
                                className="mb-3 h-9 w-auto origin-left scale-x-[1.12] object-contain"
                            />
                            <p>{__('Chinese-run stores delivering across Nigeria.')}</p>
                        </div>
                        <div>
                            <h4 className="font-display mb-3 text-[13px] font-semibold text-tb-text">{__('Shop')}</h4>
                            <ul className="space-y-2">
                                <li>
                                    <Link href={route('products.index')} className="hover:text-tb">
                                        {__('All Products')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('stores.index')} className="hover:text-tb">
                                        {__('Stores')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('compare.index')} className="hover:text-tb">
                                        {__('Compare Prices')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('favourites.index')} className="hover:text-tb">
                                        {__('Favourites')}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-display mb-3 text-[13px] font-semibold text-tb-text">{__('Support')}</h4>
                            <ul className="space-y-2">
                                <li>
                                    <Link href={route('orders.index')} className="hover:text-tb">
                                        {__('My Orders')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('track-order.index')} className="hover:text-tb">
                                        {__('Track Order')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('legal.return-policy')} className="hover:text-tb">
                                        {__('Return Policy')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('support.index')} className="hover:text-tb">
                                        {__('Support')}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-display mb-3 text-[13px] font-semibold text-tb-text">{__('Legal')}</h4>
                            <ul className="space-y-2">
                                <li>
                                    <Link href={route('legal.terms')} className="hover:text-tb">
                                        {__('Terms & Conditions')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('legal.privacy')} className="hover:text-tb">
                                        {__('Privacy Policy')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('legal.customer-protection')} className="hover:text-tb">
                                        {__('Customer Protection')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('legal.vendor-protection')} className="hover:text-tb">
                                        {__('Vendor Protection')}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-display mb-3 text-[13px] font-semibold text-tb-text">{__('Sell')}</h4>
                            <ul className="space-y-2">
                                <li>
                                    <Link href={isVendor ? route('vendor.dashboard') : route('open-shop')} className="hover:text-tb">
                                        {isVendor ? __('Vendor Dashboard') : __('Open a shop')}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-display mb-3 text-[13px] font-semibold text-tb-text">{__('Language')}</h4>
                            <div className="flex gap-3">
                                {LANGUAGES.map((lang) => (
                                    <a
                                        key={lang.code}
                                        href={langHref(lang.code)}
                                        className={locale === lang.code ? 'font-bold text-tb' : 'hover:text-tb'}
                                        aria-current={locale === lang.code ? 'true' : undefined}
                                    >
                                        {lang.label}
                                    </a>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="mt-8 flex flex-col gap-3 border-t border-tb-border pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p>© {new Date().getFullYear()} ChinaInNigeria. {__('All rights reserved.')}</p>
                        <div className="flex flex-wrap gap-x-4 gap-y-1">
                            <Link href={route('legal.terms')} className="hover:text-tb">{__('Terms')}</Link>
                            <Link href={route('legal.privacy')} className="hover:text-tb">{__('Privacy')}</Link>
                            <Link href={route('legal.return-policy')} className="hover:text-tb">{__('Returns')}</Link>
                        </div>
                    </div>
                </div>
            </footer>

            {/* Reserves the space MobileTabBar's fixed positioning takes out of the document flow —
                without this, the tab bar would float on top of the last bit of footer content. */}
            <div className="cin-tabbar-spacer sm:hidden" aria-hidden="true" />
            <MobileTabBar />
        </div>
    );
}
