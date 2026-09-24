<?php

namespace App\Support\Dev;

use App\Models\DevOtpLog;

/**
 * Mirrors every OTP code generated anywhere in the app (buyer signup email/phone, vendor phone
 * KYC, vendor bank-change email) into a small local-only table, purely so a developer without a
 * working SMS/email gateway can copy-paste the code straight from a browser tab (see
 * routes/dev.php's /dev/otps page) instead of digging through storage/logs/laravel.log.
 *
 * `record()` is a hard no-op outside `local` — checked here, not just by the caller, so a real
 * code sent to a real customer's phone/email can never end up persisted in plaintext in staging
 * or production no matter what calls this.
 */
class OtpLogger
{
    /**
     * Keeps the table from growing forever in a long-running local environment — a dev debugging
     * "why didn't my OTP arrive" only ever needs the most recent handful, never the full history.
     */
    private const MAX_ROWS = 200;

    public static function record(string $purpose, string $identifier, string $code): void
    {
        if (! app()->environment('local')) {
            return;
        }

        DevOtpLog::create([
            'purpose' => $purpose,
            'identifier' => $identifier,
            'code' => $code,
            'created_at' => now(),
        ]);

        $excessIds = DevOtpLog::orderByDesc('id')
            ->skip(self::MAX_ROWS)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($excessIds->isNotEmpty()) {
            DevOtpLog::whereIn('id', $excessIds)->delete();
        }
    }
}
