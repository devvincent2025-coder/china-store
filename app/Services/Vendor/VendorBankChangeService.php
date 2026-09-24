<?php

namespace App\Services\Vendor;

use App\Jobs\VerifyVendorBankAccount;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\BankAccountChangeApproved;
use App\Notifications\BankAccountChangeOtp;
use App\Notifications\BankAccountChangeRejected;
use App\Notifications\BankAccountChangeSubmitted;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use App\Support\Dev\OtpLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Changing a vendor's payout bank account is deliberately the most friction-heavy write in the
 * whole app — money follows it. Three checkpoints, in order: (1) the vendor proves control of their
 * registered contact_email via a one-time code, (2) our own support team calls the vendor to
 * confirm by phone (an out-of-band step this service can't verify, only gate on), (3) an admin
 * records that call happened by approving the request in Filament. Only step 3 actually writes the
 * new bank_code/account_number columns that EscrowService pays out to.
 */
class VendorBankChangeService
{
    private const OTP_TTL_MINUTES = 10;

    public function __construct(private readonly KorapayService $korapay)
    {
    }

    /**
     * Resolves the account name via Korapay so the vendor (and, later, the frontend's live preview)
     * can see who actually owns the account before anything is submitted. Used both by the request
     * form's live "resolve as you type" preview and, again, authoritatively here — the client-shown
     * name is never trusted on its own, since a vendor could otherwise submit any account number and
     * bank code without Korapay ever having confirmed it resolves to a real account.
     */
    public function resolveAccountName(string $accountNumber, string $bankCode): string
    {
        $result = $this->korapay->resolveBankAccount($accountNumber, $bankCode);

        $resolvedName = $result['account_name'] ?? null;

        if (! $resolvedName) {
            throw ValidationException::withMessages([
                'account_number' => 'We could not resolve an account name for this account number and bank.',
            ]);
        }

        return $resolvedName;
    }

    public function requestChange(Vendor $vendor, string $accountNumber, string $bankCode, string $bankName): void
    {
        try {
            $resolvedName = $this->resolveAccountName($accountNumber, $bankCode);
        } catch (KorapayRequestException $e) {
            throw ValidationException::withMessages([
                'account_number' => 'We could not verify this account with your bank right now. Please try again shortly.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $vendor->update([
            'pending_bank_code' => $bankCode,
            'pending_bank_name' => $bankName,
            'pending_account_number' => $accountNumber,
            'pending_resolved_account_name' => $resolvedName,
            'bank_change_status' => 'otp_pending',
            'bank_change_otp_hash' => Hash::make($code),
            'bank_change_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        OtpLogger::record('vendor_bank_change_email', $vendor->contact_email, $code);

        $vendor->notify(new BankAccountChangeOtp($code));
    }

    public function verifyOtp(Vendor $vendor, string $code): void
    {
        if ($vendor->bank_change_status !== 'otp_pending') {
            throw ValidationException::withMessages(['code' => 'There is no pending bank account change to confirm.']);
        }

        if (! $vendor->bank_change_otp_expires_at || $vendor->bank_change_otp_expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Please start the request again.']);
        }

        if (! $vendor->bank_change_otp_hash || ! Hash::check($code, $vendor->bank_change_otp_hash)) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect.']);
        }

        $vendor->update([
            'bank_change_status' => 'awaiting_approval',
            'bank_change_requested_at' => now(),
            'bank_change_otp_hash' => null,
            'bank_change_otp_expires_at' => null,
        ]);

        Notification::send(User::role(['admin', 'super_admin'])->get(), new BankAccountChangeSubmitted($vendor));
    }

    /**
     * Copies the pending bank details into the live columns and re-runs Korapay verification —
     * called only from the Filament admin action after support has confirmed the change by phone.
     */
    public function approve(Vendor $vendor, User $admin): void
    {
        $vendor->update([
            'bank_code' => $vendor->pending_bank_code,
            'bank_name' => $vendor->pending_bank_name,
            'account_number' => $vendor->pending_account_number,
            'bank_verification_status' => 'pending',
            'resolved_account_name' => null,
            'pending_bank_code' => null,
            'pending_bank_name' => null,
            'pending_account_number' => null,
            'pending_resolved_account_name' => null,
            'bank_change_status' => 'none',
            'bank_change_reviewed_by' => $admin->id,
            'bank_change_reviewed_at' => now(),
            'bank_change_rejection_reason' => null,
        ]);

        VerifyVendorBankAccount::dispatch($vendor);

        $vendor->user->notify(new BankAccountChangeApproved);
    }

    public function reject(Vendor $vendor, User $admin, string $reason): void
    {
        $vendor->update([
            'pending_bank_code' => null,
            'pending_bank_name' => null,
            'pending_account_number' => null,
            'pending_resolved_account_name' => null,
            'bank_change_status' => 'none',
            'bank_change_reviewed_by' => $admin->id,
            'bank_change_reviewed_at' => now(),
            'bank_change_rejection_reason' => $reason,
        ]);

        $vendor->user->notify(new BankAccountChangeRejected($reason));
    }
}
