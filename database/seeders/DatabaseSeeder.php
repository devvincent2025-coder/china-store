<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            VendorSeeder::class,
            ProductSeeder::class,
            CouponSeeder::class,
            BannerSeeder::class,
            BuyerSeeder::class,
            OrderSeeder::class,
            AdminPermissionSeeder::class,
            AdminSubRolesSeeder::class,
        ]);

        // User::factory(10)->create();

        // The one Filament login for local dev/demo — assigns 'admin' so it can pass
        // User::canAccessPanel() and reach /admin to review/approve vendors.
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@chinainnigeria.com',
        ])->assignRole('admin');

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assignRole('buyer');
    }
}
