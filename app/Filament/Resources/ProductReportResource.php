<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\ProductReportResource\Pages;
use App\Models\ProductReport;
use App\Services\Product\ProductTakedownService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductReportResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = ProductReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Catalog';

    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::where('status', 'open')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('product.sku')->label('Product SKU')->disabled(),
                Forms\Components\TextInput::make('user.name')->label('Reported by')->disabled(),
                Forms\Components\TextInput::make('reason')->disabled(),
                Forms\Components\Textarea::make('description')->disabled()->columnSpanFull(),
                Forms\Components\TextInput::make('status')->disabled(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.title')
                    ->label('Product')
                    ->formatStateUsing(fn ($record) => $record->product?->getTranslation('title', 'en'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.vendor.business_name')->label('Vendor'),
                Tables\Columns\TextColumn::make('user.name')->label('Reported by')->placeholder('Guest'),
                Tables\Columns\TextColumn::make('open_reports_on_product')
                    ->label('Open Reports (this product)')
                    ->state(fn (ProductReport $record): int => $record->product?->reports()->where('status', 'open')->count() ?? 0)
                    ->badge()
                    ->color(fn (int $state): string => $state >= (int) \App\Models\Setting::get('product_report_flag_threshold', 5) ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('reason')->badge(),
                Tables\Columns\TextColumn::make('description')->limit(60),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'open' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['open' => 'Open', 'resolved' => 'Resolved']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                static::takeDownAction(),
                static::dismissAction(),
            ])
            ->bulkActions([]);
    }

    public static function takeDownAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('takeDown')
            ->label('Take Down Product')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (ProductReport $record): bool => $record->status === 'open' && $record->product?->taken_down_at === null)
            ->requiresConfirmation()
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (shown to the vendor)')
                    ->required()
                    ->default(fn (ProductReport $record) => $record->description)
                    ->columnSpanFull(),
            ])
            ->action(function (ProductReport $record, array $data) {
                app(ProductTakedownService::class)->takeDown($record->product, $data['reason'], auth()->user());
                $record->update(['status' => 'resolved']);

                Notification::make()->title('Product taken down and report resolved')->success()->send();
            });
    }

    public static function dismissAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('dismiss')
            ->label('Dismiss')
            ->icon('heroicon-o-check')
            ->color('gray')
            ->visible(fn (ProductReport $record): bool => $record->status === 'open')
            ->requiresConfirmation()
            ->action(fn (ProductReport $record) => $record->update(['status' => 'resolved']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductReports::route('/'),
            'view' => Pages\ViewProductReport::route('/{record}'),
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
}
