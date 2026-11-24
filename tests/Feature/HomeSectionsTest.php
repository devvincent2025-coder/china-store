<?php

namespace Tests\Feature;

use App\Filament\Resources\HomeSectionResource;
use App\Filament\Resources\HomeSectionResource\Pages\EditHomeSection;
use App\Filament\Resources\HomeSectionResource\RelationManagers\ItemsRelationManager;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin-managed homepage sections (Filament → Homepage sections) and their deal prices, which
 * must be what buyers are actually charged — see App\Services\Pricing\DealPricing.
 */
class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $vendor = Vendor::factory()->verified()->state([
            'identity_verification_status' => 'approved',
            'phone_verified_at' => now(),
        ]);

        return Product::factory()->for($vendor)->create(['price' => 10_000_00, 'stock' => 50] + $attributes);
    }

    private function dealSection(array $attributes = []): HomeSection
    {
        // $attributes first: PHP's array + keeps the LEFT value for duplicate keys.
        return HomeSection::create($attributes + [
            'type' => 'flash_deals',
            'title' => ['en' => 'Mega Sale'],
            'item_limit' => 4,
            'auto_fill' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function addToCart(Product $product, ?ProductVariant $variant = null, int $quantity = 1): int
    {
        $this->post(route('cart.store'), array_filter([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]))->assertSessionHasNoErrors();

        return (int) \App\Models\CartItem::query()->where('product_id', $product->id)->value('unit_price');
    }

    public function test_migration_installs_the_default_sections(): void
    {
        $this->assertSame(
            ['hero', 'categories', 'trust', 'flash_deals', 'collection', 'collection', 'collection', 'coupons', 'top_picks', 'product_grid', 'sell_cta', 'faq'],
            HomeSection::orderBy('sort_order')->pluck('type')->all(),
        );
    }

    public function test_homepage_renders_admin_sections_with_hand_picked_products_first(): void
    {
        HomeSection::query()->delete();
        $picked = $this->product();
        $other = $this->product();
        $section = $this->dealSection(['auto_fill' => true, 'item_limit' => 2]);
        HomeSectionItem::create(['home_section_id' => $section->id, 'product_id' => $picked->id, 'deal_price' => 7_500_00]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('sections', 1)
            ->where('sections.0.type', 'flash_deals')
            ->where('sections.0.title.en', 'Mega Sale')
            ->has('sections.0.products', 2)
            ->where('sections.0.products.0.id', $picked->id)
            ->where('sections.0.products.0.effective_price', 7_500_00)
            ->where('sections.0.products.0.original_price', 10_000_00)
            ->where('sections.0.products.1.id', $other->id));
    }

    public function test_hidden_and_scheduled_sections_are_not_rendered(): void
    {
        HomeSection::query()->delete();
        $this->dealSection(['is_active' => false]);
        $this->dealSection(['starts_at' => now()->addDay()]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('sections', 0));
    }

    public function test_live_deal_price_is_charged_in_cart(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        HomeSectionItem::create(['home_section_id' => $this->dealSection()->id, 'product_id' => $product->id, 'deal_price' => 7_500_00]);

        $this->assertSame(7_500_00, $this->addToCart($product));
    }

    public function test_expired_or_inactive_deals_are_not_charged(): void
    {
        $this->actingAs(User::factory()->create());
        $expired = $this->product();
        $inactive = $this->product();
        HomeSectionItem::create(['home_section_id' => $this->dealSection()->id, 'product_id' => $expired->id, 'deal_price' => 5_000_00, 'ends_at' => now()->subMinute()]);
        HomeSectionItem::create(['home_section_id' => $this->dealSection(['is_active' => false])->id, 'product_id' => $inactive->id, 'deal_price' => 5_000_00]);

        $this->assertSame(10_000_00, $this->addToCart($expired));
        $this->assertSame(10_000_00, $this->addToCart($inactive));
    }

    public function test_deal_never_raises_the_price(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        HomeSectionItem::create(['home_section_id' => $this->dealSection()->id, 'product_id' => $product->id, 'deal_price' => 12_000_00]);

        $this->assertSame(10_000_00, $this->addToCart($product));
    }

    public function test_deal_applies_to_inheriting_variants_but_not_explicitly_priced_ones(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        $inherits = ProductVariant::create(['product_id' => $product->id, 'sku' => 'PIECE', 'stock' => 5]);
        $carton = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CARTON', 'stock' => 5, 'price' => 90_000_00]);
        HomeSectionItem::create(['home_section_id' => $this->dealSection()->id, 'product_id' => $product->id, 'deal_price' => 7_500_00]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'product_variant_id' => $inherits->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('cart.store'), ['product_id' => $product->id, 'product_variant_id' => $carton->id, 'quantity' => 1])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $inherits->id, 'unit_price' => 7_500_00]);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $carton->id, 'unit_price' => 90_000_00]);
    }

    public function test_with_bulk_tiers_the_lower_of_tier_and_deal_is_charged(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        ProductPriceTier::create(['product_id' => $product->id, 'min_quantity' => 1, 'max_quantity' => 9, 'price_kobo' => 9_000_00]);
        ProductPriceTier::create(['product_id' => $product->id, 'min_quantity' => 10, 'max_quantity' => null, 'price_kobo' => 6_000_00]);
        HomeSectionItem::create(['home_section_id' => $this->dealSection()->id, 'product_id' => $product->id, 'deal_price' => 7_500_00]);

        $this->assertSame(7_500_00, $this->addToCart($product, quantity: 2)); // deal beats tier 1
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 8])->assertSessionHasNoErrors();
        $this->assertSame(6_000_00, (int) \App\Models\CartItem::where('product_id', $product->id)->value('unit_price')); // tier 2 beats deal
    }

    public function test_admin_can_manage_sections_and_set_deal_price_in_naira(): void
    {
        // Same permission rows/roles production has (super_admin is synced every permission).
        $this->seed([\Database\Seeders\AdminPermissionSeeder::class, \Database\Seeders\AdminSubRolesSeeder::class]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('super_admin', 'web'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $section = $this->dealSection();
        $product = $this->product();

        $this->get(HomeSectionResource::getUrl('index'))->assertOk()->assertSee('Mega Sale');
        $this->get(HomeSectionResource::getUrl('edit', ['record' => $section]))->assertOk();

        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $section, 'pageClass' => EditHomeSection::class])
            ->callTableAction('create', data: ['product_id' => $product->id, 'deal_price' => '7500.50'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('home_section_items', [
            'home_section_id' => $section->id,
            'product_id' => $product->id,
            'deal_price' => 7_500_50,
        ]);

        // A "deal" at or above the normal price is rejected.
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $this->dealSection(), 'pageClass' => EditHomeSection::class])
            ->callTableAction('create', data: ['product_id' => $product->id, 'deal_price' => '10000'])
            ->assertHasTableActionErrors(['deal_price']);
    }
}
