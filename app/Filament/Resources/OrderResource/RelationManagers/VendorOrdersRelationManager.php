<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\VendorOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class VendorOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorOrders';

    protected static ?string $title = 'Vendor Orders';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor'),
                Tables\Columns\TextColumn::make('coupon.code')
                    ->label('Coupon')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('fulfillment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'delivered' => 'success',
                        'shipped' => 'info',
                        'processing' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => '₦'.number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('payout_amount')
                    ->label('Payout')
                    ->formatStateUsing(fn ($state) => '₦'.number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('tracking_carrier')->label('Carrier')->placeholder('—'),
                Tables\Columns\TextColumn::make('tracking_number')->label('Tracking #')->placeholder('—'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('viewItems')
                    ->label('View items')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Vendor order items')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(fn (VendorOrder $record) => [
                        \Filament\Infolists\Components\Placeholder::make('items')
                            ->label('')
                            ->content(function () use ($record) {
                                $rows = $record->items->map(function ($item) {
                                    $title = is_array($item->product_title_snapshot)
                                        ? ($item->product_title_snapshot['en'] ?? reset($item->product_title_snapshot))
                                        : $item->product_title_snapshot;

                                    return sprintf(
                                        '%s — qty %d @ ₦%s = ₦%s',
                                        e($title),
                                        $item->quantity,
                                        number_format($item->unit_price / 100, 2),
                                        number_format($item->line_total / 100, 2)
                                    );
                                });

                                if ($rows->isEmpty()) {
                                    return 'No items.';
                                }

                                return new HtmlString($rows->implode('<br>'));
                            }),
                    ]),
            ])
            ->recordUrl(null)
            ->paginated(false);
    }
}
