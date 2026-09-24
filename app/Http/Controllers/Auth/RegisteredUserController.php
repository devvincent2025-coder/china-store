<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\BuyerSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer signup, step 1 of 4: email + password only. Step 2 (email OTP) lives in
 * EmailOtpController, steps 3-4 (name/phone + phone OTP) in ProfileVerificationController — see
 * App\Services\Auth\BuyerSignupService's class doc comment for the full flow and why no User row
 * exists until the email OTP is verified.
 */
class RegisteredUserController extends Controller
{
    public function __construct(private readonly BuyerSignupService $signup)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $this->signup->startRegistration($request, $validated['email'], Hash::make($validated['password']));

        return redirect()->route('register.verify-email');
    }
}
