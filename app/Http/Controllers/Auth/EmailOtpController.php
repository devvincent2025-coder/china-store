<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\BuyerSignupService;
use App\Services\Cart\CartResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer signup, step 2 of 4: confirm the 4-digit code sent to the email address entered in step 1
 * (RegisteredUserController). Nothing is persisted until this succeeds — see BuyerSignupService.
 */
class EmailOtpController extends Controller
{
    public function __construct(
        private readonly BuyerSignupService $signup,
        private readonly CartResolver $carts,
    ) {
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $email = $this->signup->pendingEmail($request);

        if (! $email) {
            return redirect()->route('register');
        }

        return Inertia::render('Auth/VerifyEmailOtp', [
            'maskedEmail' => $this->maskEmail($email),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ]);

        $user = $this->signup->verifyEmailOtp($request, $validated['code']);

        Auth::login($user);
        $this->carts->mergeGuestCartIntoUser($request, $user);

        // Nothing renders a dedicated "complete profile" page — CompleteProfileModal lives on the
        // home page itself (see Pages/Home.jsx), driven by the shared `auth.user` prop rather than
        // a route, so it can keep showing there on reload until the buyer finishes phone
        // verification (see EnsureBuyerCompletesSignup).
        return redirect()->route('home');
    }

    /**
     * @throws ValidationException
     */
    public function resend(Request $request): RedirectResponse
    {
        $this->signup->resendEmailOtp($request);

        return back()->with('success', 'A new code has been sent to your email.');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        $visible = Str::substr($local, 0, min(2, strlen($local)));

        return $visible.str_repeat('*', max(1, strlen($local) - strlen($visible))).'@'.$domain;
    }
}
