<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The compressed/enhanced copy (see App\Jobs\EnhanceProductImage) is now a genuinely
        // separate object in storage from the original upload, not just a resize URL on the same
        // object — so it needs its own storage_path to be deletable, and its own size for admin
        // visibility into how well the ~20-30KB compression target is being hit.
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('enhanced_storage_path')->nullable()->after('storage_path');
            $table->unsignedInteger('enhanced_size_bytes')->nullable()->after('enhanced_storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn(['enhanced_storage_path', 'enhanced_size_bytes']);
        });
    }
};
