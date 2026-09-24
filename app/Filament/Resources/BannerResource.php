<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::pluck('business_name', 'id'))
                    ->placeholder('Platform (homepage)')
                    ->nullable(),
                Forms\Components\TextInput::make('title.en')->label('Title (English)'),
                Forms\Components\TextInput::make('title.zh')->label('Title (Chinese)'),
                Forms\Components\TextInput::make('title.fr')->label('Title (French)'),
                Forms\Components\TextInput::make('image_url')->label('Image URL')->required()->url(),
                Forms\Components\TextInput::make('link_url')->label('Link URL')->url()->nullable(),
                Forms\Components\Select::make('placement')
                    ->options([
                        'homepage' => 'Homepage',
                        'store' => 'Store',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('ends_at'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('title', 'en') ?: '—'),
                Tables\Columns\TextColumn::make('vendor.business_name')
                    ->label('Vendor')
                    ->placeholder('Platform'),
                Tables\Columns\TextColumn::make('placement'),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->placeholder('—'),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('placement')
                    ->options([
                        'homepage' => 'Homepage',
                        'store' => 'Store',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
