<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\AdminActivityLogResource\Pages;
use App\Models\AdminActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A read-only audit trail — who approved which vendor, who resolved which dispute, who suspended
 * which account, who changed which platform setting. See App\Models\AdminActivityLog::record().
 */
class AdminActivityLogResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = AdminActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity Log';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('adminUser.name')->label('Admin'),
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('description')->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('admin_user_id')
                    ->relationship('adminUser', 'name')
                    ->label('Admin'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminActivityLogs::route('/'),
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
