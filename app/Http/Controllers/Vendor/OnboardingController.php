<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use App\Services\Storage\SupabaseStorageService;
use App\Services\Vendor\VendorOnboardingService;
use App\Support\NigerianBanks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
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
            // Documents are stored as private-bucket paths, not URLs — sign a short-lived URL per
            // request so the vendor can review what they submitted without a permanent public link
            // ever existing for these sensitive files.
            'documentUrls' => $vendor && $vendor->verification_documents
                ? array_map(fn ($path) => $storage->signedUrl($path), $vendor->verification_documents)
                : [],
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
     * Step 2/3 of onboarding: business profile + verification documents. Documents go to the
     * PRIVATE Supabase Storage bucket (never public) and are immediately queued for the one manual
     * Filament review checkpoint.
     */
    public function updateProfile(Request $request, SupabaseStorageService $storage): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email'],
            'contact_phone' => ['required', 'string'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $documentPaths = array_map(
            fn ($file) => $storage->uploadPrivate($file->getRealPath()),
            $request->file('documents'),
        );

        $vendor = $request->user()->vendor ?? $request->user()->vendor()->create([
            'business_name' => $validated['business_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
        ]);

        $vendor->update([
            'business_name' => $validated['business_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
            'verification_documents' => $documentPaths,
            'documents_status' => 'pending',
        ]);

        return redirect()->route('vendor.onboarding')->with('success', 'Documents submitted for review.');
    }

    /**
     * Step 4/5: automated bank verification, zero human review — see
     * App\Services\Vendor\VendorOnboardingService::submitBankDetails().
     */
    public function submitBankDetails(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'digits:10'],
            'bank_code' => ['required', 'string'],
            'bank_name' => ['required', 'string'],
        ]);

        $this->onboardingService->submitBankDetails(
            $request->user()->vendor,
            $validated['account_number'],
            $validated['bank_code'],
            $validated['bank_name'],
        );

        return redirect()->route('vendor.onboarding')->with('success', 'Bank details submitted — verifying now.');
    }
}
