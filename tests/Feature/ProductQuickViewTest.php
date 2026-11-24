<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /products/{slug}/quick-view — what a product card's "Add to Cart" loads to decide between
 * adding straight away and opening the variant-picker modal.
 */
class ProductQuickViewTest extends TestCase
{
    use RefreshDatabase;

    /** A vendor that passes Product::scopePubliclyListed() (verified() alone isn't KYC/phone-approved). */
    private function listedVendor()
    {
        return Vendor::factory()->verified()->state([
            'identity_verification_status' => 'approved',
            'phone_verified_at' => now(),
        ]);
    }

    public function test_returns_options_and_variants_for_a_listed_product(): void
    {
        $product = Product::factory()->for($this->listedVendor())->create(['stock' => 10]);
        $option = ProductOption::create(['product_id' => $product->id, 'name' => ['en' => 'Size'], 'sort_order' => 0]);
        $value = ProductOptionValue::create(['product_option_id' => $option->id, 'value' => ['en' => 'Large'], 'sort_order' => 0]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-L', 'stock' => 4]);
        $variant->optionValues()->attach($value->id);

        $response = $this->getJson(route('products.quick-view', $product->slug))->assertOk();

        $response->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.options.0.values.0.id', $value->id)
            ->assertJsonPath('product.variants.0.id', $variant->id)
            ->assertJsonPath('product.variants.0.option_values.0.id', $value->id)
            ->assertJsonPath('product.variants.0.stock', 4);

        // The parent product must not be re-serialized inside every variant.
        $this->assertArrayNotHasKey('product', $response->json('product.variants.0'));
    }

    public function test_product_without_options_returns_empty_options(): void
    {
        $product = Product::factory()->for($this->listedVendor())->create();

        $this->getJson(route('products.quick-view', $product->slug))
            ->assertOk()
            ->assertJsonPath('product.options', []);
    }

    public function test_hidden_product_is_not_found(): void
    {
        // Vendor not identity-verified -> product is not publicly listed anywhere.
        $product = Product::factory()->for(Vendor::factory())->create();

        $this->getJson(route('products.quick-view', $product->slug))->assertNotFound();
    }
}
