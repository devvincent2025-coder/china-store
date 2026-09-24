import { useMemo, useState } from 'react';
import Chart from 'react-apexcharts';
import EmptyState from '@/Components/EmptyState';
import { formatNaira, koboToNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

// Sequential single-hue ramp (Tailwind's orange scale) — light means "little/no revenue",
// dark means "high revenue", so intensity reads as one continuous magnitude channel.
const RAMP = [
    { from: 0, to: 0, color: '#f4f4f2', name: 'None' },
    { from: 0.01, to: 20, color: '#fed7aa', name: 'Low' },
    { from: 20.01, to: 40, color: '#fdba74', name: 'Fair' },
    { from: 40.01, to: 60, color: '#fb923c', name: 'Good' },
    { from: 60.01, to: 80, color: '#ea580c', name: 'Great' },
    { from: 80.01, to: 100, color: '#9a3412', name: 'Best' },
];

/**
 * Single-series ApexCharts heatmap showing where (by Nigerian state) the vendor makes money.
 * `data` is `analytics.salesByLocation`: [{ state, orders, revenue }, ...] sorted by revenue desc.
 * This is a location-INTENSITY grid, not a geographic map.
 */
export default function SalesHeatmap({ data = [] }) {
    const { __ } = useTranslation();
    const [metric, setMetric] = useState('revenue'); // 'revenue' | 'orders'

    const hasData = data.length > 0;

    const max = useMemo(() => Math.max(1, ...data.map((d) => Number(d[metric]) || 0)), [data, metric]);

    const series = useMemo(
        () => [
            {
                name: metric === 'revenue' ? __('Revenue') : __('Orders'),
                data: data.map((d) => ({
                    x: d.state,
                    y: max ? Math.round(((Number(d[metric]) || 0) / max) * 100) : 0,
                    raw: d,
                })),
            },
        ],
        [data, metric, max, __]
    );

    const options = useMemo(
        () => ({
            chart: { type: 'heatmap', toolbar: { show: false }, fontFamily: 'inherit' },
            plotOptions: {
                heatmap: {
                    radius: 4,
                    colorScale: { ranges: RAMP },
                },
            },
            dataLabels: { enabled: false },
            xaxis: {
                labels: { style: { colors: '#898781', fontSize: '11px' } },
            },
            yaxis: { show: false },
            grid: { padding: { left: 8, right: 8 } },
            legend: { show: false },
            tooltip: {
                custom: ({ seriesIndex, dataPointIndex, w }) => {
                    const point = w.config.series[seriesIndex].data[dataPointIndex];
                    const raw = point.raw;
                    return `<div class="px-3 py-2 text-xs">
                        <div class="font-semibold text-neutral-800">${raw.state}</div>
                        <div class="text-neutral-500">${raw.orders} ${__('orders')}</div>
                        <div class="text-neutral-500">${formatNaira(raw.revenue)}</div>
                    </div>`;
                },
            },
        }),
        [__]
    );

    if (!hasData) {
        return (
            <EmptyState
                title={__('No sales data yet')}
                description={__('Once you receive paid orders, this will show which states are buying from you.')}
            />
        );
    }

    return (
        <div>
            <div className="mb-3 flex justify-end gap-1">
                <button
                    type="button"
                    onClick={() => setMetric('revenue')}
                    className={`rounded-md px-2.5 py-1 text-xs font-medium ${
                        metric === 'revenue' ? 'bg-orange-600 text-white' : 'bg-neutral-100 text-neutral-600'
                    }`}
                >
                    {__('Revenue')}
                </button>
                <button
                    type="button"
                    onClick={() => setMetric('orders')}
                    className={`rounded-md px-2.5 py-1 text-xs font-medium ${
                        metric === 'orders' ? 'bg-orange-600 text-white' : 'bg-neutral-100 text-neutral-600'
                    }`}
                >
                    {__('Orders')}
                </button>
            </div>
            <Chart options={options} series={series} type="heatmap" height={Math.max(120, data.length * 34)} />
        </div>
    );
}
