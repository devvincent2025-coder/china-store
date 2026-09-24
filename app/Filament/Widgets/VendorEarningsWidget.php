<?php

namespace App\Filament\Widgets;

use App\Models\Vendor;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Attached to OrderResource's list page (see OrderResource\Pages\ListOrders::getHeaderWidgets())
 * so an admin viewing orders can immediately see how much each vendor sold and how much commission
 * the platform earned from them, without cross-referencing individual orders by hand.
 */
class VendorEarningsWidget extends BaseWidget
{
    protected static ?string $heading = 'Vendor Earnings & Platform Commission';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vendor::query()
                    ->withSum(['vendorOrders as gross_sales' => fn ($query) => $query->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))], 'total')
                    ->withSum(['vendorOrders as commission_earned' => fn ($query) => $query->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))], 'commission_amount')
                    ->withSum(['vendorOrders as net_payout' => fn ($query) => $query->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))], 'payout_amount')
                    ->withCount(['vendorOrders as orders_count' => fn ($query) => $query->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))])
                    ->orderByDesc('gross_sales')
            )
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->label('Vendor'),
                Tables\Columns\TextColumn::make('orders_count')->label('Paid Orders'),
                Tables\Columns\TextColumn::make('gross_sales')
                    ->label('Gross Sales')
                    ->formatStateUsing(fn ($state) => '₦'.number_format(($state ?? 0) / 100, 2)),
                Tables\Columns\TextColumn::make('commission_earned')
                    ->label('Our Commission')
                    ->formatStateUsing(fn ($state) => '₦'.number_format(($state ?? 0) / 100, 2))
                    ->color('success'),
                Tables\Columns\TextColumn::make('net_payout')
                    ->label('Vendor Net Payout')
                    ->formatStateUsing(fn ($state) => '₦'.number_format(($state ?? 0) / 100, 2)),
            ])
            ->paginated([10, 25, 50]);
    }
}
