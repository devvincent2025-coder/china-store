<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Models\VendorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private function listedVendor(array $overrides = []): Vendor
    {
        $vendor = Vendor::factory()->verified()->create();
        $vendor->forceFill([
            'identity_verification_status' => 'approved',
            'phone_verified_at' => now(),
            'vacation_mode' => false,
            ...$overrides,
        ])->save();
        $vendor->user->forceFill(['email_verified_at' => now()])->save();

        return $vendor;
    }

    public function test_index_lists_every_sub_sitemap_with_lastmod(): void
    {
        Product::factory()->for($this->listedVendor())->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('sitemap.static'), false)
            ->assertSee(route('sitemap.categories'), false)
            ->assertSee(route('sitemap.stores'), false)
            ->assertSee(route('sitemap.products', ['page' => 1]), false)
            ->assertSee('<lastmod>', false);
    }

    public function test_products_sitemap_includes_all_gallery_images_and_only_listed_products(): void
    {
        $listed = Product::factory()->for($this->listedVendor())->create();
        ProductImage::factory()->for($listed)->create(['enhanced_url' => 'https://cdn.test/a.jpg', 'sort_order' => 0]);
        ProductImage::factory()->for($listed)->create(['enhanced_url' => null, 'original_url' => '/storage/b.jpg', 'sort_order' => 1]);

        $onVacation = Product::factory()->for($this->listedVendor(['vacation_mode' => true]))->create();
        $draft = Product::factory()->for($this->listedVendor())->create(['status' => 'draft']);

        $this->get('/sitemap-products-1.xml')
            ->assertOk()
            ->assertSee(route('products.show', $listed), false)
            ->assertSee('https://cdn.test/a.jpg', false)
            ->assertSee(rtrim(config('app.url'), '/').'/storage/b.jpg', false)
            ->assertSee('hreflang="zh"', false)
            ->assertDontSee(route('products.show', $onVacation), false)
            ->assertDontSee(route('products.show', $draft), false);

        $this->get('/sitemap-products-2.xml')->assertNotFound();
    }

    public function test_categories_sitemap_skips_empty_and_orphaned_categories(): void
    {
        $vendor = $this->listedVendor();
        $withProducts = Category::factory()->create();
        Product::factory()->for($vendor)->for($withProducts)->create();

        $parentOfListedChild = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parentOfListedChild->id]);
        Product::factory()->for($vendor)->for($child)->create();

        $empty = Category::factory()->create();
        $inactiveParent = Category::factory()->create(['is_active' => false]);
        $orphan = Category::factory()->create(['parent_id' => $inactiveParent->id]);
        Product::factory()->for($vendor)->for($orphan)->create();

        $this->get('/sitemap-categories.xml')
            ->assertOk()
            ->assertSee(route('categories.show', $withProducts), false)
            ->assertSee(route('categories.show', $parentOfListedChild), false)
            ->assertSee(route('categories.show', $child), false)
            ->assertDontSee(route('categories.show', $empty), false)
            ->assertDontSee(route('categories.show', $orphan), false);
    }

    public function test_stores_sitemap_only_lists_stores_with_listed_products(): void
    {
        $active = VendorStore::factory()->for($vendor = $this->listedVendor())->create();
        Product::factory()->for($vendor)->create();

        $vacation = VendorStore::factory()->for($away = $this->listedVendor(['vacation_mode' => true]))->create();
        Product::factory()->for($away)->create();

        $emptyStore = VendorStore::factory()->for($this->listedVendor())->create();

        $this->get('/sitemap-stores.xml')
            ->assertOk()
            ->assertSee(route('vendors.show', $active), false)
            ->assertSee($active->logo_url, false)
            ->assertDontSee(route('vendors.show', $vacation), false)
            ->assertDontSee(route('vendors.show', $emptyStore), false);
    }

    public function test_export_command_writes_every_file(): void
    {
        Product::factory()->for($this->listedVendor())->create();
        $dir = storage_path('framework/testing/sitemap-export');
        File::deleteDirectory($dir);

        $this->artisan('sitemap:export', ['--path' => $dir, '--gzip' => true])->assertSuccessful();

        foreach (['sitemap.xml', 'sitemap-static.xml', 'sitemap-categories.xml', 'sitemap-stores.xml', 'sitemap-products-1.xml'] as $file) {
            $this->assertFileExists("{$dir}/{$file}");
            $this->assertFileExists("{$dir}/{$file}.gz");
            $this->assertNotFalse(simplexml_load_file("{$dir}/{$file}"), "{$file} is not well-formed XML");
        }

        File::deleteDirectory($dir);
    }

    public function test_robots_points_at_sitemap_and_blocks_tracking_redirects(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.route('sitemap.index'), false)
            ->assertSee('Disallow: /go/', false);
    }
}
