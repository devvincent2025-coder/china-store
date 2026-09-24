<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketplaceStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('view_financial_widgets', 'web');
    }

    protected function getStats(): array
    {
        $totalVendors = Vendor::count();
        $pendingApprovals = Vendor::where('documents_status', 'pending')->count();
        $totalOrders = Order::count();
        $totalRevenueKobo = Order::where('payment_status', 'paid')->sum('total');

        return [
            Stat::make('Total Vendors', $totalVendors),

            Stat::make('Pending Vendor Approvals', $pendingApprovals)
                ->color($pendingApprovals > 0 ? 'warning' : 'success'),

            Stat::make('Total Orders', $totalOrders),

            Stat::make('Total Revenue', '₦'.number_format($totalRevenueKobo / 100, 2))
                ->color('success'),
        ];
    }
}
