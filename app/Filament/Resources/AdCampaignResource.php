<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\AdCampaignResource\Pages;
use App\Models\AdCampaign;
use App\Services\Ads\AdCampaignService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Admin oversight of the vendor "boost your store" advertising feature — see
 * App\Services\Ads\AdCampaignService for the full lifecycle. A paid campaign sits in
 * pending_approval until approved here; this is the "did we actually get paid and is this banner
 * appropriate" checkpoint before it ever shows to a buyer.
 */
class AdCampaignResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = AdCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Store Boosts (Ads)';

    public static function getNavigationBadge(): ?string
    {
        $count = AdCampaign::where('status', 'pending_approval')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Campaign')
                    ->schema([
                        Forms\Components\Placeholder::make('vendor')
                            ->label('Vendor')
                            ->content(fn (?AdCampaign $record) => $record?->vendor?->business_name ?? '—'),
                        Forms\Components\Placeholder::make('banner')
                            ->label('Banner')
                            ->content(fn (?AdCampaign $record) => $record?->banner_url
                                ? new \Illuminate\Support\HtmlString('<img src="'.e($record->banner_url).'" style="max-width:320px;border-radius:8px;">')
                                : 'No banner set.'),
                        Forms\Components\TextInput::make('duration_days')->disabled(),
                        Forms\Components\TextInput::make('status')->disabled(),
                        Forms\Components\TextInput::make('amount_kobo')
                            ->label('Amount paid (₦)')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2) : '0.00'),
                        Forms\Components\Textarea::make('rejection_reason')->disabled()->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Metrics')
                    ->schema([
                        Forms\Components\TextInput::make('impressions_count')->label('Impressions')->disabled(),
                        Forms\Components\TextInput::make('clicks_count')->label('Clicks')->disabled(),
                        Forms\Components\Placeholder::make('ctr')
                            ->label('Click-through rate')
                            ->content(fn (?AdCampaign $record) => ($record?->clickThroughRate() ?? 0).'%'),
                        Forms\Components\Placeholder::make('avg_dwell')
                            ->label('Avg. time on store page')
                            ->content(fn (?AdCampaign $record) => ($record?->averageDwellSeconds() ?? 0).'s'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending_payment', 'pending_approval' => 'warning',
                        'paused' => 'gray',
                        'rejected', 'expired', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('duration_days')->label('Days'),
                Tables\Columns\TextColumn::make('amount_kobo')
                    ->label('Paid')
                    ->formatStateUsing(fn ($state) => '₦'.number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('impressions_count')->label('Impr.'),
                Tables\Columns\TextColumn::make('clicks_count')->label('Clicks'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_payment' => 'Pending payment',
                        'pending_approval' => 'Pending approval',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'rejected' => 'Rejected',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                static::approveAction(),
                static::rejectAction(),
            ])
            ->bulkActions([]);
    }

    /**
     * $actionClass lets this same definition serve both the table's ->actions([...]) (needs
     * Filament\Tables\Actions\Action, the default) and a page's getHeaderActions() (needs
     * Filament\Actions\Action — a sibling class, not a subclass, of the table one) — see
     * ViewAdCampaign::getHeaderActions().
     */
    public static function approveAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (AdCampaign $record): bool => $record->status === 'pending_approval')
            ->requiresConfirmation()
            ->action(function (AdCampaign $record) {
                app(AdCampaignService::class)->approve($record, auth()->user());

                \App\Models\AdminActivityLog::record('ad_campaign.approved', $record, "Approved ad campaign for {$record->vendor->business_name}");

                Notification::make()->title('Campaign approved')->success()->send();
            });
    }

    public static function rejectAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (AdCampaign $record): bool => $record->status === 'pending_approval')
            ->form([
                Forms\Components\Textarea::make('reason')->label('Rejection reason')->required()->columnSpanFull(),
            ])
            ->action(function (AdCampaign $record, array $data) {
                app(AdCampaignService::class)->reject($record, auth()->user(), $data['reason']);

                \App\Models\AdminActivityLog::record('ad_campaign.rejected', $record, "Rejected ad campaign for {$record->vendor->business_name}: {$data['reason']}");

                Notification::make()->title('Campaign rejected')->danger()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdCampaigns::route('/'),
            'view' => Pages\ViewAdCampaign::route('/{record}'),
        ];
    }
}
