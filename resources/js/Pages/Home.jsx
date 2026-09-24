import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import WelcomeModal from '@/Components/WelcomeModal';
import CompleteProfileModal from '@/Components/CompleteProfileModal';
import TopPicksSection from '@/Components/TopPicksSection';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';

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

function MiniProduct({ product, entry = false, compact = false }) {
    if (!product) return null;
    const image = product.images?.find((img) => img.is_primary) ?? product.images?.[0];
    const imageUrl = image?.enhanced_url ?? image?.original_url;

    const imageClass = entry
        ? 'relative h-[56px] overflow-hidden rounded-[10px] bg-tb-card bg-cover bg-center bg-no-repeat'
        : compact
            ? 'relative h-[78px] w-full overflow-hidden rounded-[10px] bg-tb-card'
            : 'relative aspect-square overflow-hidden rounded-[12px] bg-tb-card';

    return (
        <Link href={route('products.show', product.slug)} className="group flex min-w-0 flex-col gap-0.5">
            <div
                className={imageClass}
                style={entry && imageUrl ? { backgroundImage: `url(${imageUrl})`, backgroundPosition: '50%', backgroundSize: 'cover' } : undefined}
            >
                {!entry && imageUrl ? (
                    <img src={imageUrl} alt={t(product.title)} className="h-full w-full object-cover" loading="lazy" />
                ) : null}
                {entry && !imageUrl ? <span className="sr-only">{t(product.title)}</span> : null}
            </div>
            <span className="mx-auto mt-1 inline-block max-w-full truncate rounded-md bg-tb px-2.5 py-1 font-display text-center text-[11px] font-bold leading-none text-white">
                {formatNaira(product.price)}
            </span>
        </Link>
    );
}

