<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\EscrowOverviewWidget;
use App\Filament\Widgets\MarketplaceStatsWidget;
use App\Filament\Widgets\OrderStatusBreakdownChart;
use App\Filament\Widgets\RevenueOverTimeChart;
use App\Filament\Widgets\VendorLeaderboardWidget;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * A dedicated insights dashboard, separate from the default Filament Dashboard page — revenue over
 * time, fulfillment breakdown, escrow exposure, and a vendor leaderboard, so an admin gets a fuller
 * picture than the four headline numbers on the main Dashboard.
 */
class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Insights';

    protected static string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('view_reports_page', 'web');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('admin.exports.report', ['format' => 'pdf']))
                ->openUrlInNewTab(),
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(route('admin.exports.report', ['format' => 'xlsx']))
                ->openUrlInNewTab(),
            Actions\Action::make('exportGoogleMerchant')
                ->label('Google Merchant Feed')
                ->icon('heroicon-o-shopping-bag')
                ->color('gray')
                ->url(route('admin.exports.google-merchant'))
                ->openUrlInNewTab(),
        ];
    }

    public function getWidgets(): array
    {
        return [
            MarketplaceStatsWidget::class,
            EscrowOverviewWidget::class,
            RevenueOverTimeChart::class,
            OrderStatusBreakdownChart::class,
            VendorLeaderboardWidget::class,
        ];
    }
}
