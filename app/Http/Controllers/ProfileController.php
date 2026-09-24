<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Auth\TwoFactorAuthenticationService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, TwoFactorAuthenticationService $twoFactor): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'defaultShippingAddress' => $user->shippingAddresses()->where('is_default', true)->first(),
            'twoFactor' => [
                'enabled' => $user->hasEnabledTwoFactorAuthentication(),
                // A secret exists but isn't confirmed yet mid-setup — keep showing the QR/secret
                // until the user confirms it or starts over, rather than a one-shot reveal.
                'pendingSecretKey' => (! $user->hasEnabledTwoFactorAuthentication() && $user->two_factor_secret)
                    ? $user->two_factor_secret
                    : null,
                'qrCodeSvg' => (! $user->hasEnabledTwoFactorAuthentication() && $user->two_factor_secret)
                    ? $twoFactor->qrCodeSvg($user, $user->two_factor_secret)
                    : null,
                // Recovery codes are only ever exposed via flash data right after they're
                // (re)generated — never persisted into a normal page prop.
                'recoveryCodes' => session('recovery_codes'),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Quick-edit for the buyer's default entry in their address book (see ShippingAddress) — the
     * full book itself (add/select any of several saved addresses) lives on the checkout page,
     * this is just a shortcut so a buyer doesn't have to go through checkout to fix their default.
     * Updates the existing default row in place if one exists, otherwise creates the buyer's first
     * saved address and marks it default.
     */
    public function updateDefaultAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
        ]);

        $request->user()->shippingAddresses()->updateOrCreate(
            ['is_default' => true],
            $validated,
        );

        return back()->with('success', 'Default address saved.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
