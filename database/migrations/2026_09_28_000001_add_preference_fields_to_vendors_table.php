<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Store visibility — a vendor-controlled pause distinct from the admin-controlled
            // `status` column (suspended/rejected are compliance actions; vacation is voluntary and
            // reversible any time). See Product::scopePubliclyListed().
            $table->boolean('vacation_mode')->default(false)->after('status');
            $table->string('vacation_message')->nullable()->after('vacation_mode');

            // Feeds VendorAnalyticsService::summary()'s low_stock_count and the vendor products
            // page's "low stock" filter — previously both hardcoded to 5.
            $table->unsignedInteger('low_stock_threshold')->default(5)->after('vacation_message');

            // Per-vendor channel choice for the one truly high-volume notification (a new order) —
            // in-app/database stays on regardless, since that's the vendor's own order queue.
            $table->boolean('notify_new_order_email')->default(true)->after('low_stock_threshold');
            $table->boolean('notify_new_order_push')->default(true)->after('notify_new_order_email');

            // Default status assigned to a product the vendor just created — draft still requires
            // them to explicitly publish afterward.
            $table->boolean('auto_publish_products')->default(true)->after('notify_new_order_push');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'vacation_mode',
                'vacation_message',
                'low_stock_threshold',
                'notify_new_order_email',
                'notify_new_order_push',
                'auto_publish_products',
            ]);
        });
    }
};
