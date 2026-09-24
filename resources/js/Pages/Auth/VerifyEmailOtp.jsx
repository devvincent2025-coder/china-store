import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import OtpInput from '@/Components/OtpInput';
import OtpResendCountdown from '@/Components/OtpResendCountdown';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Buyer signup, step 2 of 4 — confirm the 4-digit code emailed in step 1. See
 * Auth\EmailOtpController; nothing is persisted yet, so reloading this page mid-flow is safe as
 * long as the session (holding the pending email+password) hasn't expired.
 */
export default function VerifyEmailOtp({ maskedEmail }) {
    const { __ } = useTranslation();
    const [code, setCode] = useState('');
    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [resending, setResending] = useState(false);
    const [resendKey, setResendKey] = useState(0);

    const submit = (e) => {
        e?.preventDefault();
        if (code.length !== 4) return;

        setProcessing(true);
        setError(null);

        router.post(route('register.verify-email'), { code }, {
            onError: (errors) => setError(errors.code ?? null),
            onFinish: () => setProcessing(false),
        });
    };

    const resend = () => {
        setResending(true);
        setError(null);

        router.post(route('register.verify-email.resend'), {}, {
            preserveScroll: true,
            onSuccess: () => setResendKey((k) => k + 1),
            onError: (errors) => setError(errors.code ?? null),
            onFinish: () => setResending(false),
        });
    };

    return (
        <div className="flex min-h-screen flex-col items-center bg-neutral-50 px-4 py-10 sm:justify-center sm:py-16">
            <Head title={__('Verify your email')} />

            <Link href="/" className="mb-8">
                <ApplicationLogo className="h-20 w-auto sm:h-24 md:h-28" />
            </Link>

            <div className="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-xl sm:p-8">
                <h1 className="font-display mb-1 text-xl font-bold text-gray-900">
                    {__('Check your email')}
                </h1>
                <p className="mb-6 text-sm text-gray-500">
                    {__('We sent a 4-digit code to :email', { email: maskedEmail })}
                </p>

                <form onSubmit={submit}>
                    <OtpInput value={code} onChange={setCode} autoFocus />
                    <InputError message={error} className="mt-3 text-center" />

                    <button
                        type="submit"
                        disabled={processing || code.length !== 4}
                        className="mt-6 w-full rounded-lg bg-orange-600 px-4 py-3.5 text-base font-bold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        {processing ? __('Verifying…') : __('Verify')}
                    </button>
                </form>

                <div className="mt-5">
                    <OtpResendCountdown onResend={resend} resending={resending} resetKey={resendKey} />
                </div>

                <p className="mt-6 text-sm text-gray-600">
                    <Link href={route('register')} className="font-semibold text-orange-600 hover:underline">
                        {__('Use a different email')}
                    </Link>
                </p>
            </div>
        </div>
    );
}
