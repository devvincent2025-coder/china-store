<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The five sub-admin roles requested for the admin panel, each pre-loaded with a sensible starter
 * permission set — an existing super_admin can still add/remove individual permissions afterwards
 * via the Roles resource's checkbox matrix (App\Filament\Resources\RoleResource), this just saves
 * them from starting every new role from zero. Also re-syncs 'admin' (legacy, kept for backward
 * compatibility with accounts created before this system existed) to every permission that exists,
 * and 'super_admin' likewise — though super_admin never actually needs to check permissions, since
 * it bypasses all of them via the Gate::before in AppServiceProvider::boot().
 *
 * Run AdminPermissionSeeder first — this only assigns permissions that already exist.
 */
class AdminSubRolesSeeder extends Seeder
{
    private const ROLE_PERMISSIONS = [
        'Support Admin' => [
            'access_admin_panel',
            'view_any_support_ticket', 'view_support_ticket', 'update_support_ticket',
            'view_any_vendor_order_dispute', 'view_vendor_order_dispute', 'update_vendor_order_dispute',
            'view_any_review', 'view_review',
            'view_any_user', 'view_user',
            'view_any_order', 'view_order',
            'view_any_vendor_report', 'view_vendor_report', 'update_vendor_report',
            'impersonate_user',
        ],
        'Finance Admin' => [
            'access_admin_panel',
            'view_any_invoice', 'view_invoice',
            'view_any_ledger_entry',
            'view_any_order', 'view_order',
            'view_financial_widgets',
            'view_reports_page',
        ],
        'Ads/Campaigns Admin' => [
            'access_admin_panel',
            'view_any_ad_campaign', 'view_ad_campaign', 'update_ad_campaign',
            'view_any_banner', 'create_banner', 'update_banner', 'delete_banner',
            'view_any_coupon', 'create_coupon', 'update_coupon', 'delete_coupon',
            // Homepage merchandising: sections, featured products and their deal prices.
            'view_any_home_section', 'create_home_section', 'update_home_section', 'delete_home_section',
        ],
        'KYC Admin' => [
            'access_admin_panel',
            'view_any_vendor', 'view_vendor', 'update_vendor',
            'view_any_vendor_report', 'view_vendor_report', 'update_vendor_report',
        ],
        'Audit Admin' => [
            'access_admin_panel',
            'view_any_admin_activity_log',
            'view_reports_page',
            'view_any_user', 'view_user',
            'view_any_vendor', 'view_vendor',
            'view_any_order', 'view_order',
            'view_any_invoice', 'view_invoice',
            'view_any_vendor_report', 'view_vendor_report',
        ],
    ];

    public function run(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        $allPermissions = Permission::where('guard_name', 'web')->pluck('name');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions($allPermissions);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])->syncPermissions($allPermissions);
    }
}
