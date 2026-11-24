import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import WelcomeModal from '@/Components/WelcomeModal';
import CompleteProfileModal from '@/Components/CompleteProfileModal';
import TopPicksSection from '@/Components/TopPicksSection';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';
import { priceOf, wasPriceOf } from '@/utils/productPrice';

const CATEGORY_ICONS = {
    electronics: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <rect x="5" y="3" width="14" height="18" rx="2" strokeWidth="1.6" />
            <path strokeWidth="1.6" d="M9 17h6" />
        </svg>
    ),
    'home-kitchen': (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeWidth="1.6" d="M3 10l9-7 9 7v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z" />
        </svg>
    ),
    fashion: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeWidth="1.6" d="M8 4l4 3 4-3 3 3-3 2v11H8V9L5 7l3-3z" />
        </svg>
    ),
    beauty: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeWidth="1.6" d="M12 3v6m0 0a4 4 0 014 4v8H8v-8a4 4 0 014-4z" />
        </svg>
    ),
    toys: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <circle cx="12" cy="12" r="8" strokeWidth="1.6" />
            <path strokeWidth="1.6" d="M8 12h8M12 8v8" />
        </svg>
    ),
    'baby-kids': (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <circle cx="12" cy="8" r="3" strokeWidth="1.6" />
            <path strokeWidth="1.6" d="M6 20c1.5-3 4-4.5 6-4.5S16.5 17 18 20" />
        </svg>
    ),
};

/** Admin-edited translatable text ({en, zh, fr}) with a fallback when left empty. */
function tx(field, fallback = '') {
    return t(field) || fallback;
}

function productImage(product) {
    const image = product?.images?.find((img) => img.is_primary) ?? product?.images?.[0];
    return image?.enhanced_url ?? image?.original_url ?? null;
}

function SectionHeading({ eyebrow, title, action }) {
    return (
        <div className="mb-4 flex items-end justify-between gap-3">
            <div>
                {eyebrow && (
                    <p className="font-ui text-[11px] font-bold uppercase tracking-[0.18em] text-tb">{eyebrow}</p>
                )}
                <h2 className="font-display text-[20px] font-bold tracking-tight text-tb-ink sm:text-[22px]">{title}</h2>
            </div>
            {action}
        </div>
    );
}

/* ---------------------------------------------------------------- Hero */

