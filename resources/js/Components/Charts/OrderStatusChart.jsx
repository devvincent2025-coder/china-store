import { useMemo } from 'react';
import Chart from 'react-apexcharts';
import EmptyState from '@/Components/EmptyState';
import { useTranslation } from '@/hooks/useTranslation';

// Matches the color convention already established in Components/StatusBadge.jsx
// (amber = processing, blue = shipped, green = delivered, red = cancelled).
const STATUS_META = {
    processing: { label: 'Processing', color: '#d97706' },
    shipped: { label: 'Shipped', color: '#2563eb' },
    delivered: { label: 'Delivered', color: '#16a34a' },
    cancelled: { label: 'Cancelled', color: '#dc2626' },
};

/**
 * Donut chart of the vendor's order fulfillment pipeline mix.
 * `data` is `analytics.orderStatusBreakdown`: { processing, shipped, delivered, cancelled }.
 */
export default function OrderStatusChart({ data = {} }) {
    const { __ } = useTranslation();

    const keys = Object.keys(STATUS_META).filter((k) => data[k] !== undefined);
    const total = keys.reduce((sum, k) => sum + (Number(data[k]) || 0), 0);

    const series = keys.map((k) => Number(data[k]) || 0);
    const labels = keys.map((k) => __(STATUS_META[k].label));
    const colors = keys.map((k) => STATUS_META[k].color);

    const options = useMemo(
        () => ({
            chart: { type: 'donut', fontFamily: 'inherit' },
            labels,
            colors,
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
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            total: { show: true, label: __('Orders'), color: '#898781' },
                        },
                    },
                },
            },
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [labels.join(','), colors.join(',')]
    );

    if (!total) {
        return (
            <EmptyState
                title={__('No sales data yet')}
                description={__('The status breakdown of your orders will appear here once you receive orders.')}
            />
        );
    }

    return <Chart options={options} series={series} type="donut" height={300} />;
}
