import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import SeoHead from '@/Components/SeoHead';
import ProductCard from '@/Components/ProductCard';
import Pagination from '@/Components/Pagination';
import EmptyState from '@/Components/EmptyState';
import { t } from '@/utils/translatable';
import { useTranslation } from '@/hooks/useTranslation';
import { formatNaira } from '@/utils/money';
import { formatDeliveryEstimate } from '@/utils/deliveryEstimate';

function StoreBannerCarousel({ banners }) {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (banners.length <= 1) return;
        const timer = setInterval(() => setIndex((i) => (i + 1) % banners.length), 5000);
        return () => clearInterval(timer);
    }, [banners.length]);

    if (banners.length === 0) return null;

    return (
        <div className="relative mb-6 aspect-[16/6] w-full overflow-hidden rounded-lg bg-neutral-200">
            {banners.map((banner, i) => (
                <a
                    key={banner.id}
                    href={banner.link_url || '#'}
                    className={`absolute inset-0 transition-opacity duration-700 ${i === index ? 'opacity-100' : 'opacity-0'}`}
                >
                    <img src={banner.image_url} alt={t(banner.title)} className="h-full w-full object-cover" />
                </a>
            ))}
        </div>
    );
}

function CouponStrip({ coupons }) {
    const { __ } = useTranslation();

    if (!coupons || coupons.length === 0) return null;

    return (
        <div className="mb-6 flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold text-neutral-500">{__('Coupons for this store:')}</span>
            {coupons.map((coupon) => (
                <div
                    key={coupon.code}
                    className="flex items-center gap-2 rounded-md border border-dashed border-orange-300 bg-orange-50 px-3 py-1 text-xs"
                >
                    <span className="font-bold text-orange-700">{coupon.code}</span>
                    <span className="text-orange-600">
                        {coupon.type === 'percentage' ? `${coupon.value}% off` : `${formatNaira(coupon.value)} off`}
                        {coupon.minimum_spend > 0 && ` · ${__('min.')} ${formatNaira(coupon.minimum_spend)}`}
                    </span>
                </div>
            ))}
        </div>
    );
}

/**
 * Reports the VENDOR/store as a whole (scam, poor service, IP violation, etc.) — separate from
 * reporting one specific product (see the "Report this product" button on Products/Show.jsx).
 * Same collapsed-ghost-button-to-inline-form pattern as that one.
 */
function ReportVendorButton({ store }) {
    const { __ } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        reason: 'poor_service',
        description: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('vendors.report', store.slug), {
            preserveScroll: true,
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="mb-6 flex items-center gap-1 text-xs text-neutral-400 hover:text-red-500"
            >
                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 3v18m0-18h11l-1.5 4.5L14 12H3" />
                </svg>
                {__('Report this store')}
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="mb-6 space-y-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
            <p className="text-xs font-semibold text-neutral-700">{__('Report this store')}</p>
            <select
                value={data.reason}
                onChange={(e) => setData('reason', e.target.value)}
                className="w-full rounded-md border-neutral-300 text-xs"
            >
                <option value="scam">{__('Scam / never delivers')}</option>
                <option value="fake_products">{__('Sells fake/counterfeit products')}</option>
                <option value="poor_service">{__('Poor service')}</option>
                <option value="ip_violation">{__('Intellectual property violation')}</option>
                <option value="other">{__('Other')}</option>
            </select>
            <textarea
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                rows={2}
                placeholder={__('Briefly describe the issue')}
                className="w-full rounded-md border-neutral-300 text-xs"
            />
            {errors.description && <p className="text-xs text-red-600">{errors.description}</p>}
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                >
                    {__('Submit Report')}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="px-3 py-1.5 text-xs text-neutral-500">
                    {__('Cancel')}
                </button>
            </div>
        </form>
    );
}

/**
 * When a buyer arrives here via a "Top Picks" ad visit (/go/{token} redirects here with
 * ?ref={token}), this reports how long they stayed via navigator.sendBeacon — fired on tab close/
 * navigation-away, which a normal fetch() can't reliably do since the page is already unloading by
 * the time it would run. See App\Http\Controllers\AdController::ping().
 */
