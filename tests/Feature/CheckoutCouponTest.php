<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Applying / removing a coupon on the checkout page — staged in the session per vendor group,
 * reflected in that group's totals and the order summary, then re-validated when paying.
 */
class CheckoutCouponTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create();
        $vendor = Vendor::factory()->verified()->state(['identity_verification_status' => 'approved', 'phone_verified_at' => now()]);
        $this->product = Product::factory()->for($vendor)->create(['price' => 10_000_00, 'stock' => 20, 'compare_price' => null]);

        Coupon::create([
            'code' => 'NEWARRIVAL15',
            'type' => 'percentage',
            'value' => 15,
            'minimum_spend' => 3_000_00,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'usage_limit' => 300,
        ]);

        $this->actingAs($this->buyer)->post(route('cart.store'), ['product_id' => $this->product->id, 'quantity' => 1]);
    }

    private function apply(string $code)
    {
        return $this->from(route('checkout.index'))->post(route('checkout.coupons.apply'), [
            'vendor_id' => $this->product->vendor_id,
            'code' => $code,
        ]);
    }

    public function test_applying_a_coupon_flashes_savings_and_discounts_the_group(): void
    {
        $this->apply('newarrival15 ') // case/whitespace-insensitive
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Coupon NEWARRIVAL15 applied — you save ₦1,500.00.');

        $this->get(route('checkout.index'))->assertInertia(fn (Assert $page) => $page
            ->where('itemsByVendor.0.applied_coupon_code', 'NEWARRIVAL15')
            ->where('itemsByVendor.0.subtotal', 10_000_00)
            ->where('itemsByVendor.0.discount', 1_500_00)
            ->where('couponNotices', []));
    }

    public function test_invalid_coupon_returns_an_error_for_that_store(): void
    {
        $this->apply('NOPE')->assertSessionHasErrors([
            "coupons.{$this->product->vendor_id}" => 'This coupon code does not exist.',
        ]);
    }

    public function test_removing_a_coupon_clears_the_discount(): void
    {
        $this->apply('NEWARRIVAL15');

        $this->from(route('checkout.index'))
            ->delete(route('checkout.coupons.remove', $this->product->vendor_id))
            ->assertSessionHas('success', 'Coupon NEWARRIVAL15 removed.');

        $this->get(route('checkout.index'))->assertInertia(fn (Assert $page) => $page
            ->where('itemsByVendor.0.applied_coupon_code', null)
            ->where('itemsByVendor.0.discount', 0));
    }

    public function test_coupon_that_stops_qualifying_is_dropped_with_a_notice(): void
    {
        $this->apply('NEWARRIVAL15');
        Coupon::where('code', 'NEWARRIVAL15')->update(['minimum_spend' => 50_000_00]);

        $this->get(route('checkout.index'))->assertInertia(fn (Assert $page) => $page
            ->where('itemsByVendor.0.applied_coupon_code', null)
            ->where('itemsByVendor.0.discount', 0)
            ->where('couponNotices.0', 'Coupon NEWARRIVAL15 was removed: This coupon requires a minimum spend of ₦50,000.00.'));
    }

    public function test_applied_coupon_is_charged_on_the_order_and_its_usage_recorded(): void
    {
        // Korapay is only reached after the order is written — stub the hosted-checkout call.
        $this->mock(KorapayService::class, fn ($mock) => $mock->shouldReceive('initializeCharge')
            ->once()
            ->andReturn(['checkout_url' => 'https://checkout.korapay.test/abc']));

        $this->apply('NEWARRIVAL15');

        $this->post(route('checkout.store'), [
            'address_id' => null,
            'save_address' => false,
            'shipping_address' => ['name' => 'Ada Obi', 'phone' => '08031234567', 'line1' => '12 Allen Avenue', 'city' => 'Ikeja', 'state' => 'Lagos'],
        ])->assertSessionHasNoErrors();

        $coupon = Coupon::where('code', 'NEWARRIVAL15')->first();

        $this->assertDatabaseHas('vendor_orders', [
            'vendor_id' => $this->product->vendor_id,
            'coupon_id' => $coupon->id,
            'subtotal' => 10_000_00,
            'discount' => 1_500_00,
        ]);
        $this->assertDatabaseHas('orders', ['user_id' => $this->buyer->id, 'subtotal' => 10_000_00, 'discount_total' => 1_500_00]);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id, 'user_id' => $this->buyer->id, 'discount_amount' => 1_500_00]);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_payment_provider_failure_rolls_everything_back_and_keeps_the_cart(): void
    {
        $this->mock(KorapayService::class, fn ($mock) => $mock->shouldReceive('initializeCharge')
            ->once()
            ->andThrow(new KorapayRequestException('Korapay request failed while trying to initialize charge: invalid authorization key')));

        $this->apply('NEWARRIVAL15');

        $this->from(route('checkout.index'))->post(route('checkout.store'), [
            'address_id' => null,
            'save_address' => false,
            'shipping_address' => ['name' => 'Ada Obi', 'phone' => '08031234567', 'line1' => '12 Allen Avenue', 'city' => 'Ikeja', 'state' => 'Lagos'],
        ])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error');

        // No orphaned order, stock and coupon use given back, cart and staged coupon intact.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('vendor_orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertSame(0, Coupon::where('code', 'NEWARRIVAL15')->value('used_count'));
        $this->assertSame(20, $this->product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 10_000_00]);

        $this->get(route('checkout.index'))->assertInertia(fn (Assert $page) => $page
            ->where('itemsByVendor.0.applied_coupon_code', 'NEWARRIVAL15')
            ->where('itemsByVendor.0.discount', 1_500_00));
    }
}
