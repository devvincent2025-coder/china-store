<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\BuyerEmailOtpNotification;
use App\Services\Sms\SmsSendException;
use App\Services\Sms\SmsService;
use App\Support\Dev\OtpLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The new buyer signup flow: email+password -> 4-digit email OTP -> name+phone -> 4-digit phone
 * OTP -> welcome. Replaces the old flow where an account was created immediately and Laravel's
 * stock MustVerifyEmail sent a signed-link email instead of a code (see App\Models\User and the
 * now-unused `sendEmailVerificationNotification()` — this flow never calls it for new buyers).
 *
 * Deliberately does NOT create a User row until the email OTP is verified: while it's just an
 * email+password pair being confirmed, there's nothing worth persisting yet, so an abandoned
 * signup leaves no orphaned account behind. The email OTP is therefore held in the SESSION rather
 * than a database row — there's no user to attach it to yet. Phone verification happens after the
 * account exists, so it reuses one hash+expiry+cooldown slot on the `users` row itself, the same
 * pattern App\Services\Vendor\VendorIdentityService uses for a vendor's phone OTP.
 */
class BuyerSignupService
{
    private const OTP_TTL_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 60;

    // A 4-digit code is only 10,000 possibilities — without a cap on wrong guesses, an automated
    // script could brute-force it well within the 10-minute expiry window over an otherwise
    // correctly-throttled endpoint. 5 wrong tries per 10 minutes makes that infeasible while still
    // giving a real user (fat-fingering the code) several genuine retries.
    private const MAX_VERIFY_ATTEMPTS = 5;

    private const VERIFY_LOCKOUT_SECONDS = 600;

    private const SESSION_KEY = 'buyer_signup';

    public function __construct(private readonly SmsService $sms)
    {
    }

    public function startRegistration(Request $request, string $email, string $hashedPassword): void
    {
        $code = $this->generateCode();

        $request->session()->put(self::SESSION_KEY, [
            'email' => $email,
            'password' => $hashedPassword,
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->toIso8601String(),
            'otp_last_sent_at' => now()->toIso8601String(),
        ]);

        OtpLogger::record('buyer_signup_email', $email, $code);

        Notification::route('mail', $email)->notify(new BuyerEmailOtpNotification($code));
    }

    public function pendingEmail(Request $request): ?string
    {
        return $request->session()->get(self::SESSION_KEY)['email'] ?? null;
    }

