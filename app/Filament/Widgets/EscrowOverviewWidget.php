<?php

namespace App\Filament\Widgets;

use App\Models\VendorOrder;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * At-a-glance view of money currently moving through escrow platform-wide — see
 * App\Services\Escrow\EscrowService for how each state transitions.
 */
class EscrowOverviewWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('view_financial_widgets', 'web');
    }

    protected function getStats(): array
    {
        $holding = VendorOrder::where('escrow_status', 'holding')->sum('payout_amount');
        $releasing = VendorOrder::where('escrow_status', 'releasing')->sum('payout_amount');
        $released = VendorOrder::where('escrow_status', 'released')->sum('payout_amount');
        $refunded = VendorOrder::whereIn('escrow_status', ['refund_pending', 'refunded'])->sum('total');
        $openDisputes = \App\Models\VendorOrderDispute::where('status', 'open')->count();

        return [
            Stat::make('Held in Escrow', '₦'.number_format($holding / 100, 2))
                ->description('Awaiting delivery confirmation or return window')
                ->color('info'),

            Stat::make('Releasing', '₦'.number_format($releasing / 100, 2))
                ->description('Payout initiated, awaiting bank confirmation')
                ->color('warning'),

            Stat::make('Released to Vendors', '₦'.number_format($released / 100, 2))
                ->color('success'),

            Stat::make('Refunded to Buyers', '₦'.number_format($refunded / 100, 2))
                ->color('danger'),

            Stat::make('Open Disputes', $openDisputes)
                ->color($openDisputes > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.vendor-order-disputes.index')),
        ];
    }
}
