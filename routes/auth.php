<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailOtpController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\ProfileVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VendorRegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Buyer signup, step 1 of 4: email + password — see RegisteredUserController's class doc
    // comment for the full 4-step flow.
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    // Step 2: confirm the 4-digit email code. Still guest-only — no account exists yet.
    Route::get('register/verify-email', [EmailOtpController::class, 'show'])
        ->name('register.verify-email');

    Route::post('register/verify-email', [EmailOtpController::class, 'store']);

    Route::post('register/verify-email/resend', [EmailOtpController::class, 'resend'])
        ->name('register.verify-email.resend');

    // Separate signup flow for vendors, distinct from the buyer registration above.
    Route::get('vendor/register', [VendorRegisteredUserController::class, 'create'])
        ->name('vendor.register');

    Route::post('vendor/register', [VendorRegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Second login step for accounts with confirmed TOTP 2FA — only reachable via the pending
    // session key AuthenticatedSessionController::store() sets, not a plain guest-visitable form.
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');

    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->name('two-factor.login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    // Buyer signup, steps 3-4 of 4: name+phone, then the 4-digit phone code — backs
    // CompleteProfileModal on Pages/Home.jsx. Deliberately NOT gated by
    // 'buyer.signup-complete' (see routes/web.php), since that's exactly the middleware these
    // routes exist to satisfy.
    Route::post('register/complete-profile', [ProfileVerificationController::class, 'store'])
        ->name('register.complete-profile');

    Route::post('register/complete-profile/resend-otp', [ProfileVerificationController::class, 'resendOtp'])
        ->name('register.complete-profile.resend-otp');

    Route::post('register/verify-phone', [ProfileVerificationController::class, 'verifyOtp'])
        ->name('register.verify-phone');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Account-settings-driven TOTP setup — see App\Http\Controllers\TwoFactorAuthenticationController
    // for why this is a three-step (generate -> confirm -> recovery codes) flow rather than a
    // single toggle.
    Route::post('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
        ->name('two-factor.enable');

    Route::post('user/confirmed-two-factor-authentication', [TwoFactorAuthenticationController::class, 'confirm'])
        ->name('two-factor.confirm');

    Route::delete('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
        ->name('two-factor.disable');

    Route::post('user/two-factor-recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])
        ->name('two-factor.recovery-codes');
});
