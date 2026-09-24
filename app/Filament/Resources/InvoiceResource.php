<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\InvoiceResource\RelationManagers\PaymentsRelationManager;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only oversight of every invoice vendors generate for out-of-website sales — admin can see
 * them here but cannot create/edit them, since that's the vendor's own dashboard flow (see
 * Http\Controllers\Vendor\InvoiceController).
 */
class InvoiceResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('invoice_number')->disabled(),
                Forms\Components\TextInput::make('vendor.business_name')->label('Vendor')->disabled(),
                Forms\Components\TextInput::make('customer_name')->disabled(),
                Forms\Components\TextInput::make('customer_email')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('total')
                    ->disabled()
                    ->formatStateUsing(fn ($state) => $state !== null ? '₦'.number_format($state / 100, 2) : null),
                Forms\Components\TextInput::make('amount_paid')
                    ->disabled()
                    ->formatStateUsing(fn ($state) => $state !== null ? '₦'.number_format($state / 100, 2) : null),
            ])
            ->columns(2)
            ->disabled();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('customer_name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('total')->formatStateUsing(fn ($state) => '₦'.number_format($state / 100, 2))->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')->formatStateUsing(fn ($state) => '₦'.number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'sent', 'partially_paid' => 'warning',
                        'void' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Paid',
                        'void' => 'Void',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
