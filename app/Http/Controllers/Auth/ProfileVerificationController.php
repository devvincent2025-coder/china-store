<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\Auth\BuyerSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Buyer signup, steps 3-4 of 4: collect the real name + phone number, verify the phone via a
 * 4-digit SMS code, then celebrate. Backs the CompleteProfileModal shown on the storefront home
 * page whenever User::needsPhoneVerification() is true — see EnsureBuyerCompletesSignup, which
 * keeps a buyer confined to the home page (where the modal lives) until this is done.
 */
class ProfileVerificationController extends Controller
{
    public function __construct(private readonly BuyerSignupService $signup)
    {
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', Rule::unique(User::class, 'phone')->ignore($user->id)],
        ]);

        $autoVerified = $this->signup->updateProfileAndSendPhoneOtp($user, $validated['name'], $validated['phone']);

        // config('services.smart_sms.phone_otp_required') is off — the phone was accepted and
        // marked verified immediately, with no code sent, so there's no OTP step for
        // CompleteProfileModal.jsx to show at all. Finish signup right here instead.
        if ($autoVerified) {
            return $this->finishSignup($user);
        }

        return back()->with('success', 'A verification code was sent to your phone.');
    }

    /**
     * @throws ValidationException
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $this->signup->resendPhoneOtp($request->user());

        return back()->with('success', 'A new code has been sent to your phone.');
    }

    /**
     * @throws ValidationException
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ]);

        $user = $request->user();

        $this->signup->verifyPhoneOtp($user, $validated['code']);

        return $this->finishSignup($user);
    }

    /**
     * Fires the welcome email/notification and sends the buyer home to see WelcomeModal.jsx —
     * shared by the normal "code verified" path above and the phone_otp_required=false bypass in
     * store(), which skips the code step entirely but still needs to end up in the same place.
     */
    private function finishSignup(User $user): RedirectResponse
    {
        $user->notify(new WelcomeNotification);

        // The storefront home page reads this flash value once to trigger the welcome modal — see
        // resources/js/Components/WelcomeModal.jsx and its wiring in Pages/Home.jsx.
        return redirect()->route('home')->with('justRegistered', true);
    }
}
