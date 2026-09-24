<?php

use App\Models\AdCampaign;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * An opaque, unguessable identifier used in every buyer-facing ad URL instead of the raw
 * auto-increment id — see App\Http\Controllers\AdController (routes keyed by {adCampaign:token})
 * and TopPicksSection.jsx, which never sees a campaign's numeric id at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->string('token', 24)->nullable()->unique()->after('id');
        });

        AdCampaign::whereNull('token')->each(function (AdCampaign $campaign) {
            $campaign->update(['token' => Str::random(20)]);
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
