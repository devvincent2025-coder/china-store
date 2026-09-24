<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use App\Support\NigerianBanks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The vendor's own account profile — everything they submitted during onboarding (business
 * profile, documents status, bank verification), plus the entry point into the bank-change
 * request flow (see BankAccountController / VendorBankChangeService). This is distinct from
 * SettingsController, which only covers VAT/shipping configuration.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly KorapayService $korapay)
    {
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Vendor/Profile', [
            'vendor' => $request->user()->vendor,
            'user' => $request->user(),
            'banks' => $this->listBanksSafely(),
        ]);
    }

    /**
     * Korapay's bank list is only needed to populate the bank-change dropdown — it must never take
     * the whole profile page down if Korapay's API is unreachable or misconfigured (the rest of the
     * page, including the vendor's existing bank details, doesn't depend on it at all).
     */
    private function listBanksSafely(): array
    {
        try {
            return $this->korapay->listBanks();
        } catch (KorapayRequestException $e) {
            Log::warning('Could not load Korapay bank list for vendor profile page, using static fallback', ['message' => $e->getMessage()]);

            return NigerianBanks::list();
        }
    }
}
