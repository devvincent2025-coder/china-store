<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\AdminActivityLog;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Moderation for buyer product reviews — admin can't edit review content (that would misrepresent
 * the buyer's own words) but can remove reviews that are abusive, spam, or otherwise violate the
 * Terms & Conditions.
 */
class ReviewResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user.name')->label('Buyer')->disabled(),
                Forms\Components\TextInput::make('product.sku')->label('Product SKU')->disabled(),
                Forms\Components\TextInput::make('vendor.business_name')->label('Vendor')->disabled(),
                Forms\Components\TextInput::make('rating')->disabled(),
                Forms\Components\Textarea::make('comment')->disabled()->columnSpanFull(),
            ])
            ->columns(2)
            ->disabled();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')->label('Product')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Buyer'),
                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state)),
                Tables\Columns\TextColumn::make('comment')->limit(60)->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rating')
                    ->options(array_combine(range(1, 5), range(1, 5))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(fn (Review $record) => AdminActivityLog::record('review.deleted', null, "Deleted review #{$record->id} by {$record->user?->email}")),
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
            'index' => Pages\ListReviews::route('/'),
            'view' => Pages\ViewReview::route('/{record}'),
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
