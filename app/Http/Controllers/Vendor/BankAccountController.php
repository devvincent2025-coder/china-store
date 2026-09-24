<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Vendor\VendorBankChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BankAccountController extends Controller
{
    public function __construct(private readonly VendorBankChangeService $bankChange)
    {
    }

    /**
     * Live "who owns this account" preview, called from the bank-change form as the vendor types —
     * lets them confirm the name before committing to sending an OTP. This is purely informational;
     * requestChange() below re-resolves the account itself before acting on anything.
     */
    public function resolveAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'digits:10'],
            'bank_code' => ['required', 'string'],
        ]);

        try {
            $resolvedName = $this->bankChange->resolveAccountName($validated['account_number'], $validated['bank_code']);

            return response()->json(['resolved_name' => $resolvedName]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (KorapayRequestException $e) {
            return response()->json(['message' => 'Could not verify this account with your bank right now. Please try again shortly.'], 422);
        }
    }

    public function requestChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'digits:10'],
            'bank_code' => ['required', 'string'],
            'bank_name' => ['required', 'string'],
        ]);

        $this->bankChange->requestChange(
            $request->user()->vendor,
            $validated['account_number'],
            $validated['bank_code'],
            $validated['bank_name'],
        );

        return back()->with('success', 'A confirmation code was sent to your registered email.');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $this->bankChange->verifyOtp($request->user()->vendor, $validated['code']);

        return back()->with('success', 'Confirmed. Your request is now awaiting admin approval — our team will call you to confirm.');
    }
}
