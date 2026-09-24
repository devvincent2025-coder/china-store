<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            // When the campaign was last paused — the 24h "can't resume yet" cooldown
            // (AdCampaign::canResume()) is measured from this, and it's cleared back to null on
            // every successful resume so the NEXT pause starts its own fresh cooldown.
            $table->timestamp('paused_at')->nullable()->after('status');

            // Idempotency flag for the "ends in 2 days" reminder sweep (see
            // App\Console\Commands\SendAdCampaignEndingReminders) — without this, a command that
            // reruns daily would re-notify the vendor every day for the full 2-day window instead
            // of once.
            $table->timestamp('ending_reminder_sent_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'ending_reminder_sent_at']);
        });
    }
};
