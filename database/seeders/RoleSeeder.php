<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Creates the four roles the platform relies on for access control. Must run before any user is
 * assigned a role — super_admin and admin are the only two allowed into the Filament panel (see
 * App\Models\User::canAccessPanel()); vendor and buyer use the Inertia-driven storefront instead.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'vendor', 'buyer'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
