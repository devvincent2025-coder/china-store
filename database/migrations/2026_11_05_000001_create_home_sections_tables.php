<?php

use App\Support\Home\HomeSectionDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per block on the storefront homepage (hero, flash deals, collections, FAQ, ...).
        // Admin-managed from Filament's "Homepage sections" resource; see App\Models\HomeSection.
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->json('title')->nullable();
            $table->json('eyebrow')->nullable();
            $table->json('subtitle')->nullable();
            // Type-specific config: collection theme/link, CTA button, trust items, FAQ entries...
            $table->json('settings')->nullable();
            // Product sections: how many products to show, and whether to top up with automatic
            // picks when fewer have been hand-placed.
            $table->unsignedSmallInteger('item_limit')->default(12);
            $table->boolean('auto_fill')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            // Visibility window. For Flash Deals, ends_at is also the countdown target.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // Products hand-placed into a section, with an optional admin-set deal price that is
        // actually charged at cart/checkout while active (see App\Services\Pricing\DealPricing).
        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('deal_price')->nullable()->comment('kobo');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['home_section_id', 'product_id']);
            $table->index(['product_id', 'deal_price']);
        });

        // Ship with today's homepage layout as editable rows, so deploying this changes nothing
        // visible until an admin edits it.
        HomeSectionDefaults::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
        Schema::dropIfExists('home_sections');
    }
};
