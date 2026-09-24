<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Platform-wide promo codes for the homepage hero and checkout testing.
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME20',
                'type' => 'percentage',
                'value' => 20,
                'minimum_spend' => 5_000_00,
                'usage_limit' => 500,
            ],
            [
                'code' => 'NEWARRIVAL15',
                'type' => 'percentage',
                'value' => 15,
                'minimum_spend' => 3_000_00,
                'usage_limit' => 300,
            ],
            [
                'code' => 'FLASH5000',
                'type' => 'flat',
                'value' => 5_000_00,
                'minimum_spend' => 20_000_00,
                'usage_limit' => 200,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(
                ['code' => $coupon['code']],
                [
                    'vendor_id' => null,
                    'type' => $coupon['type'],
                    'value' => $coupon['value'],
                    'minimum_spend' => $coupon['minimum_spend'],
                    'usage_limit' => $coupon['usage_limit'],
                    'used_count' => 0,
                    'starts_at' => now()->subDay(),
                    'expires_at' => now()->addMonths(2),
                    'is_active' => true,
                ]
            );
        }
    }
}
