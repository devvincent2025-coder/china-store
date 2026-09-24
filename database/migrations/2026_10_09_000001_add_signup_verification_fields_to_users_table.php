<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');

            // One shared OTP slot reused sequentially by whichever verification step is currently
            // active for this account (phone verification during the new buyer signup flow today;
            // email verification stays session-based pre-account, see BuyerSignupService) — same
            // hash+expiry+cooldown pattern as Vendor's phone_otp_hash/phone_otp_expires_at.
            $table->string('otp_hash')->nullable()->after('phone_verified_at');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_hash');
            $table->timestamp('otp_last_sent_at')->nullable()->after('otp_expires_at');

            // Set true only for accounts created via the new OTP signup flow (see
            // BuyerSignupService::verifyEmailOtp()) — existing accounts default to false so they are
            // never retroactively forced through phone verification.
            $table->boolean('requires_phone_verification')->default(false)->after('otp_last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'otp_hash', 'otp_expires_at', 'otp_last_sent_at', 'requires_phone_verification']);
        });
    }
};
