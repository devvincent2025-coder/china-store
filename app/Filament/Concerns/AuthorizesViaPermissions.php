<?php

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Illuminate\Support\Str;

/**
 * Gates a Filament Resource's CRUD actions against Spatie permissions named
 * "{action}_{snake_case_model_name}" (e.g. "view_any_vendor", "update_ad_campaign") instead of a
 * Policy class — this app has no per-record ownership logic to enforce for admin-side resources
 * (unlike the buyer/vendor-facing Policies in app/Policies), just "does this sub-admin role have
 * this permission at all". See database/seeders/AdminPermissionSeeder.php for where these
 * permission names are generated and database/seeders/AdminSubRolesSeeder.php for which roles get
 * which ones. super_admin bypasses all of this via the Gate::before in AppServiceProvider::boot().
 *
 * Explicitly checks the 'web' guard (checkPermissionTo(..., 'web')) rather than the ambient
 * $user->can(...)/hasRole(...) — Laravel's own auth middleware (including Filament's) calls
 * Auth::shouldUse($guard) as soon as it authenticates a request, which rewrites
 * config('auth.defaults.guard') to 'admin' for the rest of that request. Spatie resolves ITS
 * default permission guard from that same config value, so an unqualified ->can(...) call made
 * from inside a Filament panel request silently starts looking for guard_name='admin' permissions
 * and finds none, since every permission here was seeded with guard_name='web' (see
 * User::canAccessPanel() for the same fix applied to the panel-login check itself).
 */
trait AuthorizesViaPermissions
{
    public static function canViewAny(): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('view_any_'.static::permissionKey(), 'web');
    }

    public static function canView($record): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('view_'.static::permissionKey(), 'web');
    }

    public static function canCreate(): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('create_'.static::permissionKey(), 'web');
    }

    public static function canEdit($record): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('update_'.static::permissionKey(), 'web');
    }

    public static function canDelete($record): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('delete_'.static::permissionKey(), 'web');
    }

    protected static function permissionKey(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }
}
