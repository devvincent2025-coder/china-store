<?php

namespace App\Http\Controllers;

use App\Services\Auth\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Account-settings-driven TOTP setup — entirely opt-in, never required at signup or login. The
 * three-step flow: (1) generate a secret (unconfirmed), (2) confirm it with a real code from the
 * user's authenticator app, (3) show one-time recovery codes. Login only starts requiring a code
 * once step 2 succeeds — see User::hasEnabledTwoFactorAuthentication().
 */
class TwoFactorAuthenticationController extends Controller
{
    /**
     * Step 1: generate a pending secret. Not yet enforced at login — the settings page will keep
     * showing the QR/secret on every reload until it's confirmed or the user regenerates it.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return back();
        }

        $user->forceFill([
            'two_factor_secret' => app(TwoFactorAuthenticationService::class)->generateSecretKey(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    /**
     * Step 2: the user enters a code their authenticator app generated from the pending secret.
     */
    public function confirm(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->two_factor_secret || ! $twoFactor->verify($user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'The provided code was invalid.']);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        // Shown to the user exactly once — the settings page prop only reads this from flash data,
        // never from a stored "please display these again" flag.
        return back()->with('recovery_codes', $recoveryCodes);
    }

    /**
     * Requires the current password as a lightweight confirmation gate — disabling 2FA is a
     * meaningful security downgrade and shouldn't be a single accidental click.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return back();
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        $user->update(['two_factor_recovery_codes' => $recoveryCodes]);

        return back()->with('recovery_codes', $recoveryCodes);
    }
}
