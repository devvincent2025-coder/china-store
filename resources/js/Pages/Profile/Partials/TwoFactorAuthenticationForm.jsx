import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import EyeToggleButton from '@/Components/EyeToggleButton';
import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/useTranslation';

export default function TwoFactorAuthenticationForm({
    twoFactor = {},
    className = '',
}) {
    const { __ } = useTranslation();
    const { enabled, pendingSecretKey, qrCodeSvg, recoveryCodes } =
        twoFactor;

    const [showDisableConfirm, setShowDisableConfirm] = useState(false);
    const [enabling, setEnabling] = useState(false);
    const [disablePasswordVisible, setDisablePasswordVisible] = useState(false);

    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });

    const enableTwoFactor = () => {
        setEnabling(true);
        router.post(
            route('two-factor.enable'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setEnabling(false),
            },
        );
    };

    const confirmTwoFactor = (e) => {
        e.preventDefault();

        confirmForm.post(route('two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirmForm.reset(),
        });
    };

    const regenerateRecoveryCodes = () => {
        router.post(
            route('two-factor.recovery-codes'),
            {},
            { preserveScroll: true },
        );
    };

    const disableTwoFactor = (e) => {
        e.preventDefault();

        router.delete(route('two-factor.disable'), {
            data: { password: disableForm.data.password },
            preserveScroll: true,
            onSuccess: () => {
                disableForm.reset();
                setShowDisableConfirm(false);
            },
            onError: () => disableForm.reset('password'),
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    {__('Two-Factor Authentication')}
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    {__('Add an extra layer of security to your account using an authenticator app.')}
                </p>
            </header>

            <div className="mt-6 space-y-6">
                {!enabled && !pendingSecretKey && (
                    <div>
                        <p className="text-sm text-gray-600">
                            {__('Add an extra layer of security using an authenticator app like Google Authenticator or Authy.')}
                        </p>

                        <PrimaryButton
                            className="mt-4 bg-orange-600 hover:bg-orange-700 focus:bg-orange-700"
                            disabled={enabling}
                            onClick={enableTwoFactor}
                        >
                            {__('Enable Two-Factor Authentication')}
                        </PrimaryButton>
                    </div>
                )}

                {!enabled && pendingSecretKey && (
                    <div>
                        <p className="text-sm text-gray-600">
                            {__('Scan this QR code with your authenticator app, then enter the 6-digit code it generates to finish setting it up.')}
                        </p>

                        <div className="mt-4 inline-block rounded-lg border border-gray-200 bg-white p-4">
                            <div
                                className="h-48 w-48"
                                dangerouslySetInnerHTML={{
                                    __html: qrCodeSvg,
                                }}
                            />
                        </div>

                        <div className="mt-3">
                            <p className="text-xs font-medium text-gray-500">
                                {__('Or enter this code manually')}
                            </p>
                            <p className="mt-1 select-all rounded-md bg-neutral-100 px-3 py-2 font-mono text-sm text-gray-800">
                                {pendingSecretKey}
                            </p>
                        </div>

                        <form
                            onSubmit={confirmTwoFactor}
                            className="mt-4 max-w-xs"
                        >
                            <label
                                htmlFor="two_factor_code"
                                className="mb-1 block text-sm font-medium text-gray-700"
                            >
                                {__('Authentication Code')}
                            </label>

                            <input
                                id="two_factor_code"
                                type="text"
                                inputMode="numeric"
                                maxLength={6}
                                value={confirmForm.data.code}
                                onChange={(e) =>
                                    confirmForm.setData(
                                        'code',
                                        e.target.value.replace(/\D/g, ''),
                                    )
                                }
                                className="block w-full rounded-lg border-0 bg-neutral-100 px-4 py-2.5 text-center text-lg tracking-[0.4em] text-gray-900 focus:bg-white focus:ring-2 focus:ring-orange-500"
                            />

                            <InputError
                                message={confirmForm.errors.code}
                                className="mt-2"
                            />

                            <PrimaryButton
                                className="mt-3 bg-orange-600 hover:bg-orange-700 focus:bg-orange-700"
                                disabled={confirmForm.processing}
                            >
                                {__('Confirm')}
                            </PrimaryButton>
                        </form>
                    </div>
                )}

                {enabled && (
                    <div>
                        <span className="inline-flex items-center gap-2 rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700">
                            <svg
                                className="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                            {__('Two-factor authentication is enabled')}
                        </span>

                        <div className="mt-4 flex flex-wrap gap-3">
                            <SecondaryButton
                                onClick={regenerateRecoveryCodes}
                            >
                                {__('Regenerate Recovery Codes')}
                            </SecondaryButton>

                            {!showDisableConfirm && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowDisableConfirm(true)
                                    }
                                    className="rounded-md border border-red-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-600 transition hover:bg-red-50"
                                >
                                    {__('Disable')}
                                </button>
                            )}
                        </div>

                        {showDisableConfirm && (
                            <form
                                onSubmit={disableTwoFactor}
                                className="mt-4 max-w-xs rounded-lg border border-red-200 bg-red-50 p-4"
                            >
                                <label
                                    htmlFor="disable_2fa_password"
                                    className="mb-1 block text-sm font-medium text-gray-700"
                                >
                                    {__('Confirm your password to disable two-factor authentication')}
                                </label>

                                <div className="relative">
                                    <input
                                        id="disable_2fa_password"
                                        type={disablePasswordVisible ? 'text' : 'password'}
                                        value={disableForm.data.password}
                                        onChange={(e) =>
                                            disableForm.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                        className="block w-full rounded-lg border-0 bg-white px-4 py-2.5 pr-11 text-base text-gray-900 focus:ring-2 focus:ring-orange-500"
                                        autoFocus
                                    />
                                    <EyeToggleButton visible={disablePasswordVisible} onToggle={() => setDisablePasswordVisible((v) => !v)} />
                                </div>

                                <InputError
                                    message={disableForm.errors.password}
                                    className="mt-2"
                                />

                                <div className="mt-3 flex gap-2">
                                    <button
                                        type="submit"
                                        disabled={disableForm.processing}
                                        className="rounded-md bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-red-700 disabled:opacity-50"
                                    >
                                        {__('Confirm Disable')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowDisableConfirm(false);
                                            disableForm.reset();
                                        }}
                                        className="rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-500 hover:text-gray-700"
                                    >
                                        {__('Cancel')}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                )}

                {recoveryCodes && recoveryCodes.length > 0 && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4">
                        <p className="text-sm font-semibold text-amber-800">
                            {__("Save these recovery codes somewhere safe — they won't be shown again.")}
                        </p>
                        <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {recoveryCodes.map((rc) => (
                                <span
                                    key={rc}
                                    className="select-all rounded-md bg-white px-2 py-1.5 text-center font-mono text-xs text-gray-800"
                                >
                                    {rc}
                                </span>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
