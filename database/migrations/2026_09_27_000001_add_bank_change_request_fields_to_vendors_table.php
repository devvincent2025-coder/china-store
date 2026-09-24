<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A vendor changing payout bank details is money-sensitive, so this is never a direct write to
// bank_code/account_number: the vendor's requested values sit in these pending_* columns behind an
// email OTP check, then wait for a human admin approval (our support team calls the vendor to
// confirm) before VendorBankChangeService::approve() copies them into the real columns and
// re-triggers Korapay verification — see App\Services\Vendor\VendorBankChangeService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('pending_bank_code')->nullable()->after('korapay_subaccount_created_at');
            $table->string('pending_bank_name')->nullable()->after('pending_bank_code');
            $table->string('pending_account_number')->nullable()->after('pending_bank_name');
            $table->string('pending_resolved_account_name')->nullable()->after('pending_account_number');
            $table->enum('bank_change_status', ['none', 'otp_pending', 'awaiting_approval'])
                ->default('none')
                ->after('pending_resolved_account_name');
            $table->string('bank_change_otp_hash')->nullable()->after('bank_change_status');
            $table->timestamp('bank_change_otp_expires_at')->nullable()->after('bank_change_otp_hash');
            $table->timestamp('bank_change_requested_at')->nullable()->after('bank_change_otp_expires_at');
            $table->foreignId('bank_change_reviewed_by')->nullable()->after('bank_change_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('bank_change_reviewed_at')->nullable()->after('bank_change_reviewed_by');
            $table->string('bank_change_rejection_reason')->nullable()->after('bank_change_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_change_reviewed_by');
            $table->dropColumn([
                'pending_bank_code',
                'pending_bank_name',
                'pending_account_number',
                'pending_resolved_account_name',
                'bank_change_status',
                'bank_change_otp_hash',
                'bank_change_otp_expires_at',
                'bank_change_requested_at',
                'bank_change_reviewed_at',
                'bank_change_rejection_reason',
            ]);
        });
    }
};
