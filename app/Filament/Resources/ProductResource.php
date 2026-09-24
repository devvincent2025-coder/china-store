<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Product\ProductTakedownService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::pluck('business_name', 'id'))
                    ->disabled(),
                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'slug')
                    ->disabled(),
                Forms\Components\TextInput::make('title.en')->label('Title (English)')->disabled(),
                Forms\Components\TextInput::make('sku')->disabled(),
                Forms\Components\TextInput::make('stock')->numeric()->disabled(),
                Forms\Components\Placeholder::make('price_tiers_summary')
                    ->label('Bulk pricing tiers')
                    ->columnSpanFull()
                    ->content(function (?Product $record) {
                        if (! $record) {
                            return '—';
                        }

                        if ($record->variants->isNotEmpty()) {
                            $lines = $record->variants
                                ->filter(fn ($variant) => $variant->priceTiers->isNotEmpty())
                                ->map(fn ($variant) => $variant->label('en').': '.static::formatTierList($variant->priceTiers));

                            return $lines->isEmpty() ? 'No per-variant tiers configured.' : $lines->implode("\n");
                        }

                        return $record->priceTiers->isEmpty() ? 'None configured — sold at a flat price.' : static::formatTierList($record->priceTiers);
                    }),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_featured'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('title', 'en'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('vendor.business_name')
                    ->label('Vendor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(fn ($record) => $record->category
                        ? $record->category->getTranslation('name', 'en')
                        : '—'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => '₦'.number_format($state / 100, 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')->sortable(),
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Columns\ToggleColumn::make('is_featured'),
                Tables\Columns\IconColumn::make('taken_down_at')
                    ->label('Taken Down')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('danger')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('flagged_at')
                    ->label('Auto-Flagged')
                    ->boolean()
                    ->trueIcon('heroicon-o-flag')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::pluck('business_name', 'id')),
                Tables\Filters\TernaryFilter::make('flagged_at')
                    ->label('Auto-flagged')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                static::takeDownAction(),
                static::restoreAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function takeDownAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('takeDown')
            ->label('Take Down')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Product $record): bool => $record->taken_down_at === null)
            ->requiresConfirmation()
            ->modalDescription('The vendor will be notified by email and in-app of the reason you give.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (shown to the vendor)')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Product $record, array $data) {
                app(ProductTakedownService::class)->takeDown($record, $data['reason'], auth()->user());

                Notification::make()->title('Product taken down')->success()->send();
            });
    }

    public static function restoreAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('restore')
            ->label('Restore')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->visible(fn (Product $record): bool => $record->taken_down_at !== null)
            ->requiresConfirmation()
            ->action(function (Product $record) {
                app(ProductTakedownService::class)->restore($record);

                Notification::make()->title('Product restored')->success()->send();
            });
    }

    /**
     * "1-9: ₦1,000.00, 10-49: ₦900.00, 50+: ₦800.00" — read-only admin oversight only; a product's
     * tiers are set by the vendor (Vendor/Products/Create.jsx, Edit.jsx), never edited here.
     */
    private static function formatTierList($tiers): string
    {
        return $tiers->sortBy('min_quantity')->map(function ($tier) {
            $range = $tier->max_quantity ? "{$tier->min_quantity}-{$tier->max_quantity}" : "{$tier->min_quantity}+";

            return $range.': ₦'.number_format($tier->price_kobo / 100, 2);
        })->implode(', ');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
