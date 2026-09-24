import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * "Resend code in 0:47" countdown, matching the server's own 60-second cooldown (see
 * BuyerSignupService::RESEND_COOLDOWN_SECONDS) — purely a UX nicety, the server enforces the real
 * limit regardless of what this shows.
 */
export default function OtpResendCountdown({ seconds = 60, onResend, resending = false, resetKey }) {
    const { __ } = useTranslation();
    const [remaining, setRemaining] = useState(seconds);

    useEffect(() => {
        setRemaining(seconds);
    }, [resetKey, seconds]);

    useEffect(() => {
        if (remaining <= 0) return;
        const timer = setTimeout(() => setRemaining((s) => s - 1), 1000);
        return () => clearTimeout(timer);
    }, [remaining]);

    if (remaining > 0) {
        return (
            <p className="text-center text-sm text-gray-500">
                {__('Resend code in :seconds', { seconds: `0:${String(remaining).padStart(2, '0')}` })}
            </p>
        );
    }

    return (
        <button
            type="button"
            onClick={onResend}
            disabled={resending}
            className="w-full text-center text-sm font-semibold text-orange-600 hover:underline disabled:opacity-50"
        >
            {resending ? __('Sending…') : __('Resend code')}
        </button>
    );
}
