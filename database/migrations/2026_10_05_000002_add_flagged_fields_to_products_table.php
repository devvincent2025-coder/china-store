<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Set automatically once a product accrues enough open reports (see
            // ProductReportController::store() and ProductTakedownService::autoFlagForReports())
            // — separate from taken_down_at/status so admins can tell "system pulled this down
            // pending investigation" apart from a manual moderator takedown, even though both
            // currently result in status='archived'.
            $table->timestamp('flagged_at')->nullable()->after('taken_down_by');
            $table->string('flagged_reason')->nullable()->after('flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['flagged_at', 'flagged_reason']);
        });
    }
};
