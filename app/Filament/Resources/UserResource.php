<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Models\AdminActivityLog;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Oversight of every account on the platform — buyers, vendor-owning users, and fellow admins —
 * in one place. Vendor-specific business data (documents, bank details) stays on VendorResource;
 * this resource is about the ACCOUNT: identity, role, and whether it's allowed to log in.
 */
class UserResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('email')->email()->required(),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->options(Role::pluck('name', 'id')),
                Forms\Components\Placeholder::make('is_suspended')
                    ->label('Account status')
                    ->content(fn (?User $record) => $record?->is_suspended ? 'Suspended' : 'Active')
                    ->visible(fn (?User $record) => $record !== null),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->placeholder('—'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(',')
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin', 'admin' => 'danger',
                        'vendor' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_suspended')
                    ->label('Suspended')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Role'),
                Tables\Filters\TernaryFilter::make('is_suspended')->label('Suspended'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                static::impersonateAction(),
                static::suspendAction(),
                static::unsuspendAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function impersonateAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('impersonate')
            ->label('Log in as')
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('gray')
            // Never offer this for a suspended account (login itself would be blocked anyway — see
            // EnsureUserIsNotSuspended), for any account that can reach the admin panel itself
            // (super_admin or any role with 'access_admin_panel' — not just the two legacy roles),
            // or unless the viewer themselves has 'impersonate_user' (Admin\ImpersonationController
            // re-checks all of this server-side too, but hiding it here avoids a confusing 403
            // click-through).
            ->visible(fn (User $record): bool => ! $record->is_suspended
                && ! $record->hasRole('super_admin', 'web')
                && ! $record->checkPermissionTo('access_admin_panel', 'web')
                && $record->id !== Filament::auth()->id()
                && (Filament::auth()->user()->hasRole('super_admin', 'web') || Filament::auth()->user()->checkPermissionTo('impersonate_user', 'web')))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record) => "You'll be logged in as {$record->name} ({$record->email}) until you click \"Return to Admin\".")
            ->url(fn (User $record) => route('admin.impersonate.start', $record))
            ->openUrlInNewTab(false);
    }

    public static function suspendAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('suspend')
            ->label('Suspend')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (User $record): bool => ! $record->is_suspended && $record->id !== auth()->id())
            ->requiresConfirmation()
            ->form([
                Forms\Components\Textarea::make('reason')->label('Reason')->required(),
            ])
            ->action(function (User $record, array $data) {
                $record->update([
                    'is_suspended' => true,
                    'suspension_reason' => $data['reason'],
                    'suspended_at' => now(),
                ]);

                $record->notify(new \App\Notifications\AccountSuspended($data['reason']));

                AdminActivityLog::record('user.suspended', $record, "Suspended {$record->email}: {$data['reason']}");

                Notification::make()->title('User suspended')->success()->send();
            });
    }

    public static function unsuspendAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('unsuspend')
            ->label('Unsuspend')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (User $record): bool => $record->is_suspended)
            ->requiresConfirmation()
            ->action(function (User $record) {
                $record->update(['is_suspended' => false, 'suspension_reason' => null, 'suspended_at' => null]);

                $record->notify(new \App\Notifications\AccountUnsuspended);

                AdminActivityLog::record('user.unsuspended', $record, "Unsuspended {$record->email}");

                Notification::make()->title('User unsuspended')->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
