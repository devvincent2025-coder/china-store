<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_order_disputes', function (Blueprint $table) {
            // The buyer's own photo evidence, submitted when opening the dispute — see
            // OrderController::storeDispute(). Array of public Supabase URLs, same convention as
            // product/banner images.
            $table->json('evidence')->nullable()->after('description');

            // A vendor has 48-72h (see App\Models\VendorOrderDispute::RESPONSE_WINDOW_HOURS) to
            // respond with their own side/evidence before an admin steps in regardless — see
            // Vendor\DisputeController::respond() and Filament\Resources\VendorOrderDisputeResource.
            $table->timestamp('vendor_response_deadline')->nullable()->after('evidence');
            $table->text('vendor_response')->nullable()->after('vendor_response_deadline');
            $table->json('vendor_evidence')->nullable()->after('vendor_response');
            $table->timestamp('vendor_responded_at')->nullable()->after('vendor_evidence');

            // Who actually made the final call — the existing AdminActivityLog entry records this
            // too, but querying/displaying it directly on the dispute itself is far simpler than
            // joining out to the activity log every time.
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
        });

        // A third outcome alongside the existing 'approved_refund' (buyer was right, full refund)
        // and 'approved_release' (buyer's dispute denied, vendor gets paid as normal) — the vendor
        // ships a genuine replacement instead of refunding; money still releases to the vendor
        // since they're fulfilling the order, just via a different item. MySQL enums need a raw
        // ALTER rather than a Blueprint method to add a value in place.
        DB::statement("ALTER TABLE vendor_order_disputes MODIFY status ENUM('open', 'approved_refund', 'approved_replace', 'approved_release', 'closed') NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vendor_order_disputes MODIFY status ENUM('open', 'approved_refund', 'approved_release', 'closed') NOT NULL DEFAULT 'open'");

        Schema::table('vendor_order_disputes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['evidence', 'vendor_response_deadline', 'vendor_response', 'vendor_evidence', 'vendor_responded_at']);
        });
    }
};
