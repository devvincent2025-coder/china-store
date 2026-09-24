<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\WebPushChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // laravel-notification-channels/webpush's own service provider only binds WebPush's
        // constructor dependencies — it never registers the 'webpush' string used by every
        // notification's via() (see App\Notifications\Concerns\BuildsDatabasePayload::toWebPush())
        // with Laravel's ChannelManager, so every queued job using it failed with "Driver [webpush]
        // not supported." until this was added.
        Notification::extend('webpush', fn ($app) => $app->make(WebPushChannel::class));

        // super_admin is the one unrestricted role — every sub-admin role (Support/Finance/Ads/KYC/
        // Audit Admin, see database/seeders/AdminSubRolesSeeder) only ever has the specific
        // permissions checked into its row on App\Filament\Resources\RoleResource, so this is the
        // single place that grants a full bypass instead of that role needing every permission
        // listed explicitly (and staying in sync every time a new one is added).
        Gate::before(fn ($user, string $ability) => $user?->hasRole('super_admin', 'web') ? true : null);
    }
}
