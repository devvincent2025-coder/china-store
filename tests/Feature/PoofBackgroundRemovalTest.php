<?php

namespace Tests\Feature;

use App\Jobs\EnhanceProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Services\Image\PoofBackgroundRemovalException;
use App\Services\Image\PoofBackgroundRemovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PoofBackgroundRemovalTest extends TestCase
{
    use RefreshDatabase;

    // Generated at runtime with GD (rather than a hand-typed base64 literal) so this is guaranteed
    // to be genuinely valid PNG data — ImageCompressionService::compress() decodes it for real.
    private function fakePng(): string
    {
        $image = imagecreatetruecolor(4, 4);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    private function fakeSupabaseAndDownload(array $extra = []): void
    {
        Http::fake(array_merge([
            'https://cdn.test/*' => Http::response($this->fakePng(), 200),
            'https://*.supabase.co/*' => Http::response('', 200),
        ], $extra));
    }

    public function test_service_is_not_configured_without_an_api_key(): void
    {
        Config::set('services.poof.api_key', null);
        $this->assertFalse(app(PoofBackgroundRemovalService::class)->isConfigured());

        Config::set('services.poof.api_key', 'test-key');
        Config::set('services.poof.enabled', false);
        $this->assertFalse(app(PoofBackgroundRemovalService::class)->isConfigured());

        Config::set('services.poof.enabled', true);
        $this->assertTrue(app(PoofBackgroundRemovalService::class)->isConfigured());
    }

    public function test_service_sends_the_documented_request_and_returns_the_image_bytes(): void
    {
        Config::set('services.poof.api_key', 'test-key');
        $resultBytes = 'processed-image-bytes';

        Http::fake(['https://api.poof.bg/v1/remove' => Http::response($resultBytes, 200)]);

        $bytes = app(PoofBackgroundRemovalService::class)->removeBackground('original-bytes');

        $this->assertSame($resultBytes, $bytes);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.poof.bg/v1/remove'
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->isMultipart();
        });
    }

    public function test_service_throws_with_the_documented_error_body_on_failure(): void
    {
        Config::set('services.poof.api_key', 'test-key');

        Http::fake(['https://api.poof.bg/v1/remove' => Http::response([
            'code' => 'insufficient_credits',
            'message' => 'Your account has run out of credits.',
            'request_id' => 'req_123',
        ], 402)]);

        $this->expectException(PoofBackgroundRemovalException::class);
        $this->expectExceptionMessage('insufficient_credits');

        app(PoofBackgroundRemovalService::class)->removeBackground('original-bytes');
    }

    public function test_enhance_job_skips_background_removal_entirely_when_unconfigured(): void
    {
        Config::set('services.poof.api_key', null);
        $image = ProductImage::factory()->for(Product::factory()->for(Vendor::factory()))->create([
            'original_url' => 'https://cdn.test/original.jpg',
            'processing_status' => 'processing',
        ]);

        $this->fakeSupabaseAndDownload();

        (new EnhanceProductImage($image))->handle(
            app(\App\Services\Storage\SupabaseStorageService::class),
            app(\App\Services\Image\ImageCompressionService::class),
            app(PoofBackgroundRemovalService::class),
        );

        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.poof.bg/v1/remove');
        $this->assertSame('done', $image->fresh()->processing_status);
    }

    public function test_enhance_job_falls_back_to_the_plain_photo_when_poof_fails(): void
    {
        Config::set('services.poof.api_key', 'test-key');
        $image = ProductImage::factory()->for(Product::factory()->for(Vendor::factory()))->create([
            'original_url' => 'https://cdn.test/original.jpg',
            'processing_status' => 'processing',
        ]);

        $this->fakeSupabaseAndDownload([
            'https://api.poof.bg/v1/remove' => Http::response(['code' => 'rate_limited', 'message' => 'Too many requests'], 429),
        ]);

        (new EnhanceProductImage($image))->handle(
            app(\App\Services\Storage\SupabaseStorageService::class),
            app(\App\Services\Image\ImageCompressionService::class),
            app(PoofBackgroundRemovalService::class),
        );

        // The job must NOT fail the whole upload just because background removal failed.
        $this->assertSame('done', $image->fresh()->processing_status);
        $this->assertNotNull($image->fresh()->enhanced_url);
    }
}
