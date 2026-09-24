<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name.en')->label('Name (English)')->required(),
                Forms\Components\TextInput::make('name.zh')->label('Name (Chinese)'),
                Forms\Components\TextInput::make('name.fr')->label('Name (French)'),
                Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent category')
                    ->options(fn (?Category $record) => Category::query()
                        ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                        ->get()
                        ->mapWithKeys(fn (Category $category) => [$category->id => $category->getTranslation('name', 'en')]))
                    ->searchable()
                    ->nullable(),
                Forms\Components\TextInput::make('image_url')->label('Image URL')->url()->nullable(),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', 'en'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(fn ($record) => $record->parent
                        ? $record->parent->getTranslation('name', 'en')
                        : '—'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products'),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
