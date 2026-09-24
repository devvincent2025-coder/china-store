<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A buyer flagging a VENDOR/store directly (scam, poor service, IP violation, etc.) —
        // distinct from ProductReport, which flags one specific listing. Reviewed by an admin, who
        // can then suspend the vendor's whole account with a reason (see
        // Services\Vendor\VendorSuspensionService and Filament\Resources\VendorReportResource).
        // Deliberately no auto-suspend-at-threshold here, unlike ProductReport's auto-flag — a
        // whole-account suspension always stays a manual, investigated admin decision.
        Schema::create('vendor_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('reason', ['scam', 'fake_products', 'poor_service', 'ip_violation', 'other']);
            $table->text('description');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reports');
    }
};
