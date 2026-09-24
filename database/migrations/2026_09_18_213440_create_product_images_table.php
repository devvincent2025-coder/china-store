<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // The raw upload's public Supabase Storage URL, kept so the optimization job has a
            // stable source to re-process from.
            $table->string('original_url');
            // Populated asynchronously by App\Jobs\EnhanceProductImage once the Supabase render
            // (resize/quality) transform finishes. Null until the queued job completes.
            $table->string('enhanced_url')->nullable();
            // The object's path within the public Supabase Storage bucket (not the full URL) —
            // needed to build render/delete requests and to delete the object when the image is removed.
            $table->string('storage_path')->nullable();

            $table->enum('processing_status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
