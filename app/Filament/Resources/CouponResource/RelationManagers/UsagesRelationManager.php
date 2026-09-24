<?php

namespace App\Filament\Resources\CouponResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Answers "which orders used this coupon?" — one row per redemption (App\Models\CouponUsage),
 * which is a durable log independent of whatever the coupon's own used_count counter says right
 * now. This is the admin-side complement to the vendor's own Orders page "Coupon applied" filter
 * and column (Vendor\OrderController::index() / Pages/Vendor/Orders/Index.jsx).
 */
class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Orders That Used This Coupon';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('vendorOrder.order.order_number')
                    ->label('Order #')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user.name')->label('Buyer')->placeholder('—'),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state) => '₦'.number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('created_at')->label('Redeemed at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->paginated([10, 25, 50]);
    }
}
