<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Independent from vendor/buyer auth: this panel authenticates against its own 'admin'
            // guard (config/auth.php) rather than the storefront's 'web' guard, so an admin's panel
            // session and a buyer/vendor's storefront session are held completely separately, even
            // though both still resolve against the same `users` table (an admin IS a User row,
            // just one whose role gates it into this guard/panel — see User::canAccessPanel()).
            ->authGuard('admin')
            ->login()
            // Every notification class in the app now sends on the 'database' channel alongside
            // mail (see App\Notifications\Concerns\BuildsDatabasePayload) — this turns on Filament's
            // own built-in bell icon so admins see new reports, disputes, tickets, etc. the moment
            // they land, reading from the exact same `notifications` table as the storefront's
            // NotificationBell.jsx does for buyers/vendors.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->brandLogo(asset('images/branding/icon.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.svg'))
            // Same brand tokens as tailwind.config.js's `tb` palette (used throughout the vendor
            // dashboard) — Color::hex() generates Filament's full 50-950 shade scale from each exact
            // hex rather than approximating with a built-in Color::Orange-style preset.
            ->colors([
                'primary' => Color::hex('#FF5000'),
                'danger' => Color::hex('#FF0036'),
                'success' => Color::hex('#00A26A'),
            ])
            // Matches the vendor/storefront font stack (see tailwind.config.js's fontFamily.brand) —
            // BunnyFontProvider (Filament's default) fetches it the same way app.blade.php already
            // loads it for the storefront/vendor SPA, just via its own <link> tag for this panel.
            ->font('Urbanist')
            // Matches VendorLayout.jsx's own `w-56` sidebar (14rem) so both dashboards feel like the
            // same product rather than two different admin tools bolted together.
            ->sidebarWidth('14rem')
            // The vendor dashboard has no dark variant at all — forcing light here keeps every
            // color/border override below correct without needing a parallel dark-mode pass.
            ->darkMode(false)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        /* ============================================================
                           Per-resource sidebar icon accents — one color per resource,
                           matching the same idea VendorLayout.jsx uses for its own nav.
                           ============================================================ */
                        .fi-sidebar-item a[href*="/admin/orders"] svg { color: #16a34a; }
                        .fi-sidebar-item a[href*="/admin/invoices"] svg { color: #0d9488; }
                        .fi-sidebar-item a[href*="/admin/ledger"] svg { color: #059669; }
                        .fi-sidebar-item a[href*="/admin/vendors"] svg { color: #FF5000; }
                        .fi-sidebar-item a[href*="/admin/ad-campaigns"] svg { color: #e11d48; }
                        .fi-sidebar-item a[href*="/admin/vendor-order-disputes"] svg { color: #dc2626; }
                        .fi-sidebar-item a[href*="/admin/product-reports"] svg { color: #e11d48; }
                        .fi-sidebar-item a[href*="/admin/products"] svg { color: #2563eb; }
                        .fi-sidebar-item a[href*="/admin/categories"] svg { color: #7c3aed; }
                        .fi-sidebar-item a[href*="/admin/banners"] svg { color: #9333ea; }
                        .fi-sidebar-item a[href*="/admin/coupons"] svg { color: #db2777; }
                        .fi-sidebar-item a[href*="/admin/reviews"] svg { color: #f59e0b; }
                        .fi-sidebar-item a[href*="/admin/support-tickets"] svg { color: #d97706; }
                        .fi-sidebar-item a[href*="/admin/users"] svg { color: #0284c7; }
                        .fi-sidebar-item a[href*="/admin/roles"] svg { color: #4338ca; }
                        .fi-sidebar-item a[href*="/admin/admin-activity-logs"] svg { color: #6b7280; }

                        /* ============================================================
                           Sidebar — sleek, borderless scroll (VendorLayout.jsx's own
                           aside has no visible scrollbar either), tighter/denser nav
                           items, solid-brand active state instead of Filament's default
                           light tint.
                           ============================================================ */
                        .fi-sidebar-nav {
                            scrollbar-width: none; /* Firefox */
                            -ms-overflow-style: none; /* old Edge/IE */
                        }
                        .fi-sidebar-nav::-webkit-scrollbar {
                            display: none; /* Chrome/Safari */
                        }
                        .fi-sidebar {
                            background-color: #ffffff !important;
                            border-right: 1px solid #EEEEEE;
                            box-shadow: none;
                        }
                        /* Content area (everything right of the sidebar) — same neutral-50 page
                           background VendorLayout.jsx uses, so the sidebar (white) actually reads
                           as a distinct panel instead of blending into the page. */
                        .fi-body {
                            background-color: #fafafa;
                        }
                        .fi-sidebar-item-button {
                            border-radius: 0.375rem; /* rounded-md, matching vendor nav items */
                            font-size: 0.875rem; /* text-sm */
                            font-weight: 500;
                            padding-top: 0.5rem;
                            padding-bottom: 0.5rem;
                        }
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button {
                            background-color: #FF5000;
                            box-shadow: none;
                        }
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button,
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button .fi-sidebar-item-label,
                        .fi-sidebar-item.fi-active .fi-sidebar-item-button svg {
                            color: #fff;
                        }

                        /* ============================================================
                           Cards — flat bordered style (border + no shadow/ring) instead
                           of Filament's default shadow-sm/ring-1, matching every card on
                           the vendor dashboard (e.g. Vendor/Dashboard.jsx's StatCard:
                           "rounded-lg border border-neutral-200 bg-white p-4").
                           ============================================================ */
                        .fi-section,
                        .fi-wi-stats-overview-stat,
                        .fi-ta-ctn {
                            border-radius: 0.5rem; /* rounded-lg */
                            border: 1px solid #e5e5e5; /* neutral-200 */
                            box-shadow: none;
                            --tw-ring-shadow: none;
                        }
                        .fi-wi-stats-overview-stat {
                            padding: 1rem;
                        }

                        /* ============================================================
                           Typography — smaller, denser headings to match the vendor
                           dashboard's "text-xl font-bold" page titles instead of
                           Filament's larger default (text-2xl/text-3xl).
                           ============================================================ */
                        .fi-header-heading {
                            font-size: 1.25rem; /* text-xl */
                            line-height: 1.75rem;
                        }
                        .fi-wi-stats-overview-stat-value {
                            font-size: 1.5rem; /* text-2xl, still prominent but not oversized */
                        }

                        /* ============================================================
                           Buttons — rounded-md instead of Filament's default rounded-lg,
                           matching every button across the vendor dashboard.
                           ============================================================ */
                        .fi-btn {
                            border-radius: 0.375rem;
                        }
                    </style>
                    HTML
                ),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                \App\Filament\Widgets\MarketplaceStatsWidget::class,
                \App\Filament\Widgets\EscrowOverviewWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
