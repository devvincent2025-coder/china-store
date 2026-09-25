<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Backs the KYC modal shown from the vendor dashboard banner (see Vendor/Dashboard.jsx and
 * Components/KycModal.jsx) — email verification reuses Laravel's own stock verify-email flow
 * (routes/auth.php), so this only covers the two parts that are specific to KYC: phone OTP and
 * the identity document upload.
 */
class IdentityController extends Controller
{
    public function __construct(private readonly VendorIdentityService $identity)
    {
    }

    public function sendPhoneOtp(Request $request): RedirectResponse
    {
        $autoVerified = $this->identity->sendPhoneOtp($request->user()->vendor);

        return back()->with('success', $autoVerified
            ? 'Phone number verified.'
            : 'A verification code was sent to your phone.');
    }

    public function verifyPhoneOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        $this->identity->verifyPhoneOtp($request->user()->vendor, $validated['code']);

        return back()->with('success', 'Phone number verified.');
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document_type' => ['required', 'string', 'in:passport,national_id,drivers_license,voters_card'],
        ]);

        $this->identity->submitIdentityDocument(
            $request->user()->vendor,
            $validated['document'],
            $validated['document_type'],
        );

        return back()->with('success', 'Document submitted for review.');
    }

    /**
     * Called once by the celebratory modal itself right after the vendor dismisses it — see
     * Vendor::shouldShowKycCelebration()'s doc comment for why this durable flag (rather than a
     * session flash) is what makes the modal show exactly once.
     */
    public function markCelebrationSeen(Request $request): RedirectResponse
    {
        $request->user()->vendor->update(['kyc_celebration_seen_at' => now()]);

        return back();
    }
}
