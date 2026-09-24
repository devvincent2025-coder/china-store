<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\VendorReportResource\Pages;
use App\Models\VendorReport;
use App\Services\Vendor\VendorSuspensionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A buyer reporting a VENDOR/store as a whole (scam, poor service, IP violation, etc.) — see
 * ProductReportResource for the equivalent that flags one specific listing instead. Unlike a
 * product report, there's no report-count auto-action here: suspending a vendor's whole account
 * is always a manual, investigated admin decision (see Services\Vendor\VendorSuspensionService).
 */
class VendorReportResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = VendorReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'People';

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
                Forms\Components\TextInput::make('vendor.business_name')->label('Vendor')->disabled(),
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
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Reported by')->placeholder('Guest'),
                Tables\Columns\TextColumn::make('open_reports_on_vendor')
                    ->label('Open Reports (this vendor)')
                    ->state(fn (VendorReport $record): int => $record->vendor?->reports()->where('status', 'open')->count() ?? 0)
                    ->badge()
                    ->color(fn (int $state): string => $state >= 3 ? 'danger' : 'gray'),
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
                static::suspendVendorAction(),
                static::dismissAction(),
            ])
            ->bulkActions([]);
    }

    public static function suspendVendorAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('suspendVendor')
            ->label('Suspend Vendor')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (VendorReport $record): bool => $record->status === 'open' && $record->vendor?->status !== 'suspended')
            ->requiresConfirmation()
            ->modalDescription('The vendor will be notified by email and in-app. Their storefront and dashboard become unreachable immediately.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (shown to the vendor)')
                    ->required()
                    ->default(fn (VendorReport $record) => $record->description)
                    ->columnSpanFull(),
            ])
            ->action(function (VendorReport $record, array $data) {
                app(VendorSuspensionService::class)->suspend($record->vendor, auth()->user(), $data['reason']);
                $record->update(['status' => 'resolved']);

                Notification::make()->title('Vendor suspended and report resolved')->success()->send();
            });
    }

    public static function dismissAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('dismiss')
            ->label('Dismiss')
            ->icon('heroicon-o-check')
            ->color('gray')
            ->visible(fn (VendorReport $record): bool => $record->status === 'open')
            ->requiresConfirmation()
            ->action(fn (VendorReport $record) => $record->update(['status' => 'resolved']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorReports::route('/'),
            'view' => Pages\ViewVendorReport::route('/{record}'),
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
