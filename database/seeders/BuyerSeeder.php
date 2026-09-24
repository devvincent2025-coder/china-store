<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * A handful of plain buyer accounts for testing login/cart/checkout as a customer.
 */
class BuyerSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'buyer']);

        $buyers = [
            ['name' => 'Ada Eze', 'email' => 'ada.buyer@example.com'],
            ['name' => 'Tunde Bakare', 'email' => 'tunde.buyer@example.com'],
            ['name' => 'Ngozi Umeh', 'email' => 'ngozi.buyer@example.com'],
            ['name' => 'Femi Adeyemi', 'email' => 'femi.buyer@example.com'],
        ];

        foreach ($buyers as $b) {
            $user = User::firstOrCreate(
                ['email' => $b['email']],
                [
                    'name' => $b['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'locale' => 'en',
                ]
            );
            $user->assignRole('buyer');
        }
    }
}
