<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Resources\CouponResource\RelationManagers\UsagesRelationManager;
use App\Models\Coupon;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::pluck('business_name', 'id'))
                    ->placeholder('Platform-wide')
                    ->nullable(),
                Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                Forms\Components\Select::make('type')
                    ->options([
                        'percentage' => 'Percentage',
                        'flat' => 'Flat (kobo)',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('value')->numeric()->required(),
                Forms\Components\TextInput::make('minimum_spend')->numeric()->default(0)->label('Minimum spend (kobo)'),
                Forms\Components\TextInput::make('usage_limit')->numeric()->nullable(),
                Forms\Components\TextInput::make('used_count')->numeric()->disabled(),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('expires_at'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('vendor.business_name')
                    ->label('Vendor')
                    ->placeholder('Platform-wide'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('value'),
                Tables\Columns\TextColumn::make('used_count')->label('Used'),
                Tables\Columns\TextColumn::make('usage_limit')->label('Limit')->placeholder('∞'),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->placeholder('Never')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'percentage' => 'Percentage',
                        'flat' => 'Flat',
                    ]),
                Tables\Filters\Filter::make('has_usages')
                    ->label('Has been redeemed')
                    ->query(fn ($query) => $query->where('used_count', '>', 0)),
                Tables\Filters\Filter::make('scope')
                    ->form([
                        Forms\Components\Select::make('scope')
                            ->options([
                                'platform' => 'Platform-wide',
                                'vendor' => 'Vendor',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['scope'] ?? null) {
                            'platform' => $query->whereNull('vendor_id'),
                            'vendor' => $query->whereNotNull('vendor_id'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
