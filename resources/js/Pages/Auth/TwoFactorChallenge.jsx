import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);

    const { data, setData, post, processing, errors, reset, transform } =
        useForm({
            code: '',
            recovery_code: '',
        });

    const submit = (e) => {
        e.preventDefault();

        transform((formData) =>
            useRecoveryCode
                ? { recovery_code: formData.recovery_code }
                : { code: formData.code },
        );

        post(route('two-factor.login'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    const toggleMode = () => {
        setUseRecoveryCode((v) => !v);
        reset('code', 'recovery_code');
    };

    return (
        <div className="flex min-h-screen flex-col items-center bg-neutral-50 px-4 py-10 sm:justify-center sm:py-16">
            <Head title="Two-Factor Authentication" />

            <Link href="/" className="mb-6">
                <ApplicationLogo className="h-12 w-auto sm:h-14" />
            </Link>

            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl sm:p-8">
                <h1 className="font-display mb-2 text-center text-xl font-bold text-gray-900">
                    Two-Factor Authentication
                </h1>

                <p className="mb-6 text-center text-sm text-gray-600">
                    {useRecoveryCode
                        ? 'Enter one of your recovery codes.'
                        : 'Enter the 6-digit code from your authenticator app.'}
                </p>

                <form onSubmit={submit}>
                    {useRecoveryCode ? (
                        <div>
                            <label
                                htmlFor="recovery_code"
                                className="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Recovery Code
                            </label>

                            <input
                                id="recovery_code"
                                type="text"
                                name="recovery_code"
                                value={data.recovery_code}
                                autoFocus
                                autoComplete="one-time-code"
                                className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-3 text-center font-mono text-sm text-gray-900 tracking-widest focus:bg-white focus:ring-2 focus:ring-orange-500"
                                onChange={(e) =>
                                    setData('recovery_code', e.target.value)
                                }
                            />
                        </div>
                    ) : (
                        <div>
                            <label
                                htmlFor="code"
                                className="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Authentication Code
                            </label>

                            <input
                                id="code"
                                type="text"
                                name="code"
                                inputMode="numeric"
                                pattern="[0-9]*"
                                maxLength={6}
                                value={data.code}
                                autoFocus
                                autoComplete="one-time-code"
                                className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-3 text-center text-2xl tracking-[0.5em] text-gray-900 focus:bg-white focus:ring-2 focus:ring-orange-500"
                                onChange={(e) =>
                                    setData(
                                        'code',
                                        e.target.value.replace(/\D/g, ''),
                                    )
                                }
                            />
                        </div>
                    )}

                    <InputError message={errors.code} className="mt-2" />

                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-6 w-full rounded-lg bg-orange-600 px-4 py-3.5 text-base font-bold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        Verify
                    </button>
                </form>

                <p className="mt-6 text-center text-sm">
                    <button
                        type="button"
                        onClick={toggleMode}
                        className="font-medium text-gray-500 underline hover:text-gray-700"
                    >
                        {useRecoveryCode
                            ? 'Use an authentication code instead'
                            : 'Use a recovery code instead'}
                    </button>
                </p>
            </div>
        </div>
    );
}