function HeroCarousel({ banners, section }) {
    const { __ } = useTranslation();
    const [index, setIndex] = useState(0);
    const count = banners.length;

    useEffect(() => {
        if (count <= 1) return undefined;
        const timer = setInterval(() => setIndex((i) => (i + 1) % count), 5500);
        return () => clearInterval(timer);
    }, [count, index]);

    if (count === 0) {
        return (
            <div className="cin-gradient relative flex h-[220px] overflow-hidden rounded-3xl px-6 py-6 text-white shadow-float sm:h-[400px] sm:px-10">
                {/* Photo: Iuliia Pilipeichenko on Unsplash (Unsplash License) —
                    https://unsplash.com/photos/uaWMyAIpF2g. Full-bleed under an orange tint on phones;
                    on wider screens it fills the right side and fades into the gradient via a mask. */}
                <img
                    src="/images/home/hero-shopping.jpg"
                    alt=""
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 h-full w-full object-cover object-center sm:left-auto sm:w-[62%] sm:[mask-image:linear-gradient(to_right,transparent,#000_38%)]"
                />
                <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-tb/90 via-tb/75 to-tb-red/40 sm:hidden" />
                <div className="pointer-events-none absolute -right-16 -top-20 h-72 w-72 rounded-full bg-tb-amber/50 blur-3xl sm:bg-tb-amber/15" />
                <div className="pointer-events-none absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-white/25 blur-3xl" />
                <div className="relative flex max-w-lg flex-col justify-center">
                    {/* <span className="cin-glass-dark mb-3 inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold">
                        <span className="h-1.5 w-1.5 rounded-full bg-tb-amber" />
                        {__('Verified Chinese-run stores')}
                    </span> */}
                    <p className="font-display text-[26px] font-extrabold leading-[1.05] tracking-tight sm:text-[44px]">
                        {tx(section?.title, __('Welcome to ChinaInNigeria'))}
                    </p>
                    <p className="mt-2 text-[13px] text-white/85 sm:text-[15px]">{tx(section?.subtitle, __('Chinese-run stores · Unbeatable prices'))}</p>
                    <div className="mt-5 flex flex-wrap gap-2">
                        <Link
                            href={route('products.index')}
                            className="rounded-full bg-white px-5 py-2.5 font-display text-[13px] font-bold text-tb shadow-float transition hover:-translate-y-px"
                        >
                            {__('Start shopping')}
                        </Link>
                        <Link
                            href={route('stores.index')}
                            className="cin-glass-dark rounded-full px-5 py-2.5 font-display text-[13px] font-semibold text-white transition hover:bg-black/40"
                        >
                            {__('Browse stores')}
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    const go = (dir) => setIndex((i) => (i + dir + count) % count);

    return (
        <div className="group/hero relative h-[200px] overflow-hidden rounded-3xl bg-tb-peach shadow-float sm:h-[400px]">
            {banners.map((banner, i) => (
                <a
                    key={banner.id}
                    href={banner.link_url || '#'}
                    aria-hidden={i !== index}
                    tabIndex={i === index ? 0 : -1}
                    className={`absolute inset-0 transition-all duration-700 ease-out ${
                        i === index ? 'scale-100 opacity-100' : 'pointer-events-none scale-[1.04] opacity-0'
                    }`}
                >
                    <img src={banner.image_url} alt={t(banner.title)} className="h-full w-full object-cover" loading={i === 0 ? 'eager' : 'lazy'} decoding="async" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/50 via-black/5 to-transparent" />
                    {t(banner.title) && (
                        <div className="cin-glass-dark absolute bottom-4 left-4 max-w-[80%] rounded-2xl px-4 py-2.5 sm:bottom-6 sm:left-6 sm:px-5 sm:py-3.5">
                            <h2 className="font-display text-[14px] font-bold leading-tight text-white sm:text-[22px]">{t(banner.title)}</h2>
                            <span className="mt-1 hidden items-center gap-1 text-[12px] font-semibold text-white/85 sm:inline-flex">
                                {__('Shop now')} →
                            </span>
                        </div>
                    )}
                </a>
            ))}

            {count > 1 && (
                <>
                    {[-1, 1].map((dir) => (
                        <button
                            key={dir}
                            type="button"
                            onClick={() => go(dir)}
                            aria-label={dir < 0 ? __('Previous slide') : __('Next slide')}
                            className={`cin-glass absolute top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full text-tb-ink opacity-0 shadow-glass transition group-hover/hero:opacity-100 sm:flex ${
                                dir < 0 ? 'left-4' : 'right-4'
                            }`}
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.4} d={dir < 0 ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7'} />
                            </svg>
                        </button>
                    ))}
                    <div className="cin-glass-dark absolute bottom-4 right-4 flex items-center gap-1.5 rounded-full px-2.5 py-1.5 sm:bottom-6 sm:right-6">
                        {banners.map((_, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => setIndex(i)}
                                className={`h-1.5 rounded-full transition-all duration-300 ${i === index ? 'w-5 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80'}`}
                                aria-label={`Slide ${i + 1}`}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

/* ---------------------------------------------------------------- Account card */

const ICONS = {
    orders: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    cart: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.2 2.4A1 1 0 006.7 17H17',
    coupon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
    clock: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    store: 'M3 9l1-4h16l1 4M4 9v10h4v-5h8v5h4V9M9 9V5h6v4',
    heart: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    shop: 'M12 6v6m0 0v6m0-6h6m-6 0H6M4 7h16l-1 12H5L4 7z',
};

function Icon({ name, className = 'h-[18px] w-[18px]' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d={ICONS[name]} />
        </svg>
    );
}

function AccountCard({ coupons, horizontal = false }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const { __ } = useTranslation();
    const hour = new Date().getHours();
    const greeting = hour < 12 ? __('Good morning') : hour < 18 ? __('Good afternoon') : __('Good evening');

    const links = [
        { label: __('Orders'), href: route('orders.index'), icon: 'orders', tint: 'bg-tb/10 text-tb' },
        { label: __('Cart'), href: route('cart.index'), icon: 'cart', tint: 'bg-tb-amber/15 text-[#C27A00]' },
        { label: __('Coupons'), href: route('products.index'), icon: 'coupon', tint: 'bg-tb-red/10 text-tb-red' },
        { label: __('Footprint'), href: route('products.index'), icon: 'clock', tint: 'bg-sky-500/10 text-sky-600' },
        { label: __('Stores'), href: route('stores.index'), icon: 'store', tint: 'bg-tb-green/10 text-tb-green' },
        { label: __('Favourite'), href: route('favourites.index'), icon: 'heart', tint: 'bg-pink-500/10 text-pink-600' },
    ];

    return (
        <aside className={`cin-glass flex h-full gap-3 rounded-3xl p-3 shadow-glass ${horizontal ? 'flex-col sm:flex-row sm:items-stretch' : 'flex-col'}`}>
            {/* Profile header */}
            <div className={`relative overflow-hidden rounded-2xl bg-gradient-to-br from-tb-peach via-white to-tb-cream p-3 ${horizontal ? 'sm:w-64 sm:shrink-0' : ''}`}>
                <div className="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-tb/25 blur-2xl" />
                <div className="relative flex items-center gap-3">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-soft ring-2 ring-white">
                        {user?.avatar_url ? (
                            <img src={user.avatar_url} alt="" className="h-full w-full object-cover" />
                        ) : user ? (
                            <span className="cin-gradient-text font-display text-lg font-extrabold">{user.name.charAt(0).toUpperCase()}</span>
                        ) : (
                            <svg className="h-6 w-6 text-tb-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        )}
                    </div>
                    <div className="min-w-0">
                        <p className="text-[11px] font-medium text-neutral-500">{greeting}</p>
                        <p className="truncate font-display text-[15px] font-bold text-tb-ink">
                            {user ? user.name.split(' ')[0] : __('Welcome, guest')}
                        </p>
                    </div>
                </div>
                {!user ? (
                    <div className="relative mt-3 grid grid-cols-2 gap-2">
                        <Link
                            href={route('login')}
                            className="cin-gradient flex h-9 items-center justify-center rounded-full font-display text-[13px] font-bold text-white shadow-glow transition hover:brightness-110"
                        >
                            {__('Log in')}
                        </Link>
                        <Link
                            href={route('register')}
                            className="flex h-9 items-center justify-center rounded-full bg-white font-display text-[13px] font-semibold text-tb-ink ring-1 ring-black/10 transition hover:ring-tb/40"
                        >
                            {__('Sign Up')}
                        </Link>
                    </div>
                ) : (
                    <Link
                        href={route('orders.index')}
                        className="relative mt-3 flex h-9 items-center justify-center rounded-full bg-tb-ink font-display text-[13px] font-semibold text-white transition hover:bg-black"
                    >
                        {__('My Orders')}
                    </Link>
                )}
            </div>

            {/* Quick links */}
            <div className={`grid grid-cols-3 gap-1.5 ${horizontal ? 'sm:flex-1 sm:grid-cols-6 sm:content-center' : ''}`}>
                {links.map((item) => (
                    <Link
                        key={item.label}
                        href={item.href}
                        className="group flex flex-col items-center gap-1 rounded-xl py-1.5 text-center transition hover:bg-white/80"
                    >
                        <span className={`flex h-9 w-9 items-center justify-center rounded-xl transition group-hover:scale-110 ${item.tint}`}>
                            <Icon name={item.icon} />
                        </span>
                        <span className="text-[10.5px] font-medium text-neutral-600">{item.label}</span>
                    </Link>
                ))}
            </div>

            {/* Credits / sell promo */}
            <div className={`relative mt-auto overflow-hidden rounded-2xl bg-tb-ink p-3 text-white ${horizontal ? 'sm:w-60 sm:shrink-0' : ''}`}>
                <div className="pointer-events-none absolute -bottom-10 -right-6 h-28 w-28 rounded-full bg-tb/60 blur-2xl" />
                <div className="pointer-events-none absolute -left-6 -top-8 h-20 w-20 rounded-full bg-tb-amber/30 blur-2xl" />
                <p className="relative font-display text-[13px] font-bold">{__('CIN Credits')}</p>
                <p className="relative mt-0.5 text-[11px] leading-snug text-white/70">
                    {coupons?.[0]
                        ? __('Use code :code at checkout for extra savings.', { code: coupons[0].code })
                        : __('Save more with marketplace coupons at checkout.')}
                </p>
                <Link
                    href={route('open-shop')}
                    className="relative mt-2 inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/20"
                >
                    <Icon name="shop" className="cin-flash-icon h-3.5 w-3.5 text-tb-amber" />
                    {__('Open a shop')}
                </Link>
            </div>
        </aside>
    );
}

/* ---------------------------------------------------------------- Categories */

const CATEGORY_TINTS = [
    'from-[#FFE3D3] to-[#FFF4EC]',
    'from-[#FFE0E6] to-[#FFF3F5]',
    'from-[#FFF0C9] to-[#FFF9EA]',
    'from-[#D9F3E8] to-[#F0FBF6]',
    'from-[#DDEBFF] to-[#F2F7FF]',
    'from-[#EDE3FF] to-[#F7F2FF]',
];

function CategoryRail({ categories }) {
    const { __ } = useTranslation();
    if (categories.length === 0) return null;

    return (
        <section className="mt-6">
            <div className="no-scrollbar -mx-1.5 flex snap-x gap-3 overflow-x-auto px-1.5 pb-1 sm:gap-4">
                {categories.map((cat, i) => (
                    <Link
                        key={cat.id}
                        href={route('categories.show', cat.slug)}
                        className="group flex w-[76px] shrink-0 snap-start flex-col items-center gap-2 text-center sm:w-[92px]"
                    >
                        <span
                            className={`flex h-[64px] w-[64px] items-center justify-center overflow-hidden rounded-[22px] bg-gradient-to-br text-tb shadow-soft ring-1 ring-black/[0.04] transition duration-300 group-hover:-translate-y-1 group-hover:shadow-float sm:h-[76px] sm:w-[76px] ${CATEGORY_TINTS[i % CATEGORY_TINTS.length]}`}
                        >
                            {cat.image_url ? (
                                <img src={cat.image_url} alt="" className="h-full w-full object-cover" />
                            ) : CATEGORY_ICONS[cat.slug] ? (
                                <span className="[&>svg]:h-7 [&>svg]:w-7">{CATEGORY_ICONS[cat.slug]}</span>
                            ) : (
                                <span className="font-display text-xl font-extrabold">{t(cat.name).charAt(0)}</span>
                            )}
                        </span>
                        <span className="line-clamp-2 text-[11.5px] font-semibold leading-tight text-tb-text transition group-hover:text-tb">
                            {t(cat.name)}
                        </span>
                    </Link>
                ))}
                <Link href={route('products.index')} className="group flex w-[76px] shrink-0 snap-start flex-col items-center gap-2 text-center sm:w-[92px]">
                    <span className="cin-gradient flex h-[64px] w-[64px] items-center justify-center rounded-[22px] text-white shadow-glow transition duration-300 group-hover:-translate-y-1 sm:h-[76px] sm:w-[76px]">
                        <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 6h6v6H4zM14 6h6v6h-6zM4 16h6v4H4zM14 16h6v4h-6z" />
                        </svg>
                    </span>
                    <span className="text-[11.5px] font-semibold leading-tight text-tb">{__('All Products')}</span>
                </Link>
            </div>
        </section>
    );
}

/* ---------------------------------------------------------------- Trust strip */

const TRUST_ICONS = {
    shield: { d: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', tint: 'text-tb-green bg-tb-green/10' },
    truck: { d: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0H3m10 0h2m4 0h2v-4l-3-4h-5v8m-8 0a2 2 0 104 0m8 0a2 2 0 104 0', tint: 'text-tb bg-tb/10' },
    lock: { d: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', tint: 'text-sky-600 bg-sky-500/10' },
    tag: { d: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z', tint: 'text-[#C27A00] bg-tb-amber/15' },
    star: { d: 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z', tint: 'text-amber-600 bg-amber-500/10' },
    gift: { d: 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7', tint: 'text-pink-600 bg-pink-500/10' },
    headset: { d: 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z', tint: 'text-violet-600 bg-violet-500/10' },
    refresh: { d: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', tint: 'text-teal-600 bg-teal-500/10' },
};

const TRUST_COLS = { 1: 'lg:grid-cols-1', 2: 'lg:grid-cols-2', 3: 'lg:grid-cols-3', 4: 'lg:grid-cols-4' };

function TrustStrip({ section }) {
    const items = (section?.settings?.items ?? []).filter((item) => t(item.title));
    if (items.length === 0) return null;

    return (
        <section className={`cin-glass mt-6 grid grid-cols-2 gap-2 rounded-3xl p-2 shadow-soft ${TRUST_COLS[Math.min(items.length, 4)]}`}>
            {items.map((item, i) => {
                const icon = TRUST_ICONS[item.icon] ?? TRUST_ICONS.shield;
                return (
                    <div key={i} className="flex items-center gap-3 rounded-2xl px-2 py-2 sm:px-3">
                        <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${icon.tint}`}>
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d={icon.d} />
                            </svg>
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-display text-[13px] font-bold text-tb-ink">{t(item.title)}</p>
                            {t(item.text) && <p className="line-clamp-1 text-[11px] text-neutral-500">{t(item.text)}</p>}
                        </div>
                    </div>
                );
            })}
        </section>
    );
}

/* ---------------------------------------------------------------- Flash deals */

/** Counts down to the admin-set end time (section ends_at), or to local midnight when none is set.
 *  Isolated in its own component so the 1s tick doesn't re-render the rest of the page. Renders
 *  placeholders until mounted to avoid SSR mismatch. Shows days when more than 24h remain. */
function Countdown({ endsAt }) {
    const [left, setLeft] = useState(null);

    useEffect(() => {
        const target = endsAt ? new Date(endsAt) : null;
        const tick = () => {
            const now = new Date();
            const end = target ?? new Date(new Date(now).setHours(24, 0, 0, 0));
            setLeft(Math.max(0, Math.floor((end - now) / 1000)));
        };
        tick();
        const id = setInterval(tick, 1000);
        return () => clearInterval(id);
    }, [endsAt]);

    const days = left === null ? 0 : Math.floor(left / 86400);
    const parts = left === null
        ? ['--', '--', '--']
        : [Math.floor((left % 86400) / 3600), Math.floor((left % 3600) / 60), left % 60].map((n) => String(n).padStart(2, '0'));
    if (days > 0) parts.unshift(`${days}d`);

    return (
        <span className="flex items-center gap-1 font-display text-[13px] font-bold tabular-nums">
            {parts.map((p, i) => (
                <span key={i} className="flex items-center gap-1">
                    <span className="rounded-lg bg-white px-1.5 py-1 text-tb-red shadow-sm">{p}</span>
                    {i < parts.length - 1 && <span className="text-white/80">:</span>}
                </span>
            ))}
        </span>
    );
}

function FlashDeals({ section }) {
    const { __ } = useTranslation();
    const products = section.products ?? [];
    if (products.length === 0) return null;

    return (
        <section className="cin-gradient relative mt-8 overflow-hidden rounded-3xl p-3 shadow-float sm:p-5">
            <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-tb-amber/50 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-28 -left-10 h-72 w-72 rounded-full bg-white/20 blur-3xl" />

            <div className="relative mb-3 flex flex-wrap items-center justify-between gap-3 sm:mb-4">
                <div className="flex flex-wrap items-center gap-3 text-white">
                    <h2 className="flex items-center gap-1.5 font-display text-[20px] font-extrabold italic tracking-tight sm:text-[24px]">
                        <svg className="h-6 w-6 text-tb-amber" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
                            <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" />
                        </svg>
                        {tx(section.title, __('Flash Deals'))}
                    </h2>
                    <span className="flex items-center gap-2 text-[12px] font-medium text-white/90">
                        {__('Ends in')}
                        <Countdown endsAt={section.ends_at} />
                    </span>
                </div>
                <Link
                    href={route('products.index')}
                    className="cin-glass-dark rounded-full px-4 py-1.5 text-[12px] font-semibold text-white transition hover:bg-black/40"
                >
                    {__('View All')} →
                </Link>
            </div>

            <div className="relative grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-4">
                {products.map((product) => {
                    const img = productImage(product);
                    const pct = Number(product.discount_percentage) || 0;
                    return (
                        <Link
                            key={product.id}
                            href={route('products.show', product.slug)}
                            className="group flex flex-col rounded-2xl bg-white p-1.5 transition duration-300 hover:-translate-y-1 hover:shadow-float"
                        >
                            <div className="relative aspect-square overflow-hidden rounded-xl bg-tb-cream">
                                {img && <img src={img} alt={t(product.title)} className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.06]" loading="lazy" />}
                                {pct > 0 && (
                                    <span className="absolute left-2 top-2 rounded-full bg-tb-red px-2 py-0.5 text-[11px] font-bold text-white shadow-sm">-{pct}%</span>
                                )}
                            </div>
                            <div className="px-1.5 pb-1 pt-2">
                                <p className="line-clamp-1 text-[12px] text-tb-text">{t(product.title)}</p>
                                <div className="mt-1 flex items-baseline gap-1.5">
                                    <span className="font-display text-[17px] font-extrabold tracking-tight text-tb-red">{formatNaira(priceOf(product))}</span>
                                    {wasPriceOf(product) && (
                                        <span className="text-[11px] text-neutral-400 line-through">{formatNaira(wasPriceOf(product))}</span>
                                    )}
                                </div>
                                <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-tb-peach">
                                    <div className="cin-shine h-full rounded-full bg-gradient-to-r from-tb-amber to-tb-red" style={{ width: `${Math.min(92, 35 + pct)}%` }} />
                                </div>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

/* ---------------------------------------------------------------- Collections (bento) */

function CollectionCard({ section }) {
    const products = section.products ?? [];
    const href = section.settings?.link_url || route('products.index');
    const title = t(section.title);
    const subtitle = t(section.subtitle);
    const theme = section.settings?.theme ?? 'rose';
    const themes = {
        rose: { wrap: 'bg-gradient-to-br from-[#FFE0E6] via-[#FFEDE4] to-[#FFF6F0]', title: 'text-tb-red', blob: 'bg-tb-red/25', chip: 'bg-tb-red' },
        ink: { wrap: 'bg-tb-ink', title: 'text-white', blob: 'bg-tb/50', chip: 'bg-tb', sub: 'text-white/60' },
        mint: { wrap: 'bg-gradient-to-br from-[#D9F3E8] via-[#ECF9F2] to-[#FFF8F3]', title: 'text-tb-green', blob: 'bg-tb-green/25', chip: 'bg-tb-green' },
        sky: { wrap: 'bg-gradient-to-br from-[#DDEBFF] via-[#EEF5FF] to-[#F8FAFF]', title: 'text-sky-700', blob: 'bg-sky-500/25', chip: 'bg-sky-600' },
        amber: { wrap: 'bg-gradient-to-br from-[#FFF0C9] via-[#FFF6E0] to-[#FFFBF2]', title: 'text-[#B36B00]', blob: 'bg-tb-amber/35', chip: 'bg-[#E08A00]' },
    }[theme] ?? {};

    if (products.length === 0) return null;

    return (
        <Link href={href} className={`group relative flex flex-col overflow-hidden rounded-3xl p-4 shadow-soft transition duration-300 hover:-translate-y-1 hover:shadow-float ${themes.wrap}`}>
            <div className={`pointer-events-none absolute -right-10 -top-12 h-40 w-40 rounded-full blur-3xl ${themes.blob}`} />
            <div className="relative mb-3 flex items-start justify-between gap-2">
                <div>
                    <h3 className={`font-display text-[18px] font-extrabold tracking-tight ${themes.title}`}>{title}</h3>
                    <p className={`text-[12px] ${themes.sub ?? 'text-neutral-500'}`}>{subtitle}</p>
                </div>
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/85 text-tb-ink shadow-sm transition group-hover:translate-x-0.5">→</span>
            </div>
            <div className="relative mt-auto grid grid-cols-2 gap-2">
                {products.map((p) => {
                    const img = productImage(p);
                    return (
                        <div key={p.id} className="relative aspect-square overflow-hidden rounded-2xl bg-white/70">
                            {img && <img src={img} alt={t(p.title)} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" />}
                            <span className={`absolute bottom-1.5 left-1.5 rounded-full px-2 py-0.5 font-display text-[11px] font-bold text-white shadow-sm ${themes.chip}`}>
                                {formatNaira(priceOf(p))}
                            </span>
                        </div>
                    );
                })}
            </div>
        </Link>
    );
}

/* ---------------------------------------------------------------- Simple sections */

function CouponsSection({ section, coupons }) {
    const { __ } = useTranslation();
    if (coupons.length === 0) return null;

    return (
        <section className="mt-8">
            <SectionHeading eyebrow={tx(section.eyebrow)} title={tx(section.title, __('Coupons for you'))} />
            <div className="no-scrollbar -mx-1.5 flex gap-3 overflow-x-auto px-1.5 pb-1">
                {coupons.map((coupon) => (
                    <div key={coupon.code} className="cin-ticket flex w-64 shrink-0 items-stretch overflow-hidden rounded-2xl bg-white shadow-soft">
                        <div className="cin-gradient flex w-24 shrink-0 flex-col items-center justify-center px-2 text-white">
                            <span className="font-display text-[22px] font-extrabold leading-none">
                                {coupon.type === 'percentage' ? `${coupon.value}%` : formatNaira(coupon.value)}
                            </span>
                            <span className="mt-1 text-[10px] font-semibold uppercase tracking-wider text-white/85">{__('Off')}</span>
                        </div>
                        <div className="flex flex-1 flex-col justify-center border-l-2 border-dashed border-tb-peach px-4 py-3">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-neutral-400">{__('Use code')}</span>
                            <span className="font-display text-[16px] font-extrabold tracking-wide text-tb-ink">{coupon.code}</span>
                            <span className="text-[11px] text-neutral-500">{__('Apply at checkout')}</span>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function ProductGridSection({ section }) {
    const { __ } = useTranslation();
    const products = section.products ?? [];
    if (products.length === 0) return null;

    return (
        <section className="mt-8">
            <SectionHeading eyebrow={tx(section.eyebrow)} title={tx(section.title, __('You might like'))} />

            <div className="cin-reveal grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                {products.map((product) => (
                    <ProductCard key={product.id} product={product} />
                ))}
            </div>

            <div className="mt-8 flex justify-center">
                <Link
                    href={route('products.index')}
                    className="cin-gradient rounded-full px-8 py-3 font-display text-[14px] font-bold text-white shadow-glow transition hover:-translate-y-px hover:brightness-110"
                >
                    {__('View more products')}
                </Link>
            </div>
        </section>
    );
}

function SellCtaSection({ section, isVendor }) {
    const { __ } = useTranslation();
    const href = isVendor ? route('vendor.dashboard') : section.settings?.button_url || route('open-shop');

    return (
        <section className="relative mt-12 overflow-hidden rounded-3xl bg-tb-ink px-5 py-8 text-white shadow-float sm:px-10 sm:py-10">
            <div className="pointer-events-none absolute -right-16 -top-24 h-80 w-80 rounded-full bg-tb/60 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-32 right-1/3 h-72 w-72 rounded-full bg-tb-red/40 blur-3xl" />
            <div className="pointer-events-none absolute -left-10 top-0 h-40 w-40 rounded-full bg-tb-amber/20 blur-3xl" />
            <div className="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                <div className="max-w-xl">
                    {tx(section.eyebrow) && (
                        <p className="font-ui text-[11px] font-bold uppercase tracking-[0.18em] text-tb-amber">{tx(section.eyebrow)}</p>
                    )}
                    <h2 className="mt-1 font-display text-[24px] font-extrabold leading-tight tracking-tight sm:text-[32px]">
                        {tx(section.title, __('Reach buyers across Nigeria with your own store'))}
                    </h2>
                    {tx(section.subtitle) && <p className="mt-2 text-[13px] text-white/70 sm:text-[14px]">{tx(section.subtitle)}</p>}
                </div>
                <Link
                    href={href}
                    className="inline-flex w-fit shrink-0 items-center gap-2 rounded-full bg-white px-6 py-3 font-display text-[14px] font-bold text-tb-ink shadow-float transition hover:-translate-y-px"
                >
                    {isVendor ? __('Vendor Dashboard') : tx(section.settings?.button_label, __('Open a shop'))}
                    <span className="cin-gradient-text">→</span>
                </Link>
            </div>
        </section>
    );
}

function FaqSection({ section }) {
    const { __ } = useTranslation();
    const faqs = (section.settings?.faqs ?? []).filter((faq) => t(faq.question) && t(faq.answer));
    if (faqs.length === 0) return null;

    return (
        <section className="mt-12 grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
            <div>
                {tx(section.eyebrow) && (
                    <p className="font-ui text-[11px] font-bold uppercase tracking-[0.18em] text-tb">{tx(section.eyebrow)}</p>
                )}
                <h2 className="mt-1 font-display text-[22px] font-bold tracking-tight text-tb-ink sm:text-[26px]">
                    {tx(section.title, __('Frequently Asked Questions'))}
                </h2>
                <Link href={route('support.index')} className="mt-3 inline-block text-[13px] font-semibold text-tb hover:underline">
                    {__('Contact support')} →
                </Link>
            </div>
            <div className="space-y-2">
                {faqs.map((faq, i) => (
                    <details key={i} className="group rounded-2xl border border-black/5 bg-white/80 px-4 py-3 shadow-soft open:bg-white">
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-[14px] font-semibold text-tb-ink marker:content-none [&::-webkit-details-marker]:hidden">
                            {t(faq.question)}
                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-tb-soft text-tb transition duration-300 group-open:rotate-45">
                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeWidth={2.4} d="M12 5v14M5 12h14" />
                                </svg>
                            </span>
                        </summary>
                        <p className="mt-2 whitespace-pre-line pr-8 text-[13px] leading-relaxed text-neutral-600">{t(faq.answer)}</p>
                    </details>
                ))}
            </div>
        </section>
    );
}

/* ---------------------------------------------------------------- Page */

/** Consecutive collection sections render side by side as one bento row. */
function groupSections(sections) {
    const blocks = [];
    sections.forEach((section) => {
        const last = blocks[blocks.length - 1];
        if (section.type === 'collection') {
            if (last?.type === 'collection-group') last.sections.push(section);
            else blocks.push({ type: 'collection-group', key: `collections-${section.id}`, sections: [section] });
        } else {
            blocks.push({ type: section.type, key: `section-${section.id}`, section });
        }
    });
    return blocks;
}

export default function Home({ banners = [], categories = [], coupons = [], topPicks = [], sections = [], seo }) {
    const { __ } = useTranslation();
    const { props } = usePage();
    const [showWelcome, setShowWelcome] = useState(!!props.flash?.justRegistered);
    const needsPhoneVerification = !!props.auth?.needsPhoneVerification;
    const isVendor = props.auth?.isVendor ?? false;

    const blocks = useMemo(() => groupSections(sections), [sections]);

    const renderBlock = (block) => {
        const { section } = block;

        switch (block.type) {
            case 'hero': {
                const showAccount = section.settings?.show_account_card !== false;
                return (
                    <div key={block.key} className="cin-reveal">
                        <section className={`grid gap-3 ${showAccount ? 'lg:grid-cols-[minmax(0,1fr)_300px]' : ''}`}>
                            <HeroCarousel banners={banners} section={section} />
                            {showAccount && (
                                <div className="hidden lg:block">
                                    <AccountCard coupons={coupons} />
                                </div>
                            )}
                        </section>
                        {/* Tablet: account card goes full width under the hero (hidden on phones to keep the page short). */}
                        {showAccount && (
                            <div className="mt-6 hidden sm:block lg:hidden">
                                <AccountCard coupons={coupons} horizontal />
                            </div>
                        )}
                    </div>
                );
            }
            case 'categories':
                return <CategoryRail key={block.key} categories={categories} />;
            case 'trust':
                return <TrustStrip key={block.key} section={section} />;
            case 'flash_deals':
                return <FlashDeals key={block.key} section={section} />;
            case 'collection-group': {
                const visible = block.sections.filter((s) => (s.products ?? []).length > 0);
                if (visible.length === 0) return null;
                return (
                    <section key={block.key} className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {visible.map((s) => (
                            <CollectionCard key={s.id} section={s} />
                        ))}
                    </section>
                );
            }
            case 'coupons':
                return <CouponsSection key={block.key} section={section} coupons={coupons} />;
            case 'top_picks':
                return <TopPicksSection key={block.key} topPicks={topPicks} eyebrow={tx(section.eyebrow)} title={tx(section.title)} />;
            case 'product_grid':
                return <ProductGridSection key={block.key} section={section} />;
            case 'sell_cta':
                return <SellCtaSection key={block.key} section={section} isVendor={isVendor} />;
            case 'faq':
                return <FaqSection key={block.key} section={section} />;
            default:
                return null;
        }
    };

    return (
        <StorefrontLayout categories={categories}>
            {needsPhoneVerification && <CompleteProfileModal />}

            {!needsPhoneVerification && showWelcome && <WelcomeModal onClose={() => setShowWelcome(false)} />}

            <SeoHead seo={seo} />

            <div className="mx-auto max-w-marketplace px-1.5 py-4 sm:px-2 sm:py-5">
                <h1 className="sr-only">
                    {__('Shop Chinese-Run Stores in Nigeria — Verified Vendors, Naira Pricing, Fast Delivery')}
                </h1>

                {/* Every block below — order, names, visibility, products and deal prices — is
                    managed in the admin panel under Storefront → Homepage sections. */}
                {blocks.map(renderBlock)}
            </div>
        </StorefrontLayout>
    );
}
