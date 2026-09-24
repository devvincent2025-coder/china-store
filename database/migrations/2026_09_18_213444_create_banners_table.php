<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            // Null vendor_id = platform homepage carousel banner managed by an admin.
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();

            $table->json('title')->nullable();
            $table->string('image_url');
            // Public Supabase Storage object path (not the full URL) — kept so the banner's file
            // can be deleted from storage when the banner record is deleted, avoiding orphans.
            $table->string('storage_path')->nullable();
            $table->string('link_url')->nullable();
            $table->enum('placement', ['homepage', 'store'])->default('homepage');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
