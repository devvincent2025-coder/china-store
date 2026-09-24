<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class RevenueOverTimeChart extends ChartWidget
{
    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('view_financial_widgets', 'web');
    }

    protected static ?string $heading = 'Revenue — Last 30 Days';

    // Same sizing fix as OrderStatusBreakdownChart — keeps every chart on the dashboard/reports
    // page at a consistent, sensible height rather than stretching to fill its card.
    protected static ?string $maxHeight = '220px';

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
        ];
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $totalsByDay = Order::query()
            ->where('payment_status', 'paid')
            ->where('paid_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(paid_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₦)',
                    'data' => $days->map(fn ($day) => round(($totalsByDay[$day] ?? 0) / 100, 2))->values(),
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $days->map(fn ($day) => \Carbon\Carbon::parse($day)->format('M j'))->values(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
