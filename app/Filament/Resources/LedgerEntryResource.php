<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\LedgerEntryResource\Pages;
use App\Models\LedgerEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only reconciliation ledger — every kobo that has moved through escrow (a sale credited, the
 * platform's commission, a payout attempt, a refund) as one append-only row per vendor, with its
 * own pending/success/failed lifecycle. See App\Services\Ledger\LedgerService for how rows get
 * here; nothing on this resource writes back to the underlying VendorOrder/escrow state, which
 * stays owned by EscrowService.
 */
class LedgerEntryResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = LedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Ledger & Payouts';

    protected static ?string $modelLabel = 'ledger entry';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sale' => 'success',
                        'payout' => 'info',
                        'commission' => 'gray',
                        'refund' => 'danger',
                        'adjustment' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state): string => ($state >= 0 ? '+' : '-').'₦'.number_format(abs($state) / 100, 2))
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->toggleable()->copyable(),
                Tables\Columns\TextColumn::make('description')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('vendorOrder.order.order_number')->label('Order')->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'sale' => 'Sale',
                        'commission' => 'Commission',
                        'payout' => 'Payout',
                        'refund' => 'Refund',
                        'adjustment' => 'Adjustment',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'business_name')
                    ->searchable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgerEntries::route('/'),
        ];
    }
}
