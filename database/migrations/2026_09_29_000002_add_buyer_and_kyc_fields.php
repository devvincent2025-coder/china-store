<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A single saved address a buyer can reuse at checkout — deliberately not a full
            // multi-address book (that's a bigger feature); this just removes the "type it every
            // time" friction for the common case of one regular delivery address.
            $table->json('default_shipping_address')->nullable()->after('avatar_url');
        });

        Schema::table('vendors', function (Blueprint $table) {
            // Identity/KYC verification — distinct from the business documents_status collected at
            // onboarding (that's "is this a real business"; this is "does a real, verified person
            // stand behind the payout bank account"). Deliberately not required to complete
            // onboarding/go live — only to actually receive an escrow payout, so it doesn't block a
            // vendor's first sale. See Vendor::hasCompletedKyc() and EscrowService::release().
            $table->string('identity_document_path')->nullable()->after('bank_change_rejection_reason');
            $table->enum('identity_verification_status', ['not_submitted', 'pending', 'approved', 'rejected'])
                ->default('not_submitted')
                ->after('identity_document_path');
            $table->string('identity_rejection_reason')->nullable()->after('identity_verification_status');
            $table->foreignId('identity_reviewed_by')->nullable()->after('identity_rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('identity_reviewed_at')->nullable()->after('identity_reviewed_by');

            // Phone verification via OTP — same pattern as the bank-change-request OTP
            // (VendorBankChangeService), just for the vendor's contact_phone instead.
            $table->timestamp('phone_verified_at')->nullable()->after('identity_reviewed_at');
            $table->string('phone_otp_hash')->nullable()->after('phone_verified_at');
            $table->timestamp('phone_otp_expires_at')->nullable()->after('phone_otp_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_shipping_address');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('identity_reviewed_by');
            $table->dropColumn([
                'identity_document_path',
                'identity_verification_status',
                'identity_rejection_reason',
                'identity_reviewed_at',
                'phone_verified_at',
                'phone_otp_hash',
                'phone_otp_expires_at',
            ]);
        });
    }
};
