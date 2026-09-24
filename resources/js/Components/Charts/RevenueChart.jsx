import { useMemo } from 'react';
import Chart from 'react-apexcharts';
import EmptyState from '@/Components/EmptyState';
import { formatNaira, koboToNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

const ORANGE = '#ea580c';

function formatShortDate(isoDate) {
    const d = new Date(`${isoDate}T00:00:00`);
    if (Number.isNaN(d.getTime())) return isoDate;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

/**
 * Line/area chart of the vendor's revenue over the last N days.
 * `data` is `analytics.revenueOverTime`: [{ date: 'YYYY-MM-DD', revenue: kobo, orders: number }, ...]
 */
export default function RevenueChart({ data = [] }) {
    const { __ } = useTranslation();

    const hasSales = data.some((d) => Number(d.revenue) > 0);

    const series = useMemo(
        () => [
            {
                name: __('Revenue'),
                data: data.map((d) => Number((koboToNaira(d.revenue)).toFixed(2))),
            },
        ],
        [data, __]
    );

    const options = useMemo(
        () => ({
            chart: {
                type: 'area',
                toolbar: { show: false },
                fontFamily: 'inherit',
                zoom: { enabled: false },
            },
            colors: [ORANGE],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.02,
                    stops: [0, 90, 100],
                },
            },
            grid: {
                borderColor: '#e1e0d9',
                strokeDashArray: 3,
                padding: { left: 8, right: 8 },
            },
            xaxis: {
                categories: data.map((d) => formatShortDate(d.date)),
                labels: {
                    style: { colors: '#898781', fontSize: '11px' },
                    rotate: 0,
                    hideOverlappingLabels: true,
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
            },
            yaxis: {
                labels: {
                    style: { colors: '#898781', fontSize: '11px' },
                    formatter: (val) => `₦${Math.round(val).toLocaleString('en-NG')}`,
                },
            },
            tooltip: {
                y: { formatter: (val) => formatNaira(Math.round(val * 100)) },
                x: { formatter: (_, opts) => data[opts.dataPointIndex]?.date ?? '' },
            },
            markers: { size: 0, hover: { size: 5 } },
        }),
        [data]
    );

    if (!data.length || !hasSales) {
        return (
            <EmptyState
                title={__('No sales data yet')}
                description={__('Revenue for the last 30 days will appear here once orders start coming in.')}
            />
        );
    }

    return <Chart options={options} series={series} type="area" height={300} />;
}
