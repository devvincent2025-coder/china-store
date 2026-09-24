<?php

namespace App\Filament\Widgets;

use App\Models\VendorOrder;
use Filament\Widgets\ChartWidget;

class OrderStatusBreakdownChart extends ChartWidget
{
    protected static ?string $heading = 'Fulfillment Status Breakdown';

    // Chart.js fills its container by default, which made this doughnut render enormous inside
    // Filament's full-width widget card — capping the canvas height and disabling
    // maintainAspectRatio (so it isn't then squeezed unnaturally at that fixed height) keeps it a
    // sensible, glanceable size instead.
    protected static ?string $maxHeight = '220px';

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $counts = VendorOrder::query()
            ->selectRaw('fulfillment_status, count(*) as total')
            ->groupBy('fulfillment_status')
            ->pluck('total', 'fulfillment_status');

        $labels = ['processing', 'shipped', 'delivered', 'cancelled'];

        return [
            'datasets' => [
                [
                    'data' => collect($labels)->map(fn ($label) => $counts[$label] ?? 0)->values(),
                    'backgroundColor' => ['#f59e0b', '#3b82f6', '#22c55e', '#ef4444'],
                ],
            ],
            'labels' => collect($labels)->map(fn ($l) => ucfirst($l))->values(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
