import { useMemo, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import KycModal from '@/Components/KycModal';
import VendorKycApprovedModal from '@/Components/VendorKycApprovedModal';
import StatusBadge from '@/Components/StatusBadge';
import EmptyState from '@/Components/EmptyState';
import RevenueChart from '@/Components/Charts/RevenueChart';
import TopProductsChart from '@/Components/Charts/TopProductsChart';
import SalesHeatmap from '@/Components/Charts/SalesHeatmap';
import OrderStatusChart from '@/Components/Charts/OrderStatusChart';
import ExportMenu from '@/Components/ExportMenu';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

// One SVG path + accent color per stat, keyed the same way NAV_ITEMS colors the vendor sidebar —
// gives every metric card a quick visual identity instead of plain text-only tiles.
const STAT_ICONS = {
    products: { color: 'text-blue-600 bg-blue-50', path: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    published: { color: 'text-green-600 bg-green-50', path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    orders: { color: 'text-amber-600 bg-amber-50', path: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' },
    revenue: { color: 'text-orange-600 bg-orange-50', path: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-12a9 9 0 100 18 9 9 0 000-18z' },
    coupons: { color: 'text-pink-600 bg-pink-50', path: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z' },
    average: { color: 'text-teal-600 bg-teal-50', path: 'M9 7h6m-6 4h6m-7 8h8a2 2 0 002-2V7a2 2 0 00-2-2H8a2 2 0 00-2 2v10a2 2 0 002 2z' },
    customers: { color: 'text-indigo-600 bg-indigo-50', path: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-8a4 4 0 11-8 0 4 4 0 018 0zm6 3a4 4 0 11-8 0 4 4 0 018 0z' },
    repeat: { color: 'text-purple-600 bg-purple-50', path: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' },
    alerts: { color: 'text-red-600 bg-red-50', path: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z' },
};

function StatCard({ label, value, accent = false, icon = null }) {
    const iconDef = icon ? STAT_ICONS[icon] : null;

    return (
        <div
            className={`rounded-lg p-4 ${accent ? 'border-orange-200 bg-orange-50' : 'border-neutral-200 bg-white'
                }`}
        >
            <div className="flex items-center justify-between">
                <p className={`text-xs ${accent ? 'text-orange-700' : 'text-neutral-500'}`}>{label}</p>
                {iconDef && (
                    <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-md ${iconDef.color}`}>
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={iconDef.path} />
                        </svg>
                    </span>
                )}
            </div>
            <p className={`mt-2 text-2xl font-bold ${accent ? 'text-orange-700' : 'text-neutral-900'}`}>{value}</p>
        </div>
    );
}

function ChartCard({ title, subtitle = null, children, className = '' }) {
    return (
        <div className={`rounded-lg bg-white p-4 ${className}`}>
            <div className="mb-3">
                <h3 className="text-sm font-semibold text-neutral-800">{title}</h3>
                {subtitle && <p className="text-xs text-neutral-500">{subtitle}</p>}
            </div>
            {children}
        </div>
    );
}

function GrowthBadge({ growthPercent }) {
    const { __ } = useTranslation();

    if (growthPercent === null || growthPercent === undefined) {
        return <span className="text-xs font-semibold text-neutral-400">{__('New')}</span>;
    }

    const positive = growthPercent >= 0;

    return (
        <span
            className={`inline-flex items-center gap-1 text-xs font-semibold ${positive ? 'text-green-600' : 'text-red-600'
                }`}
        >
            <svg
                className={`h-3.5 w-3.5 ${positive ? '' : 'rotate-180'}`}
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
            </svg>
            {Math.abs(growthPercent).toFixed(1)}%
        </span>
    );
}

// Derives a simple "best day of the week" callout purely from the revenueOverTime series
// that's already on the page — no extra backend data required.
function useBestWeekday(revenueOverTime) {
    return useMemo(() => {
        if (!revenueOverTime?.length) return null;

        const totals = {};
        revenueOverTime.forEach(({ date, revenue }) => {
            const d = new Date(`${date}T00:00:00`);
            if (Number.isNaN(d.getTime())) return;
            const weekday = d.toLocaleDateString('en-US', { weekday: 'long' });
            totals[weekday] = (totals[weekday] || 0) + (Number(revenue) || 0);
        });

        const entries = Object.entries(totals).filter(([, total]) => total > 0);
        if (!entries.length) return null;

        entries.sort((a, b) => b[1] - a[1]);
        return { weekday: entries[0][0], revenue: entries[0][1] };
    }, [revenueOverTime]);
}

export default function Dashboard({ vendor, stats, recentOrders, analytics, hasAdCampaign = false, kycJustApproved = false }) {
    const { __ } = useTranslation();
    const { auth } = usePage().props;
    const bestWeekday = useBestWeekday(analytics?.revenueOverTime);
    const [kycOpen, setKycOpen] = useState(false);
    const [showKycCelebration, setShowKycCelebration] = useState(kycJustApproved);

    const kycComplete = !!auth?.user?.email_verified_at
        && !!vendor?.phone_verified_at
        && vendor?.identity_verification_status === 'approved';
    const kycRejected = vendor?.identity_verification_status === 'rejected';

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Vendor Dashboard')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Dashboard')}</h1>

            {/* Rejection gets its own distinct (red, not amber) banner with the actual reason
                right here — the generic "not complete yet" banner below covers every other
                incomplete state (never started, awaiting review, missing phone/email). */}
            {kycRejected ? (
                <div className="mb-6 flex flex-col items-start justify-between gap-3 rounded-lg border border-red-300 bg-red-50 p-4 sm:flex-row sm:items-center">
                    <div className="flex items-start gap-3">
                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p className="text-sm font-semibold text-red-800">{__('Your identity verification (KYC) was not approved')}</p>
                            <p className="text-sm text-red-700">{vendor?.identity_rejection_reason}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setKycOpen(true)}
                        className="shrink-0 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        {__('View Reason & Reupload')}
                    </button>
                </div>
            ) : !kycComplete && (
                <div className="mb-6 flex flex-col items-start justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 sm:flex-row sm:items-center">
                    <div className="flex items-start gap-3">
                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p className="text-sm font-semibold text-amber-800">{__('Complete your identity verification (KYC)')}</p>
                            <p className="text-sm text-amber-700">{__('Verify your email, phone, and ID to enable escrow payouts and make your products visible to buyers.')}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setKycOpen(true)}
                        className="shrink-0 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                    >
                        {__('Complete KYC')}
                    </button>
                </div>
            )}

            <KycModal show={kycOpen} onClose={() => setKycOpen(false)} vendor={vendor} />

            {showKycCelebration && (
                <VendorKycApprovedModal onClose={() => setShowKycCelebration(false)} />
            )}

            {!hasAdCampaign && (
                <div className="mb-6 flex flex-col items-start justify-between gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4 sm:flex-row sm:items-center">
                    <div className="flex items-start gap-3">
                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <div>
                            <p className="text-sm font-semibold text-rose-800">{__('Get more customers — boost your store')}</p>
                            <p className="text-sm text-rose-700">{__('Show up in "Top Picks" on the homepage and other pages buyers browse every day.')}</p>
                        </div>
                    </div>
                    <Link
                        href={route('vendor.ads.create')}
                        className="shrink-0 rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                    >
                        {__('Boost My Store')}
                    </Link>
                </div>
            )}

            <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <StatCard label={__('Total Products')} value={stats.total_products} icon="products" />
                <StatCard label={__('Published')} value={stats.published_products} icon="published" />
                <StatCard label={__('Pending Orders')} value={stats.pending_orders} icon="orders" />
                <StatCard label={__('Total Revenue')} value={formatNaira(stats.total_revenue)} icon="revenue" />
                <StatCard label={__('Active Coupons')} value={stats.active_coupons} icon="coupons" />
            </div>

            {analytics && (
                <div className="mt-10">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-bold text-neutral-900">{__('Business Insights')}</h2>
                        <div className="flex items-center gap-4">
                            {bestWeekday && (
                                <p className="text-sm text-neutral-500">
                                    {__('Best day:')} <span className="font-semibold text-orange-600">{bestWeekday.weekday}</span>{' '}
                                    ({formatNaira(bestWeekday.revenue)} {__('in the last 30 days')})
                                </p>
                            )}
                            <ExportMenu routeName="vendor.exports.report" />
                        </div>
                    </div>

                    {/* Month-over-month revenue + deeper KPI row */}
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="rounded-lg border border-orange-200 bg-white p-4 lg:col-span-1">
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-neutral-500">{__("This Month's Revenue")}</p>
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-orange-50 text-orange-600">
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={STAT_ICONS.revenue.path} />
                                    </svg>
                                </span>
                            </div>
                            <p className="mt-2 text-2xl font-bold text-neutral-900">
                                {formatNaira(analytics.monthOverMonth.current_month)}
                            </p>
                            <div className="mt-1 flex items-center gap-2">
                                <GrowthBadge growthPercent={analytics.monthOverMonth.growth_percent} />
                                <span className="text-xs text-neutral-400">{__('vs last month')}</span>
                            </div>
                        </div>
                        <StatCard label={__('Average Order Value')} value={formatNaira(analytics.summary.average_order_value)} icon="average" />
                        <StatCard label={__('Total Customers')} value={analytics.summary.total_customers} icon="customers" />
                        <StatCard
                            label={__('Repeat Customer Rate')}
                            value={`${Number(analytics.summary.repeat_customer_rate).toFixed(1)}%`}
                            icon="repeat"
                        />
                        <StatCard
                            label={__('Low Stock Alerts')}
                            value={analytics.summary.low_stock_count}
                            accent={analytics.summary.low_stock_count > 0}
                            icon="alerts"
                        />
                    </div>

                    {/* Revenue trend — full width, the most important chart */}
                    <div className="mb-6 grid grid-cols-1 gap-4">
                        <ChartCard title={__('Revenue (Last 30 Days)')} subtitle={__('Daily revenue and order volume')}>
                            <RevenueChart data={analytics.revenueOverTime} />
                        </ChartCard>
                    </div>

                    {/* Product mix + fulfillment pipeline */}
                    <div className="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <ChartCard title={__('Top Products')} subtitle={__('By units sold')}>
                            <TopProductsChart data={analytics.topProducts} />
                        </ChartCard>
                        <ChartCard title={__('Order Status')} subtitle={__('Fulfillment pipeline mix')}>
                            <OrderStatusChart data={analytics.orderStatusBreakdown} />
                        </ChartCard>
                    </div>

                    {/* Location intensity heatmap */}
                    <div className="grid grid-cols-1 gap-4">
                        <ChartCard title={__('Sales by Location')} subtitle={__('Where your paid orders come from, by state')}>
                            <SalesHeatmap data={analytics.salesByLocation} />
                        </ChartCard>
                    </div>
                </div>


            )}


            <div className="rounded-lg bg-white mt-10">
                <div className="flex items-center justify-between border-b border-neutral-100 px-4 py-3">
                    <h2 className="text-sm font-semibold text-neutral-800">{__('Recent Orders')}</h2>
                    <Link href={route('vendor.orders.index')} className="text-sm text-orange-600 hover:underline">
                        {__('View All')} →
                    </Link>
                </div>

                {recentOrders.length === 0 ? (
                    <div className="p-6">
                        <EmptyState title={__('No orders yet')} description={__('Orders will appear here once buyers start purchasing.')} />
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
                            <tr>
                                <th className="px-4 py-2">{__('Order #')}</th>
                                <th className="px-4 py-2">{__('Items')}</th>
                                <th className="px-4 py-2">{__('Total')}</th>
                                <th className="px-4 py-2">{__('Status')}</th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100">
                            {recentOrders.map((vo) => (
                                <tr key={vo.id}>
                                    <td className="px-4 py-3 font-medium text-neutral-800">{vo.order?.order_number}</td>
                                    <td className="px-4 py-3 text-neutral-500">{vo.items?.length ?? 0}</td>
                                    <td className="px-4 py-3">{formatNaira(vo.total)}</td>
                                    <td className="px-4 py-3"><StatusBadge status={vo.fulfillment_status} /></td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={route('vendor.orders.show', vo.id)} className="font-medium text-orange-600 hover:underline">
                                            {__('View')}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

        </VendorLayout>
    );
}
