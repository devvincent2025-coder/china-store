<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * The permission vocabulary every Filament admin resource is gated against (see
 * App\Filament\Concerns\AuthorizesViaPermissions) plus a handful of standalone permissions for
 * things that aren't a resource (panel login itself, dashboard widgets, whole pages). Only
 * generates/updates permission ROWS — see AdminSubRolesSeeder for which roles get which of these.
 * Idempotent (firstOrCreate), safe to re-run.
 */
class AdminPermissionSeeder extends Seeder
{
    /**
     * One entry per Filament Resource, keyed by the same snake_case name
     * App\Filament\Concerns\AuthorizesViaPermissions derives from the resource's model class —
     * changing a key here without updating the resource's model would silently stop gating it.
     * The action list only includes actions the resource's pages actually support (e.g. OrderResource
     * has no Create/Edit page, so admins can never be granted "create_order").
     */
    private const RESOURCE_PERMISSIONS = [
        'ad_campaign' => ['view_any', 'view', 'update'],
        'admin_activity_log' => ['view_any'],
        'banner' => ['view_any', 'create', 'update', 'delete'],
        'category' => ['view_any', 'create', 'update', 'delete'],
        'coupon' => ['view_any', 'create', 'update', 'delete'],
        'home_section' => ['view_any', 'create', 'update', 'delete'],
        'invoice' => ['view_any', 'view'],
        'ledger_entry' => ['view_any'],
        'order' => ['view_any', 'view'],
        'product' => ['view_any', 'view', 'create', 'update', 'delete'],
        'product_report' => ['view_any', 'view', 'update'],
        'review' => ['view_any', 'view', 'delete'],
        'role' => ['view_any', 'create', 'update', 'delete'],
        'support_ticket' => ['view_any', 'view', 'update'],
        'user' => ['view_any', 'view', 'update'],
        'vendor' => ['view_any', 'view', 'create', 'update', 'delete'],
        'vendor_order_dispute' => ['view_any', 'view', 'update'],
        'vendor_report' => ['view_any', 'view', 'update'],
    ];

    /**
     * Not tied to a Resource's CRUD actions — access_admin_panel is what actually lets a role log
     * into /admin at all (see User::canAccessPanel()); impersonate_user gates "Log in as" on
     * UserResource and Admin\ImpersonationController; the other two gate a page or widget that has
     * no Resource behind it.
     */
    private const CUSTOM_PERMISSIONS = [
        'access_admin_panel',
        'view_financial_widgets',
        'view_reports_page',
        'manage_settings_page',
        'impersonate_user',
    ];

    public function run(): void
    {
        foreach (self::RESOURCE_PERMISSIONS as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$action}_{$resource}", 'guard_name' => 'web']);
            }
        }

        foreach (self::CUSTOM_PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
