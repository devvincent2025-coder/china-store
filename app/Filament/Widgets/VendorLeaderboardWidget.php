<?php

namespace App\Filament\Widgets;

use App\Models\Vendor;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class VendorLeaderboardWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Vendors by Revenue';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vendor::query()
                    ->withSum(['vendorOrders as revenue' => fn ($query) => $query->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))], 'total')
                    ->withCount('vendorOrders as orders_count')
                    ->orderByDesc('revenue')
            )
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->label('Vendor'),
                Tables\Columns\TextColumn::make('orders_count')->label('Orders'),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state) => '₦'.number_format(($state ?? 0) / 100, 2)),
                Tables\Columns\TextColumn::make('commission_rate')->label('Commission %'),
            ])
            ->paginated([10])
            ->defaultPaginationPageOption(10);
    }
}
