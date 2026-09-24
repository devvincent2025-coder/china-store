<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Which kind of government ID the vendor uploaded — shown to the admin reviewing it
            // (App\Filament\Resources\VendorResource) alongside the document itself, since a
            // reviewer otherwise has no way to know what they're looking at. See
            // App\Services\Vendor\VendorIdentityService::submitIdentityDocument().
            $table->enum('identity_document_type', ['passport', 'national_id', 'drivers_license', 'voters_card'])
                ->nullable()
                ->after('identity_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('identity_document_type');
        });
    }
};