    public function resendEmailOtp(Request $request): void
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! $pending) {
            throw ValidationException::withMessages(['code' => 'Your session has expired. Please start again.']);
        }

        $this->assertCooldownElapsed($pending['otp_last_sent_at'] ?? null);

        $code = $this->generateCode();
        $pending['otp_hash'] = Hash::make($code);
        $pending['otp_expires_at'] = now()->addMinutes(self::OTP_TTL_MINUTES)->toIso8601String();
        $pending['otp_last_sent_at'] = now()->toIso8601String();
        $request->session()->put(self::SESSION_KEY, $pending);

        // A fresh code deserves a fresh set of verify attempts — otherwise a buyer who mistyped
        // the old code a few times and legitimately requested a new one would still be locked out
        // from trying the NEW code.
        RateLimiter::clear('signup-email-otp:'.Str::lower($pending['email']).'|'.$request->ip());

        OtpLogger::record('buyer_signup_email', $pending['email'], $code);

        Notification::route('mail', $pending['email'])->notify(new BuyerEmailOtpNotification($code));
    }

    public function verifyEmailOtp(Request $request, string $code): User
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! $pending) {
            throw ValidationException::withMessages(['code' => 'Your session has expired. Please start again.']);
        }

        $throttleKey = 'signup-email-otp:'.Str::lower($pending['email']).'|'.$request->ip();
        $this->assertVerifyNotRateLimited($throttleKey);

        if (Carbon::parse($pending['otp_expires_at'])->isPast()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Please request a new one.']);
        }

        if (! Hash::check($code, $pending['otp_hash'])) {
            RateLimiter::hit($throttleKey, self::VERIFY_LOCKOUT_SECONDS);

            throw ValidationException::withMessages(['code' => 'That code is incorrect.']);
        }

        RateLimiter::clear($throttleKey);

        $user = User::create([
            // A placeholder until the next step collects the buyer's real name — the `name` column
            // is NOT NULL, and asking for it before email is even confirmed is exactly the extra
            // friction this redesigned flow is meant to remove.
            'name' => Str::before($pending['email'], '@'),
            'email' => $pending['email'],
            'password' => $pending['password'],
            'requires_phone_verification' => true,
        ]);

        // email_verified_at isn't mass-assignable (deliberately — nothing should be able to just
        // pass it in a request), so it has to be set this way rather than via the create() above.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole('buyer');

        $request->session()->forget(self::SESSION_KEY);

        return $user;
    }

    public function updateProfileAndSendPhoneOtp(User $user, string $name, string $phone): void
    {
        $this->assertCooldownElapsed($user->otp_last_sent_at?->toIso8601String());

        $code = $this->generateCode();

        try {
            $this->sms->send($phone, "Your ChinaInNigeria verification code is {$code}. It expires in 10 minutes.");
        } catch (SmsSendException $e) {
            // See the matching comment in VendorIdentityService::sendPhoneOtp — a local gateway
            // outage shouldn't block testing the rest of signup; the code is still persisted below
            // and visible on /dev/otps. Production/staging still fails loudly.
            if (! app()->environment('local')) {
                throw ValidationException::withMessages([
                    'phone' => 'We could not send a verification code to your phone right now. Please try again shortly.',
                ]);
            }

            Log::warning('SMS send failed in local env — continuing so the OTP stays testable via /dev/otps', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        $user->update([
            'name' => $name,
            'phone' => $phone,
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'otp_last_sent_at' => now(),
        ]);

        RateLimiter::clear('signup-phone-otp:'.$user->id);
        OtpLogger::record('buyer_signup_phone', $phone, $code);
    }

    public function resendPhoneOtp(User $user): void
    {
        if (! $user->phone) {
            throw ValidationException::withMessages(['phone' => 'Please enter your phone number first.']);
        }

        $this->assertCooldownElapsed($user->otp_last_sent_at?->toIso8601String());

        $code = $this->generateCode();

        try {
            $this->sms->send($user->phone, "Your ChinaInNigeria verification code is {$code}. It expires in 10 minutes.");
        } catch (SmsSendException $e) {
            if (! app()->environment('local')) {
                throw ValidationException::withMessages([
                    'phone' => 'We could not send a verification code to your phone right now. Please try again shortly.',
                ]);
            }

            Log::warning('SMS send failed in local env — continuing so the OTP stays testable via /dev/otps', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        $user->update([
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'otp_last_sent_at' => now(),
        ]);

        RateLimiter::clear('signup-phone-otp:'.$user->id);
        OtpLogger::record('buyer_signup_phone', $user->phone, $code);
    }

    public function verifyPhoneOtp(User $user, string $code): void
    {
        $throttleKey = 'signup-phone-otp:'.$user->id;
        $this->assertVerifyNotRateLimited($throttleKey);

        if (! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Please request a new one.']);
        }

        if (! $user->otp_hash || ! Hash::check($code, $user->otp_hash)) {
            RateLimiter::hit($throttleKey, self::VERIFY_LOCKOUT_SECONDS);

            throw ValidationException::withMessages(['code' => 'That code is incorrect.']);
        }

        RateLimiter::clear($throttleKey);

        $user->update([
            'phone_verified_at' => now(),
            'requires_phone_verification' => false,
            'otp_hash' => null,
            'otp_expires_at' => null,
        ]);
    }

    private function generateCode(): string
    {
        return (string) random_int(1000, 9999);
    }

    private function assertCooldownElapsed(?string $lastSentAt): void
    {
        if ($lastSentAt && Carbon::parse($lastSentAt)->diffInSeconds(now(), true) < self::RESEND_COOLDOWN_SECONDS) {
            throw ValidationException::withMessages(['code' => 'Please wait a moment before requesting another code.']);
        }
    }

    /**
     * Caps wrong-code guesses at MAX_VERIFY_ATTEMPTS within VERIFY_LOCKOUT_SECONDS — cache-backed
     * (Laravel's RateLimiter facade), the same mechanism/style as the login throttle in
     * AuthenticatedSessionController, just keyed to this signup step instead of a login attempt.
     */
    private function assertVerifyNotRateLimited(string $throttleKey): void
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, self::MAX_VERIFY_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'code' => "Too many incorrect attempts. Please try again in {$seconds} seconds, or request a new code.",
        ]);
    }
}
