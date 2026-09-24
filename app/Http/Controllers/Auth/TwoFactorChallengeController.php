<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticationService;
use App\Services\Cart\CartResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Second step of login for accounts with confirmed TOTP two-factor authentication. Only reachable
 * after AuthenticatedSessionController::store() has already validated the password and stashed the
 * pending user id in the session — a direct visit with no pending challenge bounces to /login.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly CartResolver $carts)
    {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request, TwoFactorAuthenticationService $twoFactor): RedirectResponse
    {
        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = User::findOrFail($userId);

        $verified = $this->verifyCode($user, $validated, $twoFactor);

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code was invalid.',
            ]);
        }

        Auth::login($user, (bool) $request->session()->get('login.remember', false));

        $request->session()->forget(['login.id', 'login.remember']);
        $request->session()->regenerate();
        $this->carts->mergeGuestCartIntoUser($request, $user);

        return redirect()->intended($user->postLoginRoute());
    }

    private function verifyCode(User $user, array $validated, TwoFactorAuthenticationService $twoFactor): bool
    {
        if (filled($validated['code'] ?? null)) {
            return $twoFactor->verify($user->two_factor_secret, $validated['code']);
        }

        if (filled($validated['recovery_code'] ?? null)) {
            return $this->consumeRecoveryCode($user, $validated['recovery_code']);
        }

        return false;
    }

    /**
     * Recovery codes are single-use — a matched code is removed from the stored list immediately
     * so it can't be replayed.
     */
    private function consumeRecoveryCode(User $user, string $submitted): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = strtoupper(trim($submitted));

        if (! in_array($normalized, $codes, true)) {
            return false;
        }

        $user->update([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$normalized])),
        ]);

        return true;
    }
}
