<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_vat_rate_is_seven_point_five_percent_with_no_setting_row(): void
    {
        $this->assertSame(7.50, Setting::vatRate());
    }

    public function test_vendor_calculates_vat_from_the_platform_setting_not_its_own_column(): void
    {
        Setting::set('default_vat_rate', '10.00');
        $vendor = Vendor::factory()->create();

        // 10% of ₦100,000 (in kobo) = ₦10,000.
        $this->assertSame(1_000_000, $vendor->calculateVat(10_000_000));
    }

    public function test_checkout_applies_the_global_vat_rate_to_every_vendor_regardless_of_vendor_config(): void
    {
        Setting::set('default_vat_rate', '7.50');

        $user = User::factory()->create();
        $vendorA = Vendor::factory()->verified()->create();
        $vendorB = Vendor::factory()->verified()->create();
        $productA = Product::factory()->for($vendorA)->create(['price' => 1_000_000, 'stock' => 10]);
        $productB = Product::factory()->for($vendorB)->create(['price' => 2_000_000, 'stock' => 10]);

        $this->actingAs($user);
        $this->post(route('cart.store'), ['product_id' => $productA->id, 'quantity' => 1]);
        $this->post(route('cart.store'), ['product_id' => $productB->id, 'quantity' => 1]);

        $cart = Cart::first();
        \App\Services\Checkout\CheckoutService::class;
        $checkout = app(\App\Services\Checkout\CheckoutService::class);

        $result = $checkout->checkout(
            $user,
            $cart,
            ['name' => 'A', 'phone' => '1', 'line1' => 'x', 'city' => 'Lagos', 'state' => 'Lagos'],
            [],
            'https://example.test',
        );

        $order = $result['order']->load('vendorOrders');
        // 7.5% of 1,000,000 kobo = 75,000; 7.5% of 2,000,000 kobo = 150,000 — same rate, both vendors.
        $this->assertEqualsCanonicalizing([75_000, 150_000], $order->vendorOrders->pluck('vat_amount')->all());
    }

    public function test_vendor_cannot_set_their_own_vat_rate_any_more(): void
    {
        $vendor = Vendor::factory()->verified()->create();
        $user = $vendor->user;
        $this->actingAs($user);

        // The vendors table no longer has a vat_rate column at all to mass-assign onto — a vendor
        // sending one is simply ignored rather than erroring or doing anything.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('vendors', 'vat_rate'));

        $this->patch(route('vendor.settings.update'), [
            'shipping_fee_type' => 'fixed',
            'shipping_fee_fixed_amount' => 500,
            'vat_rate' => 99,
        ])->assertSessionHasNoErrors();

        $this->assertSame('fixed', $vendor->fresh()->shipping_fee_type);
    }
}
