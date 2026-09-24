import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/useTranslation';

const ICONS = {
    home: (active) => (
        <svg className="h-6 w-6" fill={active ? 'currentColor' : 'none'} viewBox="0 0 24 24" stroke="currentColor" strokeWidth={active ? 0 : 1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 10.5L12 3l9 7.5M5 9.5V21h14V9.5" />
        </svg>
    ),
    shop: (active) => (
        <svg className="h-6 w-6" fill={active ? 'currentColor' : 'none'} viewBox="0 0 24 24" stroke="currentColor" strokeWidth={active ? 0 : 1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 7h12l1 13H5L6 7zM9 7a3 3 0 016 0" />
        </svg>
    ),
    cart: (light) => (
        <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke={light ? '#fff' : 'currentColor'} strokeWidth={1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h2l2.4 12.4a2 2 0 002 1.6h7.2a2 2 0 002-1.6L20 8H6" />
            <circle cx="9" cy="20" r="1.4" fill={light ? '#fff' : 'currentColor'} stroke="none" />
            <circle cx="17" cy="20" r="1.4" fill={light ? '#fff' : 'currentColor'} stroke="none" />
        </svg>
    ),
    orders: (active) => (
        <svg className="h-6 w-6" fill={active ? 'currentColor' : 'none'} viewBox="0 0 24 24" stroke="currentColor" strokeWidth={active ? 0 : 1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-5 8l2 2 4-4" />
        </svg>
    ),
    account: (active) => (
        <svg className="h-6 w-6" fill={active ? 'currentColor' : 'none'} viewBox="0 0 24 24" stroke="currentColor" strokeWidth={active ? 0 : 1.8}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 20c0-3.3 3.6-6 8-6s8 2.7 8 6" />
        </svg>
    ),
};

/**
 * Persistent bottom tab bar, mobile only (`sm:hidden`) — the primary nav once installed as a PWA,
 * meant to make the app feel native rather than like a scaled-down desktop site. StorefrontLayout
 * reserves matching bottom space via `.cin-tabbar-spacer` so this never overlaps page content or
 * the footer.
 *
 * The center tab (Cart) is a raised circular button poking up out of a matching notch cut into the
 * bar itself (see `.cin-tabbar-notch` in app.css — a radial-gradient CSS mask, not an SVG shape, so
 * it never distorts regardless of how wide the screen is) — the "floating action button in a
 * scalloped bar" pattern from Shopee/Temu/Jumia-style shopping apps. Cart earns the raised slot
 * over Shop/Orders/Account since it's the one action a buyer wants reachable from literally
 * anywhere, one thumb-reach away, without needing to register it as visually "just another tab".
 *
 * Structurally: the notch mask is applied to its OWN background layer (`.cin-tabbar-notch`), never
 * to the `nav` that actually holds the tabs/button — masking the nav directly would bite a chunk
 * out of the floating cart button too, since it'd just be another child inside the masked box.
 */
export default function MobileTabBar() {
    const { __ } = useTranslation();
    const { props } = usePage();
    const cartCount = props.cartCount ?? 0;
    const isLoggedIn = !!props.auth?.user;

    const sideTabs = [
        { key: 'home', label: __('Home'), href: route('home'), active: route().current('home') },
        { key: 'shop', label: __('Shop'), href: route('products.index'), active: route().current('products.*') || route().current('categories.*') },
        { key: 'orders', label: __('Orders'), href: isLoggedIn ? route('orders.index') : route('login'), active: route().current('orders.*') },
        { key: 'account', label: __('Account'), href: isLoggedIn ? route('profile.edit') : route('login'), active: route().current('profile.*') || route().current('login') },
    ];

    const cartActive = route().current('cart.*');

    return (
        // No fixed height here — `.cin-tabbar`'s safe-area padding-bottom needs to actually ADD to
        // this box's total height (matching `.cin-tabbar-spacer`'s `calc(4rem + safe-area)`
        // reservation in StorefrontLayout), not shrink `nav`'s own fixed h-16 content area on a
        // notched phone (which border-box padding would do if this div were also height-constrained).
        <div className="cin-tabbar fixed inset-x-0 bottom-0 z-40 sm:hidden">
            <div className="cin-tabbar-notch absolute inset-0 border-t border-neutral-200 bg-white" aria-hidden="true" />

            <nav className="relative flex h-16" aria-label={__('Primary')}>
                {sideTabs.slice(0, 2).map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        className={`relative flex flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-medium ${
                            tab.active ? 'text-orange-600' : 'text-neutral-500'
                        }`}
                    >
                        {ICONS[tab.key](tab.active)}
                        {tab.label}
                    </Link>
                ))}

                {/* Empty center cell — reserves the flex width the notch is cut into; the actual
                    button below is absolutely positioned (relative to this `nav`) so it can float
                    above the bar's own top edge. */}
                <div className="flex-1" aria-hidden="true" />

                {sideTabs.slice(2).map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        className={`relative flex flex-1 flex-col items-center justify-center gap-0.5 text-[11px] font-medium ${
                            tab.active ? 'text-orange-600' : 'text-neutral-500'
                        }`}
                    >
                        {ICONS[tab.key](tab.active)}
                        {tab.label}
                    </Link>
                ))}

                <Link
                    href={route('cart.index')}
                    aria-label={__('Cart')}
                    className={`absolute left-1/2 top-0 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full shadow-lg ring-4 ring-white transition-transform active:scale-95 ${
                        cartActive
                            ? 'bg-gradient-to-br from-orange-500 to-red-600'
                            : 'bg-gradient-to-br from-orange-500 to-orange-600'
                    }`}
                >
                    <span className="relative">
                        {ICONS.cart(true)}
                        {cartCount > 0 && (
                            <span className="absolute -right-2 -top-1.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-600 px-1 text-[9px] font-bold leading-none text-white ring-2 ring-white">
                                {cartCount > 99 ? '99+' : cartCount}
                            </span>
                        )}
                    </span>
                </Link>
            </nav>
        </div>
    );
}
