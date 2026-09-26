<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewVendorDocumentsSubmitted;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use App\Services\Storage\SupabaseStorageService;
use App\Services\Vendor\VendorBankChangeService;
use App\Services\Vendor\VendorOnboardingService;
use App\Support\NigerianBanks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Kept in sync with Vendor/Onboarding.jsx's own DOCUMENT_TYPES constant — same convention
     * KycModal.jsx/IdentityController use for the separate identity-document types, just not
     * shared as one source of truth across languages, so both sides list them explicitly.
     */
    private const DOCUMENT_TYPES = ['cac_certificate', 'valid_id', 'utility_bill', 'other'];

    public function __construct(
        private readonly VendorOnboardingService $onboardingService,
        private readonly KorapayService $korapay,
    ) {
    }

    public function show(Request $request, SupabaseStorageService $storage): Response
    {
        $vendor = $request->user()->vendor;

        return Inertia::render('Vendor/Onboarding', [
            'vendor' => $vendor,
            'banks' => $this->listBanksSafely(),
            // The document lives in the PRIVATE bucket — sign a short-lived URL per request so the
            // vendor can preview what they submitted without a permanent public link ever existing
            // for what might be a sensitive business document.
            'documentUrl' => $vendor?->verification_document_path
                ? $storage->signedUrl($vendor->verification_document_path)
                : null,
        ]);
    }

    /**
     * Korapay's bank list only feeds the bank dropdown — it must never take the whole onboarding
     * page down (blocking the business-profile/documents step too) if Korapay's API is unreachable
     * or misconfigured.
     */
    private function listBanksSafely(): array
    {
        try {
            return $this->korapay->listBanks();
        } catch (KorapayRequestException $e) {
            Log::warning('Could not load Korapay bank list for vendor onboarding page, using static fallback', ['message' => $e->getMessage()]);

            return NigerianBanks::list();
        }
    }

    /**
     * Step 2/3 of onboarding: exactly one business verification document, with a declared type,
     * uploaded to the PRIVATE Supabase Storage bucket and queued for the one manual Filament
     * review checkpoint. business_name/contact_email/contact_phone are NOT accepted here any more
     * — they're fixed at whatever the vendor registered with (shown read-only on the page), since
     * a vendor changing their business identity right as they submit documents under it would
     * undermine the point of reviewing those documents at all. See Vendor\SettingsController for
     * the vendor's own post-onboarding settings, which still doesn't touch these either.
     */
    public function updateProfile(Request $request, SupabaseStorageService $storage): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_if(! $vendor, 404);

        // A submission already awaiting review, or already approved, has nothing to resubmit over
        // — mirrors the button being disabled client-side, enforced again here since a disabled
        // button is only ever a UI convenience, never the actual gate.
        if (in_array($vendor->documents_status, ['pending', 'approved'], true)) {
            return redirect()->route('vendor.onboarding');
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', self::DOCUMENT_TYPES)],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $path = $storage->uploadPrivate($request->file('document')->getRealPath());

        // A resubmission after rejection replaces the old file rather than leaving an orphaned
        // one behind in the private bucket — same cleanup VendorIdentityService::
        // submitIdentityDocument() does for the separate identity document.
        if ($vendor->verification_document_path) {
            $storage->deletePrivate($vendor->verification_document_path);
        }

        $vendor->update([
            'verification_document_path' => $path,
            'verification_document_type' => $validated['document_type'],
            'documents_status' => 'pending',
            'documents_rejection_reason' => null,
        ]);

        Notification::send(User::role(['admin', 'super_admin'])->get(), new NewVendorDocumentsSubmitted($vendor));

        return redirect()->route('vendor.onboarding')->with('success', 'Document submitted for review.');
    }

    /**
     * Live "who owns this account" preview, called as the vendor finishes typing their account
     * number — same purpose and contract as Vendor\BankAccountController::resolveAccount() for the
     * post-onboarding bank-CHANGE flow, just reachable during onboarding itself (before a vendor's
     * store is active, so before the 'vendor.active' middleware group that resolveAccount() sits
     * behind). submitBankDetails() below re-resolves the account itself regardless — this is purely
     * informational, never trusted on its own.
     */
    public function resolveBankAccount(Request $request, VendorBankChangeService $bankChange): JsonResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'digits:10'],
            'bank_code' => ['required', 'string'],
        ]);

        try {
            $resolvedName = $bankChange->resolveAccountName($validated['account_number'], $validated['bank_code']);

            return response()->json(['resolved_name' => $resolvedName]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (KorapayRequestException $e) {
            return response()->json(['message' => 'Could not verify this account with your bank right now. Please try again shortly.'], 422);
        }
    }

    /**
     * Step 4/5: automated bank verification, zero human review — see
     * App\Services\Vendor\VendorOnboardingService::submitBankDetails(). The frontend only ever
     * calls this once resolveBankAccount() above has already shown the vendor the resolved account
     * name, but this re-verifies from scratch regardless (the same defense-in-depth
     * VendorBankChangeService::requestChange() applies for the bank-change flow).
     */
    public function submitBankDetails(Request $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_if(! $vendor, 404);

        // Already verifying or verified — nothing to resubmit unless Korapay came back 'failed'.
        if (in_array($vendor->bank_verification_status, ['pending', 'verified'], true)) {
            return redirect()->route('vendor.onboarding');
        }

        $validated = $request->validate([
            'account_number' => ['required', 'digits:10'],
            'bank_code' => ['required', 'string'],
            'bank_name' => ['required', 'string'],
        ]);

        $this->onboardingService->submitBankDetails(
            $vendor,
            $validated['account_number'],
            $validated['bank_code'],
            $validated['bank_name'],
        );

        return redirect()->route('vendor.onboarding')->with('success', 'Bank details submitted — verifying now.');
    }
}
