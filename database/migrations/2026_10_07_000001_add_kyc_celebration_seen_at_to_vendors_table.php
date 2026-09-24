<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Lets the vendor dashboard show a one-time celebratory "you're a verified seller!"
            // modal exactly once after an admin approves their identity — the approval itself
            // happens in an admin session, so a normal Inertia session flash can't carry this
            // across to the vendor's own next visit; this column is what makes it durable. See
            // App\Http\Controllers\Vendor\DashboardController and
            // App\Http\Controllers\Vendor\IdentityController::markCelebrationSeen().
            $table->timestamp('kyc_celebration_seen_at')->nullable()->after('identity_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('kyc_celebration_seen_at');
        });
    }
};
