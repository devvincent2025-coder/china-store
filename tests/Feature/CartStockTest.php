<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Checkout\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CartStockTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::factory()->for(Vendor::factory()->verified())->create($attributes);
    }

    public function test_variant_add_to_cart_works_when_the_driver_returns_ids_as_strings(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V1', 'stock' => 5]);

        // Reproduces production's MySQL driver, which hands back int columns as strings — the
        // strict === check in CartController::store() used to 422 on exactly this.
        DB::connection()->getPdo()->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
        $this->assertSame($product->id, ProductVariant::find($variant->id)->product_id);

        $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
    }

    public function test_quick_add_without_variant_redirects_to_product_page_for_products_with_options(): void
    {
        // Logged in: a guest cart rides on a cookie the test client doesn't carry between requests.
        $this->actingAs(User::factory()->create());
        $product = $this->product(['stock' => 10]);
        ProductOption::create(['product_id' => $product->id, 'name' => ['en' => 'Size'], 'sort_order' => 0]);

        // What a product card's "Add to Cart" sends — no variant, since it can't pick one.
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect(route('products.show', $product->slug))
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cannot_add_more_than_available_stock(): void
    {
        // Logged in: a guest cart rides on a cookie the test client doesn't carry between requests.
        $this->actingAs(User::factory()->create());
        $product = $this->product(['stock' => 3]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        // Merges with the existing line: 2 + 2 > 3.
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertSessionHasErrors(['quantity' => 'Only 3 left in stock.']);

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_cannot_update_cart_line_beyond_stock(): void
    {
        // Logged in: a guest cart rides on a cookie the test client doesn't carry between requests.
        $this->actingAs(User::factory()->create());
        $product = $this->product(['stock' => 3]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $item = Cart::first()->items()->first();

        $this->patch(route('cart.update', $item), ['quantity' => 4])
            ->assertSessionHasErrors(['quantity' => 'Only 3 left in stock.']);
        $this->patch(route('cart.update', $item), ['quantity' => 3])->assertSessionHasNoErrors();

        $this->assertSame(3, $item->fresh()->quantity);
    }

    public function test_product_page_exposes_existing_cart_line(): void
    {
        // Logged in: a guest cart rides on a cookie the test client doesn't carry between requests.
        $this->actingAs(User::factory()->create());
        $product = $this->product(['stock' => 10]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);

        $this->get(route('products.show', $product))
            ->assertInertia(fn ($page) => $page
                ->where('cartLines.0.quantity', 2)
                ->where('cartLines.0.product_variant_id', null));
    }

    public function test_checkout_rejects_order_when_stock_dropped_below_cart_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['stock' => 5]);
        $cart = $user->cart()->create();
        $cart->items()->create([
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'quantity' => 4,
            'unit_price' => $product->price,
        ]);

        // Another buyer bought most of it after this cart was filled.
        $product->update(['stock' => 1]);

        try {
            app(CheckoutService::class)->checkout($user, $cart, ['name' => 'A', 'phone' => '1', 'line1' => 'x', 'city' => 'Lagos', 'state' => 'Lagos'], [], 'https://example.test');
            $this->fail('Checkout should have been rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only 1 of', $e->errors()['cart'][0]);
            $this->assertStringContainsString('your cart has 4', $e->errors()['cart'][0]);
        }

        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }
}
