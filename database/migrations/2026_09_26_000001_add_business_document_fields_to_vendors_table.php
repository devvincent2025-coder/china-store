<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business verification is now exactly one document with a declared type (CAC certificate, valid
 * ID, utility bill, ...) — same shape as the existing identity/KYC document
 * (identity_document_path/identity_document_type, see App\Services\Vendor\VendorIdentityService) —
 * rather than the old verification_documents JSON array with no type at all. Also adds a genuine
 * 'not_submitted' status: previously documents_status defaulted to 'pending' for every vendor row
 * from the moment it was created, even before anything was ever uploaded, which is exactly what
 * Vendor/Onboarding.jsx's "Submit for Review" button needs to tell apart from a real, awaiting-
 * review submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('verification_document_path')->nullable()->after('verification_documents');
            $table->string('verification_document_type')->nullable()->after('verification_document_path');
        });

        // Backfill from the old array column before dropping it: a vendor who genuinely submitted
        // under the old flow keeps their document (as the single new path, type unknown — 'other')
        // and whatever status/reason an admin already gave it. A vendor who never submitted
        // anything gets 'not_submitted' instead of the old, misleading default of 'pending'.
        DB::table('vendors')->orderBy('id')->chunkById(200, function ($vendors) {
            foreach ($vendors as $vendor) {
                $paths = json_decode($vendor->verification_documents ?? '[]', true) ?: [];

                if (! empty($paths)) {
                    DB::table('vendors')->where('id', $vendor->id)->update([
                        'verification_document_path' => $paths[0],
                        'verification_document_type' => 'other',
                    ]);
                } elseif ($vendor->documents_status === 'pending') {
                    DB::table('vendors')->where('id', $vendor->id)->update([
                        'documents_status' => 'not_submitted',
                    ]);
                }
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('documents_status', ['not_submitted', 'pending', 'approved', 'rejected'])
                ->default('not_submitted')
                ->change();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('verification_documents');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->json('verification_documents')->nullable();
        });

        DB::table('vendors')->whereNotNull('verification_document_path')->orderBy('id')->chunkById(200, function ($vendors) {
            foreach ($vendors as $vendor) {
                DB::table('vendors')->where('id', $vendor->id)->update([
                    'verification_documents' => json_encode([$vendor->verification_document_path]),
                ]);
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->enum('documents_status', ['pending', 'approved', 'rejected'])->default('pending')->change();
            $table->dropColumn(['verification_document_path', 'verification_document_type']);
        });
    }
};