function useAdDwellTracking() {
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const adToken = params.get('ref');
        if (!adToken) return;

        const startedAt = Date.now();

        const reportDwell = () => {
            const seconds = Math.round((Date.now() - startedAt) / 1000);
            if (seconds < 1) return;

            const url = route('go.ping', adToken);
            const payload = JSON.stringify({ seconds });

            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));
            } else {
                fetch(url, { method: 'POST', body: payload, headers: { 'Content-Type': 'application/json' }, keepalive: true });
            }
        };

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') reportDwell();
        });
        window.addEventListener('pagehide', reportDwell);

        return () => {
            window.removeEventListener('pagehide', reportDwell);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
}

export default function Show({ store, products, banners, onVacation = false, vacationMessage = null, storeSuspended = false, coupons = [], seo }) {
    const { __ } = useTranslation();
    const description = t(store.description);

    useAdDwellTracking();

    return (
        <StorefrontLayout>
            <SeoHead seo={seo} />

            {/* Store banner header — makes it feel like a branded shop */}
            <div className="border-b border-neutral-200 bg-gradient-to-r from-neutral-900 to-neutral-700">
                {store.banner_url && (
                    <div className="h-32 w-full overflow-hidden opacity-70 sm:h-48">
                        <img src={store.banner_url} alt="" className="h-full w-full object-cover" />
                    </div>
                )}
                <div className="mx-auto -mt-10 max-w-7xl px-4 pb-6 sm:px-6 lg:px-8">
                    <div className="flex items-end gap-4">
                        <div className="h-20 w-20 shrink-0 overflow-hidden rounded-full border-4 border-white bg-white sm:h-24 sm:w-24">
                            {store.logo_url ? (
                                <img src={store.logo_url} alt={t(store.name)} className="h-full w-full object-cover" />
                            ) : (
                                <div className="flex h-full items-center justify-center bg-orange-100 text-2xl font-bold text-orange-600">
                                    {t(store.name).charAt(0)}
                                </div>
                            )}
                        </div>
                        <div className="pb-1">
                            <h1 className="text-lg font-bold text-white sm:text-2xl">{t(store.name)}</h1>
                            {store.city && (
                                <p className="text-sm text-neutral-300">{store.city}{store.state ? `, ${store.state}` : ''}</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {description && (
                    <p className="mb-6 max-w-2xl text-sm text-neutral-600">{description}</p>
                )}

                {onVacation && (
                    <div className="mb-6 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4">
                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p className="text-sm font-semibold text-amber-800">{__('This store is temporarily closed')}</p>
                            <p className="text-sm text-amber-700">{vacationMessage || __("This vendor is on a short break and isn't taking new orders right now. Check back soon.")}</p>
                        </div>
                    </div>
                )}

                {storeSuspended && (
                    <div className="mb-6 flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 p-4">
                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 105.636 5.636a9 9 0 0012.728 12.728zM5.636 5.636l12.728 12.728" />
                        </svg>
                        <div>
                            <p className="text-sm font-semibold text-red-800">{__('This store is no longer available')}</p>
                            <p className="text-sm text-red-700">{__('This vendor is not currently taking new orders.')}</p>
                        </div>
                    </div>
                )}

                {formatDeliveryEstimate(store.vendor?.delivery_days_min, store.vendor?.delivery_days_max, __) && (
                    <div className="mb-6 flex items-center gap-2 text-sm text-neutral-600">
                        <svg className="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>{formatDeliveryEstimate(store.vendor.delivery_days_min, store.vendor.delivery_days_max, __)}</span>
                    </div>
                )}

                <CouponStrip coupons={coupons} />

                <ReportVendorButton store={store} />

                <StoreBannerCarousel banners={banners} />

                <h2 className="mb-4 text-lg font-bold text-neutral-900">{__('Products from this store')}</h2>

                {products.data.length === 0 ? (
                    <EmptyState
                        title={onVacation ? __('This store is temporarily closed') : __('No products yet')}
                        description={onVacation ? (vacationMessage || __("This vendor is on a short break and isn't taking new orders right now. Check back soon.")) : __("This vendor hasn't listed any products.")}
                    />
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                        <Pagination paginator={products} />
                    </>
                )}
            </div>
        </StorefrontLayout>
    );
}
