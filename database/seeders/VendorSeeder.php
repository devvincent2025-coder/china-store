<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorStore;
use App\Services\Seeding\ProductImageSearchService;
use App\Support\Korapay\SandboxTestAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Creates 4 fully verified/approved vendor accounts (user + vendor + vendor_store each) so the
 * multi-vendor cart/checkout and Korapay split payment flow can be exercised end to end locally
 * without going through the real document review / bank verification / Korapay provisioning steps.
 *
 * Self-sufficient: defensively (re)creates the 'vendor' role in case this runs before RoleSeeder.
 */
class VendorSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'vendor']);

        $images = app(ProductImageSearchService::class);
        $categories = Category::all()->keyBy(fn ($c) => $c->getTranslation('name', 'en'));

        $vendors = [
            [
                'name' => 'Chen Wei',
                'email' => 'chenwei.vendor@example.com',
                'business_name' => 'Golden Dragon Electronics',
                'store_name' => ['en' => 'Golden Dragon Electronics', 'zh' => '金龙电子', 'fr' => 'Golden Dragon Électronique'],
                'category' => 'Electronics',
                'city' => 'Lagos',
                'state' => 'Lagos',
                // Demo values chosen to exercise all three shipping fee modes across the seed
                // vendors, so the discount/VAT/shipping breakdown is visibly non-zero in local testing.
                'vat_rate' => 7.50,
                'shipping_fee_type' => 'both',
                'shipping_fee_fixed_amount' => 150000, // ₦1,500
                'shipping_fee_percentage' => 1.50,
            ],
            [
                'name' => 'Amaka Obi',
                'email' => 'amakaobi.vendor@example.com',
                'business_name' => 'Sunrise Fashion House',
                'store_name' => ['en' => 'Sunrise Fashion House', 'zh' => '日出时尚屋', 'fr' => 'Maison de Mode Sunrise'],
                'category' => 'Fashion',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'vat_rate' => 0,
                'shipping_fee_type' => 'fixed',
                'shipping_fee_fixed_amount' => 200000, // ₦2,000 flat
                'shipping_fee_percentage' => 0,
            ],
            [
                'name' => 'Li Na',
                'email' => 'lina.vendor@example.com',
                'business_name' => 'Jade Beauty Emporium',
                'store_name' => ['en' => 'Jade Beauty Emporium', 'zh' => '翡翠美妆坊', 'fr' => 'Emporium de Beauté Jade'],
                'category' => 'Beauty',
                'city' => 'Abuja',
                'state' => 'FCT',
                'vat_rate' => 7.50,
                'shipping_fee_type' => 'percentage',
                'shipping_fee_fixed_amount' => 0,
                'shipping_fee_percentage' => 5.00,
            ],
            [
                'name' => 'Ibrahim Musa',
                'email' => 'ibrahimmusa.vendor@example.com',
                'business_name' => 'Home Comfort Traders',
                'store_name' => ['en' => 'Home Comfort Traders', 'zh' => '家居舒适商行', 'fr' => 'Négociants Home Comfort'],
                'category' => 'Home & Kitchen',
                'city' => 'Abuja',
                'state' => 'FCT',
                // Free shipping, no VAT — demonstrates a vendor who's configured neither fee.
                'vat_rate' => 0,
                'shipping_fee_type' => 'fixed',
                'shipping_fee_fixed_amount' => 0,
                'shipping_fee_percentage' => 0,
            ],
        ];

        foreach ($vendors as $v) {
            $user = User::firstOrCreate(
                ['email' => $v['email']],
                [
                    'name' => $v['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'locale' => 'en',
                ]
            );
            $user->assignRole('vendor');

            $vendor = Vendor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $v['business_name'],
                    'contact_email' => $v['email'],
                    'contact_phone' => '0'.fake()->numerify('#########'),
                    'verification_documents' => null,
                    'documents_status' => 'approved',
                    'documents_reviewed_at' => now()->subDays(14),
                    // Korapay's own documented NGN sandbox test account — always resolves and
                    // disburses successfully against Korapay's test API. A random fake() account
                    // number here (the old behavior) made every payout/escrow-release test fail
                    // with "Invalid account.", since Korapay's sandbox only recognizes its own
                    // documented test accounts. See App\Support\Korapay\SandboxTestAccounts for the
                    // full set (success/failed/invalid) if a test specifically needs one of those.
                    'bank_code' => SandboxTestAccounts::NGN_SUCCESS['bank_code'],
                    'bank_name' => 'United Bank For Africa',
                    'account_number' => SandboxTestAccounts::NGN_SUCCESS['account_number'],
                    'resolved_account_name' => strtoupper($v['business_name']),
                    'bank_verification_status' => 'verified',
                    'bank_verified_at' => now()->subDays(14),
                    'bank_verification_response' => ['status' => true, 'message' => 'Account resolved successfully'],
                    'korapay_subaccount_id' => 'SUB_'.Str::random(10),
                    'korapay_subaccount_created_at' => now()->subDays(14),
                    'commission_rate' => 10.00,
                    'vat_rate' => $v['vat_rate'],
                    'shipping_fee_type' => $v['shipping_fee_type'],
                    'shipping_fee_fixed_amount' => $v['shipping_fee_fixed_amount'],
                    'shipping_fee_percentage' => $v['shipping_fee_percentage'],
                    'status' => 'active',
                ]
            );

            $category = $categories->get($v['category']);

            $this->command?->info('Fetching store images for: '.$v['store_name']['en']);

            VendorStore::updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'category_id' => $category?->id,
                    'slug' => Str::slug($v['store_name']['en']),
                    'name' => $v['store_name'],
                    'description' => [
                        'en' => 'A verified marketplace store bringing quality goods from trusted suppliers straight to your door.',
                        'zh' => '一家经过认证的市场店铺，将来自可信供应商的优质商品直接送到您家门口。',
                        'fr' => 'Une boutique de marché vérifiée qui vous apporte des produits de qualité de fournisseurs fiables.',
                    ],
                    'logo_url' => $images->find($v['category'].' store shop logo', 0, 'retail store'),
                    'banner_url' => $images->findWide($v['category'].' storefront shopping', 0, 'marketplace store'),
                    'address_line' => fake()->streetAddress(),
                    'city' => $v['city'],
                    'state' => $v['state'],
                    'country' => 'NG',
                ]
            );
        }
    }
}
