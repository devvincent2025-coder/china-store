<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Generates historical paid orders spread over the last 60 days so the vendor analytics dashboard
 * (VendorAnalyticsService) has real data to chart — revenue over time, top products, sales by
 * state, and order-status mix all read from these records. Bypasses CheckoutService/Korapay
 * entirely (as ProductSeeder does for its own data) since this is backfilled history, not a live
 * checkout — the money math (discount/VAT/shipping/commission/payout) is still computed correctly
 * by hand here so the numbers stay internally consistent.
 */
class OrderSeeder extends Seeder
{
    /** Weighted so Lagos/Abuja (where the seed vendors are based) dominate, like real delivery data would. */
    private const STATE_WEIGHTS = [
        'Lagos' => 35, 'FCT' => 20, 'Ogun' => 10, 'Rivers' => 8, 'Oyo' => 7,
        'Kano' => 6, 'Kaduna' => 5, 'Enugu' => 4, 'Delta' => 3, 'Anambra' => 2,
    ];

    private const CITIES_BY_STATE = [
        'Lagos' => ['Ikeja', 'Lekki', 'Surulere', 'Yaba'],
        'FCT' => ['Wuse', 'Garki', 'Gwarinpa', 'Maitama'],
        'Ogun' => ['Abeokuta', 'Sagamu'],
        'Rivers' => ['Port Harcourt'],
        'Oyo' => ['Ibadan'],
        'Kano' => ['Kano City'],
        'Kaduna' => ['Kaduna City'],
        'Enugu' => ['Enugu City'],
        'Delta' => ['Warri', 'Asaba'],
        'Anambra' => ['Awka', 'Onitsha'],
    ];

    public function run(): void
    {
        $buyers = User::role('buyer')->get();

        if ($buyers->isEmpty()) {
            $this->command?->warn('OrderSeeder: no buyer accounts found — run BuyerSeeder first. Skipping.');

            return;
        }

        $weightedStates = $this->expandWeights(self::STATE_WEIGHTS);

        Vendor::with('products')->where('status', 'active')->each(function (Vendor $vendor) use ($buyers, $weightedStates) {
            $products = $vendor->products()->where('status', 'published')->get();

            if ($products->isEmpty()) {
                return;
            }

            $orderCount = fake()->numberBetween(18, 32);

            for ($i = 0; $i < $orderCount; $i++) {
                $this->createOrder($vendor, $products, $buyers, $weightedStates);
            }

            $this->command?->info("OrderSeeder: {$orderCount} orders for {$vendor->business_name}.");
        });
    }

    private function createOrder(Vendor $vendor, $products, $buyers, array $weightedStates): void
    {
        $buyer = $buyers->random();
        $state = fake()->randomElement($weightedStates);
        $city = fake()->randomElement(self::CITIES_BY_STATE[$state]);

        $lineItems = $products->random(min($products->count(), fake()->numberBetween(1, 3)));
        $subtotal = 0;
        $itemPayloads = [];

        foreach ($lineItems as $product) {
            $quantity = fake()->numberBetween(1, 4);
            $unitPrice = $product->price;
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;

            $itemPayloads[] = [
                'product_id' => $product->id,
                // getTranslations(), not the magic ->title accessor (which resolves to a single
                // plain string in the current locale) — see the identical fix in CheckoutService.
                'product_title_snapshot' => $product->getTranslations('title'),
                'product_image_snapshot' => $product->images->first()?->enhanced_url ?? $product->images->first()?->original_url,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        // ~20% of historical orders carry a coupon discount, matching realistic promo redemption rates.
        $discount = fake()->boolean(20) ? intdiv($subtotal * fake()->numberBetween(5, 20), 100) : 0;
        $afterDiscount = $subtotal - $discount;
        $vatAmount = $vendor->calculateVat($afterDiscount);
        $shippingAmount = $vendor->calculateShippingFee($afterDiscount);
        $total = $afterDiscount + $vatAmount + $shippingAmount;
        $commission = intdiv($afterDiscount * (int) round((float) $vendor->commission_rate * 100), 10000);
        $payout = $total - $commission;

        $paidAt = now()->subDays(fake()->numberBetween(0, 59))->subHours(fake()->numberBetween(0, 23));
        $fulfillmentStatus = fake()->randomElement(['processing', 'processing', 'shipped', 'delivered', 'delivered', 'delivered', 'cancelled']);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'CIN-'.$paidAt->format('Ymd').'-'.Str::upper(Str::random(6)),
            'currency' => 'NGN',
            'original_subtotal' => $subtotal,
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'vat_total' => $vatAmount,
            'shipping_total' => $shippingAmount,
            'total' => $total,
            'payment_reference' => (string) Str::uuid(),
            'payment_status' => 'paid',
            'paid_at' => $paidAt,
            'shipping_address' => [
                'name' => $buyer->name,
                'phone' => $buyer->phone ?? '0'.fake()->numerify('#########'),
                'line1' => fake()->streetAddress(),
                'city' => $city,
                'state' => $state,
            ],
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);

        $vendorOrder = $order->vendorOrders()->create([
            'vendor_id' => $vendor->id,
            'original_subtotal' => $subtotal,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat_amount' => $vatAmount,
            'shipping_amount' => $shippingAmount,
            'total' => $total,
            'commission_amount' => $commission,
            'payout_amount' => $payout,
            'fulfillment_status' => $fulfillmentStatus,
            'tracking_number' => in_array($fulfillmentStatus, ['shipped', 'delivered']) ? strtoupper(Str::random(10)) : null,
            'tracking_carrier' => in_array($fulfillmentStatus, ['shipped', 'delivered']) ? fake()->randomElement(['GIG Logistics', 'DHL', 'Kwik Delivery']) : null,
            'shipped_at' => in_array($fulfillmentStatus, ['shipped', 'delivered']) ? $paidAt->copy()->addHours(fake()->numberBetween(4, 48)) : null,
            'delivered_at' => $fulfillmentStatus === 'delivered' ? $paidAt->copy()->addHours(fake()->numberBetween(48, 120)) : null,
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);

        foreach ($itemPayloads as $payload) {
            $vendorOrder->items()->create($payload);
        }
    }

    /**
     * Expands a {label => weight} map into a flat array suitable for fake()->randomElement(), so
     * heavier-weighted states appear proportionally more often.
     */
    private function expandWeights(array $weights): array
    {
        $expanded = [];

        foreach ($weights as $label => $weight) {
            $expanded = array_merge($expanded, array_fill(0, $weight, $label));
        }

        return $expanded;
    }
}
