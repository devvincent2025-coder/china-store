<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT is now a single platform-wide rate an admin sets from Filament\Pages\Settings
 * (App\Models\Setting::vatRate()), not something each vendor configures for themselves — see
 * App\Models\Vendor::calculateVat(). Dropping this column outright rather than just ignoring it,
 * so nothing can accidentally read a stale per-vendor value again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });

        // Force this to 7.50 outright, even overwriting an existing row: 'default_vat_rate' was
        // previously just a per-vendor SEED value (copied onto a new vendor's own vat_rate column
        // at signup, see VendorRegisteredUserController), pre-filled at '0.00' by
        // Filament\Pages\Settings::mount() — so most installs have an explicit but ACCIDENTAL 0.00
        // row here, persisted the first time any admin merely opened and saved that settings page
        // without ever touching this field. That's never been a deliberate choice, so it doesn't
        // deserve the "don't clobber an admin's existing value" treatment a real setting would get.
        Setting::set('default_vat_rate', '7.50');
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0.00);
        });
    }
};