function BannerCarousel({ banners }) {
    const { __ } = useTranslation();
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (banners.length <= 1) return undefined;
        const timer = setInterval(() => setIndex((i) => (i + 1) % banners.length), 4500);
        return () => clearInterval(timer);
    }, [banners.length]);

    if (banners.length === 0) {
        return (
            <div className="flex h-[150px] items-center justify-center rounded-[12px] bg-gradient-to-br from-tb to-[#ff8a3d] px-6 text-white sm:h-[256px]">
                <div>
                    <p className="font-display text-xl font-bold">{__('Welcome to ChinaInNigeria')}</p>
                    <p className="mt-1 font-sans text-sm text-white/90">{__('Chinese-run stores · Unbeatable prices')}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="relative h-[150px] overflow-hidden rounded-[12px] bg-tb-card sm:h-[256px]">
            {banners.map((banner, i) => (
                <a
                    key={banner.id}
                    href={banner.link_url || '#'}
                    className={`absolute inset-0 transition-opacity duration-500 ${i === index ? 'opacity-100' : 'opacity-0'}`}
                >
                    <img src={banner.image_url} alt={t(banner.title)} className="h-full w-full object-cover" />
                    {t(banner.title) && (
                        <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/55 to-transparent px-3 pb-5 pt-8">
                            <h2 className="font-display text-[13px] font-semibold text-white sm:text-[14px]">{t(banner.title)}</h2>
                        </div>
                    )}
                </a>
            ))}
            {banners.length > 1 && (
                <div className="absolute bottom-1.5 left-1/2 flex -translate-x-1/2 gap-1.5">
                    {banners.map((_, i) => (
                        <button
                            key={i}
                            type="button"
                            onClick={() => setIndex(i)}
                            className={`h-[5px] w-[5px] rounded-full ${i === index ? 'bg-white' : 'bg-white/50'}`}
                            aria-label={`Slide ${i + 1}`}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function PromoTile({ title, subtitle, href, children, accent = 'text-tb-text' }) {
    return (
        <div className="business-entry-panel flex min-h-0 flex-col p-2">
            <div className="mb-1.5 flex items-baseline justify-between gap-2">
                <Link href={href} className={`font-display text-[13px] font-semibold ${accent} hover:opacity-80`}>
                    {title}
                </Link>
                {subtitle && <span className="truncate font-sans text-[11px] text-tb-muted">{subtitle}</span>}
            </div>
            <div className="min-h-0 flex-1">{children}</div>
        </div>
    );
}

function UserPanel({ coupons }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const { __ } = useTranslation();
    const hour = new Date().getHours();
    const greeting = hour < 12 ? __('Good morning') : hour < 18 ? __('Good afternoon') : __('Good evening');

    const primaryLinks = [
        {
            label: __('Orders'),
            href: route('orders.index'),
            icon: (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            ),
        },
        {
            label: __('Cart'),
            href: route('cart.index'),
            icon: (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.2 2.4A1 1 0 006.7 17H17" />
                </svg>
            ),
        },
        {
            label: __('Coupons'),
            href: route('products.index'),
            icon: (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            ),
        },
        {
            label: __('Footprint'),
            href: route('products.index'),
            icon: (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
        },
    ];

    const secondaryLinks = [
        {
            label: __('Stores'),
            href: route('stores.index'),
            icon: (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d="M3 9l1-4h16l1 4M4 9v10h4v-5h8v5h4V9M9 9V5h6v4" />
                </svg>
            ),
        },
        {
            label: __('Favourite'),
            href: route('favourites.index'),
            icon: (
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
            ),
        },
        {
            label: __('Open a shop'),
            href: route('open-shop'),
            emphasize: true,
            icon: (
                <svg className="cin-flash-icon h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.7} d="M12 6v6m0 0v6m0-6h6m-6 0H6M4 7h16l-1 12H5L4 7z" />
                </svg>
            ),
        },
    ];

    return (
        <aside className="business-entry-panel flex h-full flex-col gap-1.5 p-2.5">
            <div className="flex flex-col items-center text-center">
                <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-white text-tb-muted">
                    {user ? (
                        <span className="font-display text-lg font-semibold text-tb">{user.name.charAt(0).toUpperCase()}</span>
                    ) : (
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    )}
                </div>
                <p className="mt-1.5 font-sans text-[12px] text-tb-text">
                    {greeting}
                    {user ? `, ${user.name.split(' ')[0]}` : ''}
                </p>
                {!user ? (
                    <Link
                        href={route('login')}
                        className="mt-2 flex h-8 w-full items-center justify-center rounded-lg bg-tb font-display text-[13px] font-semibold text-white hover:bg-tb-hover"
                    >
                        {__('Log in now')}
                    </Link>
                ) : (
                    <Link
                        href={route('orders.index')}
                        className="mt-2 flex h-8 w-full items-center justify-center rounded-full border border-tb font-display text-[13px] font-semibold text-tb hover:bg-white"
                    >
                        {__('My Orders')}
                    </Link>
                )}
            </div>

            <div className="grid grid-cols-4 gap-0.5 border-t border-tb-border pt-2 text-center">
                {primaryLinks.map((item) => (
                    <Link key={item.label} href={item.href} className="flex flex-col items-center gap-0.5 rounded py-0.5 text-tb-muted hover:bg-white hover:text-tb">
                        {item.icon}
                        <span className="font-sans text-[10px]">{item.label}</span>
                    </Link>
                ))}
            </div>

            <div className="grid grid-cols-3 gap-0.5 border-t border-tb-border pt-2 text-center">
                {secondaryLinks.map((item) => (
                    <Link
                        key={item.label}
                        href={item.href}
                        className={`flex flex-col items-center gap-0.5 rounded py-0.5 hover:bg-white ${
                            item.emphasize ? 'text-tb-red hover:text-tb-red' : 'text-tb-muted hover:text-tb'
                        }`}
                    >
                        {item.icon}
                        <span className={`font-sans text-[10px] leading-tight ${item.emphasize ? 'font-semibold' : ''}`}>
                            {item.label}
                        </span>
                    </Link>
                ))}
            </div>

            <div className="mt-auto rounded-[10px] bg-gradient-to-r from-[#FFF4EC] to-[#FFE8D9] p-2">
                <p className="font-display text-[12px] font-semibold text-tb">{__('CIN Credits')}</p>
                <p className="mt-0.5 font-sans text-[10px] leading-snug text-tb-muted">
                    {coupons?.[0]
                        ? __('Use code :code at checkout for extra savings.', { code: coupons[0].code })
                        : __('Save more with marketplace coupons at checkout.')}
                </p>
                <Link href={route('products.index')} className="mt-1 inline-block font-sans text-[11px] font-medium text-tb hover:underline">
                    {__('View All')} →
                </Link>
            </div>
        </aside>
    );
}

export default function Home({
    banners = [],
    categories = [],
    coupons = [],
    dealProducts = [],
    liveProducts = [],
    factoryProducts = [],
    budgetProducts = [],
    recommendedProducts = [],
    topPicks = [],
    seo,
}) {
    const { __ } = useTranslation();
    const { props } = usePage();
    const [refreshKey, setRefreshKey] = useState(0);
    const [showWelcome, setShowWelcome] = useState(
        !!props.flash?.justRegistered,
    );
    const needsPhoneVerification = !!props.auth?.needsPhoneVerification;

    const shuffled = [...recommendedProducts].sort(() => Math.random() - 0.5);

    return (
        <StorefrontLayout categories={categories}>
            {needsPhoneVerification && <CompleteProfileModal />}

            {!needsPhoneVerification && showWelcome && (
                <WelcomeModal onClose={() => setShowWelcome(false)} />
            )}

            <SeoHead seo={seo} />

            <div className="mx-auto max-w-marketplace px-1.5 py-3 sm:px-2">
                {/* Hero marketplace panel */}
                <section className="rounded-[12px] bg-white p-1.5 sm:p-2">
                    <div className="grid gap-2 lg:grid-cols-[160px_minmax(0,1fr)_210px]">
                        <nav className="business-entry-panel hidden py-1 lg:block">
                            <ul className="font-sans text-[12px]">
                                {categories.map((cat) => (
                                    <li key={cat.id}>
                                        <Link
                                            href={route('categories.show', cat.slug)}
                                            className="flex items-center gap-2 px-2.5 py-[6px] text-tb-text hover:bg-white hover:text-tb"
                                        >
                                            <span className="text-tb-muted">
                                                {CATEGORY_ICONS[cat.slug] ?? (
                                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <circle cx="12" cy="12" r="7" strokeWidth="1.6" />
                                                    </svg>
                                                )}
                                            </span>
                                            <span className="truncate">{t(cat.name)}</span>
                                        </Link>
                                    </li>
                                ))}
                                <li>
                                    <Link
                                        href={route('products.index')}
                                        className="flex items-center gap-2 px-2.5 py-[6px] font-medium text-tb hover:bg-white"
                                    >
                                        <span>→</span>
                                        <span>{__('All Products')}</span>
                                    </Link>
                                </li>
                            </ul>
                        </nav>

                        <div className="flex min-w-0 flex-col gap-2">
                            <div className="grid items-stretch gap-2 md:grid-cols-[minmax(0,1.4fr)_minmax(200px,0.75fr)]">
                                <BannerCarousel banners={banners} />

                                <div className="business-entry-panel flex h-[256px] flex-col overflow-visible p-2 pb-2.5">
                                    <div className="mb-1.5 flex shrink-0 items-center justify-between">
                                        <h2 className="font-display text-[13px] font-semibold text-tb-text">{__('Flash Deals')}</h2>
                                        <Link href={route('products.index')} className="font-sans text-[11px] text-tb-muted hover:text-tb">
                                            {__('View All')}
                                        </Link>
                                    </div>
                                    <div className="grid min-h-0 flex-1 grid-cols-2 grid-rows-2 gap-x-2 gap-y-1.5">
                                        {dealProducts.slice(0, 4).map((product) => (
                                            <MiniProduct key={product.id} product={product} compact />
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                                <PromoTile title={__('China Hub')} subtitle={__('#verified · #lagos')} href={route('products.index')} accent="text-tb">
                                    <ul className="space-y-1 font-sans text-[11px] leading-snug text-tb-muted">
                                        <li>· {__('Chinese-run stores across Nigeria')}</li>
                                        <li>· {__('Fast Lagos & Abuja delivery')}</li>
                                        <li>· {__('Secure multi-vendor checkout')}</li>
                                    </ul>
                                </PromoTile>

                                <PromoTile title={__('Live picks')} subtitle={__('Trending now')} href={route('products.index')} accent="text-tb-red">
                                    <div className="grid grid-cols-2 gap-1.5">
                                        {liveProducts.map((p) => (
                                            <MiniProduct key={p.id} product={p} entry />
                                        ))}
                                    </div>
                                </PromoTile>

                                <PromoTile title={__('Factory direct')} subtitle={__('Vendor sourced')} href={route('products.index')} accent="text-[#000000]">
                                    <div className="grid grid-cols-2 gap-1.5">
                                        {factoryProducts.map((p) => (
                                            <MiniProduct key={p.id} product={p} entry />
                                        ))}
                                    </div>
                                </PromoTile>

                                <PromoTile title={__('Low-price zone')} subtitle={__('Budget finds')} href={route('products.index')} accent="text-dark">
                                    <div className="grid grid-cols-2 gap-1.5">
                                        {budgetProducts.map((p) => (
                                            <MiniProduct key={p.id} product={p} entry />
                                        ))}
                                    </div>
                                </PromoTile>
                            </div>
                        </div>

                        <div className="hidden lg:block">
                            <UserPanel coupons={coupons} />
                        </div>
                    </div>

                    <div className="business-entry-panel mt-[10px] grid grid-cols-3 gap-2 p-2 lg:hidden">
                        {categories.slice(0, 6).map((cat) => (
                            <Link
                                key={cat.id}
                                href={route('categories.show', cat.slug)}
                                className="flex flex-col items-center gap-1 rounded-md px-1 py-2 text-center hover:bg-white"
                            >
                                <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-white">
                                    {cat.image_url ? (
                                        <img src={cat.image_url} alt="" className="h-full w-full object-cover" />
                                    ) : (
                                        <span className="text-tb">{t(cat.name).charAt(0)}</span>
                                    )}
                                </div>
                                <span className="line-clamp-1 font-sans text-[11px] text-tb-text">{t(cat.name)}</span>
                            </Link>
                        ))}
                    </div>

                    <div className="mt-[10px] lg:hidden">
                        <UserPanel coupons={coupons} />
                    </div>
                </section>

                <TopPicksSection topPicks={topPicks} />

                {coupons.length > 0 && (
                    <section className="mt-3 flex flex-wrap gap-2">
                        {coupons.map((coupon) => (
                            <div
                                key={coupon.code}
                                className="flex items-center gap-2 rounded-[6px] border border-dashed border-tb bg-tb-card px-3 py-1.5 font-sans text-[12px]"
                            >
                                <span className="font-display font-bold text-tb">{coupon.code}</span>
                                <span className="text-tb-muted">
                                    {coupon.type === 'percentage'
                                        ? `${coupon.value}% off`
                                        : `${formatNaira(coupon.value)} off`}
                                </span>
                            </div>
                        ))}
                    </section>
                )}

                <section className="mt-4">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-display flex items-center gap-1.5 text-[18px] font-semibold text-tb-text">
                            <svg className="h-5 w-5 text-tb" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                            </svg>
                            {__('You might like')}
                        </h2>
                        <button
                            type="button"
                            onClick={() => setRefreshKey((k) => k + 1)}
                            className="flex items-center gap-1 font-sans text-[12px] text-tb-muted hover:text-tb"
                        >
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0014-7M19 5a9 9 0 00-14 7" />
                            </svg>
                            {__('Refresh')}
                        </button>
                    </div>

                    <div key={refreshKey} className="grid grid-cols-2 gap-[10px] sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        {shuffled.map((product) => (
                            <ProductCard key={`${refreshKey}-${product.id}`} product={product} />
                        ))}
                    </div>

                    <div className="mt-6 flex justify-center">
                        <Link
                            href={route('products.index')}
                            className="rounded-full border border-tb-border bg-white px-8 py-2.5 font-sans text-[13px] text-tb-text shadow-card hover:border-tb hover:text-tb"
                        >
                            {__('View more products')}
                        </Link>
                    </div>
                </section>
            </div>
        </StorefrontLayout>
    );
}
