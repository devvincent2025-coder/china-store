<?php

namespace App\Services\Vendor;

use App\Models\User;
use App\Models\Vendor;
use App\Notifications\IdentityVerificationApproved;
use App\Notifications\IdentityVerificationRejected;
use App\Notifications\NewIdentityVerificationSubmitted;
use App\Services\Sms\SmsSendException;
use App\Services\Sms\SmsService;
use App\Services\Storage\SupabaseStorageService;
use App\Support\Dev\OtpLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * KYC ("know your customer") identity verification — separate from and stricter than the business
 * documents_status check done at onboarding. That earlier check answers "is this a real
 * business?"; this one answers "does a verified individual actually control this account and its
 * payout bank account?" Three parts: email (reuses Laravel's own MustVerifyEmail — see User::class
 * and Vendor::hasCompletedKyc()), phone (OTP via SmsService), and a passport/government ID upload
 * an admin reviews. Deliberately NOT required to complete onboarding or go live — only to actually
 * receive an escrow payout (see EscrowService::release()), so it never blocks a vendor's first sale.
 */
class VendorIdentityService
{
    private const OTP_TTL_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 60;

    // A 6-digit code is 1,000,000 possibilities, but still not safe to leave unlimited-guess —
    // caps wrong attempts the same way BuyerSignupService does for its 4-digit codes.
    private const MAX_VERIFY_ATTEMPTS = 5;

    private const VERIFY_LOCKOUT_SECONDS = 600;

    public function __construct(
        private readonly SmsService $sms,
        private readonly SupabaseStorageService $storage,
    ) {
    }

    /**
     * @return bool true if the phone was verified immediately with no code sent at all (see
     *   config('services.smart_sms.phone_otp_required')) — the caller (IdentityController) uses
     *   this to flash the right message, since KycModal.jsx's code-entry form disappears on its
     *   own once vendor.phone_verified_at is set (no other frontend change needed).
     */
    public function sendPhoneOtp(Vendor $vendor): bool
    {
        if (! config('services.smart_sms.phone_otp_required')) {
            $vendor->update([
                'phone_verified_at' => now(),
                'phone_otp_hash' => null,
                'phone_otp_expires_at' => null,
            ]);

            return true;
        }

        // Previously unthrottled entirely — a vendor (or anyone driving their session) could spam
        // this endpoint and burn SMS credits with no limit at all. A 60-second cooldown per vendor
        // matches the buyer-signup phone OTP's own resend spacing (see BuyerSignupService).
        $sendThrottleKey = 'vendor-phone-otp-send:'.$vendor->id;

        if (RateLimiter::tooManyAttempts($sendThrottleKey, 1)) {
            $seconds = RateLimiter::availableIn($sendThrottleKey);

            throw ValidationException::withMessages([
                'phone' => "Please wait {$seconds} seconds before requesting another code.",
            ]);
        }

        $code = (string) random_int(100000, 999999);

        try {
            $this->sms->send($vendor->contact_phone, "Your ChinaInNigeria verification code is {$code}. It expires in 10 minutes.");
        } catch (SmsSendException $e) {
            // In local dev, a real gateway outage (which is exactly the situation
            // App\Support\Dev\OtpLogger/routes/dev.php's /dev/otps page exists for) shouldn't
            // block testing the rest of the flow — the code still gets persisted below and shown
            // on that page. In production/staging this must still fail loudly: a real vendor who
            // never received their code has no other way to get it.
            if (! app()->environment('local')) {
                throw ValidationException::withMessages([
                    'phone' => 'We could not send a verification code to your phone right now. Please try again shortly.',
                ]);
            }

            Log::warning('SMS send failed in local env — continuing so the OTP stays testable via /dev/otps', [
                'vendor_id' => $vendor->id,
                'message' => $e->getMessage(),
            ]);
        }

        RateLimiter::hit($sendThrottleKey, self::RESEND_COOLDOWN_SECONDS);

        // A fresh code deserves a fresh set of verify attempts.
        RateLimiter::clear('vendor-phone-otp-verify:'.$vendor->id);
        OtpLogger::record('vendor_phone_kyc', $vendor->contact_phone, $code);

        $vendor->update([
            'phone_otp_hash' => Hash::make($code),
            'phone_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        return false;
    }

    public function verifyPhoneOtp(Vendor $vendor, string $code): void
    {
        $throttleKey = 'vendor-phone-otp-verify:'.$vendor->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_VERIFY_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => "Too many incorrect attempts. Please try again in {$seconds} seconds, or request a new code.",
            ]);
        }

        if (! $vendor->phone_otp_expires_at || $vendor->phone_otp_expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Please request a new one.']);
        }

        if (! $vendor->phone_otp_hash || ! Hash::check($code, $vendor->phone_otp_hash)) {
            RateLimiter::hit($throttleKey, self::VERIFY_LOCKOUT_SECONDS);

            throw ValidationException::withMessages(['code' => 'That code is incorrect.']);
        }

        RateLimiter::clear($throttleKey);

        $vendor->update([
            'phone_verified_at' => now(),
            'phone_otp_hash' => null,
            'phone_otp_expires_at' => null,
        ]);
    }

    public function submitIdentityDocument(Vendor $vendor, UploadedFile $document, string $documentType): void
    {
        // Passport/ID scans are as sensitive as the business verification documents from
        // onboarding — same private bucket, same short-lived signed URL pattern for admin review
        // (see Filament\Resources\VendorResource's identity section).
        $path = $this->storage->uploadPrivate($document->getRealPath());

        if ($vendor->identity_document_path) {
            $this->storage->deletePrivate($vendor->identity_document_path);
        }

        $vendor->update([
            'identity_document_path' => $path,
            'identity_document_type' => $documentType,
            'identity_verification_status' => 'pending',
            'identity_rejection_reason' => null,
        ]);

        Notification::send(User::role(['admin', 'super_admin'])->get(), new NewIdentityVerificationSubmitted($vendor));
    }

    public function approveIdentity(Vendor $vendor, User $admin): void
    {
        $vendor->update([
            'identity_verification_status' => 'approved',
            'identity_reviewed_by' => $admin->id,
            'identity_reviewed_at' => now(),
            'identity_rejection_reason' => null,
        ]);

        $vendor->user->notify(new IdentityVerificationApproved);
    }

    public function rejectIdentity(Vendor $vendor, User $admin, string $reason): void
    {
        $vendor->update([
            'identity_verification_status' => 'rejected',
            'identity_reviewed_by' => $admin->id,
            'identity_reviewed_at' => now(),
            'identity_rejection_reason' => $reason,
        ]);

        $vendor->user->notify(new IdentityVerificationRejected($reason));
    }
}
