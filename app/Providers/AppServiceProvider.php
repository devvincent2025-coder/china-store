<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use NotificationChannels\WebPush\WebPushChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One deal-price lookup per request/job — see DealPricing's doc comment.
        $this->app->scoped(\App\Services\Pricing\DealPricing::class);
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

        // Powers the buyer-facing AI ordering API (routes/api.php) — a buyer approves one of
        // these two scopes for an AI tool (Claude, a ChatGPT Custom GPT Action, ...) on the OAuth
        // consent screen below, never both by default, so "let it look things up" and "let it
        // spend money on my behalf" stay two separate, revocable decisions.
        Passport::tokensCan([
            'orders:place' => 'Search products, manage your cart, and place orders on your behalf',
            'orders:read' => 'View your past and current orders',
        ]);

        // The consent screen a buyer sees at GET /oauth/authorize right after logging in — see
        // resources/views/oauth/authorize.blade.php. Without this, Passport falls back to a view
        // name ('passport::authorize') this app never publishes, so /oauth/authorize would 500.
        Passport::authorizationView('oauth.authorize');
    }
}
