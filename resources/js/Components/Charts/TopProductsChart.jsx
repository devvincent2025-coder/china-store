import { useMemo } from 'react';
import Chart from 'react-apexcharts';
import EmptyState from '@/Components/EmptyState';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

// Fixed-order categorical palette (validated for CVD-safe adjacency), used for identity
// encoding — one hue per product slice, never reassigned based on rank/filter changes.
const CATEGORICAL = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];

function truncate(title, max = 22) {
    if (!title) return 'Untitled';
    return title.length > max ? `${title.slice(0, max - 1)}…` : title;
}

/**
 * Donut chart of the vendor's top-selling products by quantity.
 * `data` is `analytics.topProducts`: [{ product_id, title, quantity, revenue }, ...] (max 5).
 */
export default function TopProductsChart({ data = [] }) {
    const { __ } = useTranslation();

    const hasSales = data.some((d) => Number(d.quantity) > 0);

    const series = useMemo(() => data.map((d) => Number(d.quantity) || 0), [data]);
    const labels = useMemo(() => data.map((d) => truncate(d.title)), [data]);

    const options = useMemo(
        () => ({
            chart: { type: 'donut', fontFamily: 'inherit' },
            labels,
            colors: CATEGORICAL,
            legend: {
                position: 'bottom',
                fontSize: '12px',
                labels: { colors: '#52514e' },
            },
            dataLabels: {
                enabled: true,
                formatter: (val) => `${val.toFixed(0)}%`,
                style: { fontSize: '11px' },
            },
            stroke: { width: 2, colors: ['#fcfcfb'] },
            tooltip: {
                y: {
                    formatter: (val, { seriesIndex }) => {
                        const item = data[seriesIndex];
                        return `${val} ${__('sold')} · ${formatNaira(item?.revenue ?? 0)}`;
                    },
                },
            },
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: __('Units sold'),
                                color: '#898781',
                            },
                        },
                    },
                },
            },
        }),
        [labels, data, __]
    );

    if (!data.length || !hasSales) {
        return (
            <EmptyState
                title={__('No sales data yet')}
                description={__('Your best-selling products will show up here once you make some sales.')}
            />
        );
    }

    return <Chart options={options} series={series} type="donut" height={300} />;
}
