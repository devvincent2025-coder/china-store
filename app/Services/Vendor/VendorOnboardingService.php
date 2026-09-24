<?php

namespace App\Services\Vendor;

use App\Jobs\CreateVendorSubaccount;
use App\Jobs\VerifyVendorBankAccount;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\VendorDocumentsApproved;
use App\Notifications\VendorDocumentsRejected;

/**
 * Orchestrates the vendor onboarding lifecycle described in the product spec: bank verification is
 * fully automated, document review is the one deliberate manual checkpoint (a fraud control for a
 * multi-vendor marketplace), and going live only happens once both gates pass.
 */
class VendorOnboardingService
{
    public function submitBankDetails(Vendor $vendor, string $accountNumber, string $bankCode, string $bankName): void
    {
        $vendor->update([
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'bank_name' => $bankName,
            'bank_verification_status' => 'pending',
        ]);

        VerifyVendorBankAccount::dispatch($vendor);
    }

    /**
     * The single manual step in the whole platform: an admin reviews uploaded ID/CAC documents in
     * Filament and approves or rejects them. Everything downstream of this call is automatic.
     */
    public function approveDocuments(Vendor $vendor, User $reviewer): void
    {
        $vendor->update([
            'documents_status' => 'approved',
            'documents_reviewed_by' => $reviewer->id,
            'documents_reviewed_at' => now(),
            'documents_rejection_reason' => null,
        ]);

        $vendor->user->notify(new VendorDocumentsApproved);

        $this->activateIfReady($vendor);
    }

    public function rejectDocuments(Vendor $vendor, User $reviewer, string $reason): void
    {
        $vendor->update([
            'documents_status' => 'rejected',
            'documents_reviewed_by' => $reviewer->id,
            'documents_reviewed_at' => now(),
            'documents_rejection_reason' => $reason,
        ]);

        $vendor->user->notify(new VendorDocumentsRejected($reason));
    }

    /**
     * Called after either gate changes (document approval or bank verification) since we don't
     * know in advance which one will complete last.
     */
    public function activateIfReady(Vendor $vendor): void
    {
        if ($vendor->status === 'active' || ! $vendor->hasPassedOnboardingChecks()) {
            return;
        }

        CreateVendorSubaccount::dispatch($vendor);
    }
}
