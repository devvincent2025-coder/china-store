<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Mirrors users.is_suspended/suspension_reason/suspended_at (see
            // App\Filament\Resources\UserResource's suspend/unsuspend actions) — a vendor
            // suspension is a manual admin decision (typically after investigating a product
            // that was auto-flagged for repeat reports, see ProductTakedownService), never
            // automatic. See App\Services\Vendor\VendorSuspensionService.
            $table->text('suspension_reason')->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspension_reason', 'suspended_at']);
        });
    }
};
