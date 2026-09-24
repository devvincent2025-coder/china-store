<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vendor's paid "boost" placement — shown as a "Top Picks / Recommended for you" card on the
 * homepage and a handful of other buyer-facing surfaces. See App\Services\Ads\AdCampaignService
 * for the full lifecycle: pending_payment -> pending_approval -> active -> (paused <-> active) ->
 * expired, or rejected at the approval step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            // Defaults to the vendor's own store banner/logo when left blank — see
            // AdCampaignService::create().
            $table->string('banner_path')->nullable();
            $table->string('banner_url')->nullable();

            $table->unsignedInteger('duration_days');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->enum('status', [
                'pending_payment', 'pending_approval', 'active', 'paused', 'rejected', 'expired', 'cancelled',
            ])->default('pending_payment');

            $table->unsignedBigInteger('amount_kobo');
            $table->string('payment_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();

            $table->string('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Aggregate metrics — see AdCampaignService::recordImpression()/recordClick()/
            // recordDwell(). Deliberately plain counters rather than a per-event log table: at this
            // traffic scale that's both simpler and cheaper to query for the vendor's own metrics
            // page than aggregating millions of rows on every dashboard load.
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->unsignedBigInteger('total_dwell_seconds')->default(0);
            $table->unsignedBigInteger('dwell_samples_count')->default(0);

            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
