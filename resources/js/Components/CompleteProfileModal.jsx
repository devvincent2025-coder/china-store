import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PhoneInput from '@/Components/PhoneInput';
import OtpInput from '@/Components/OtpInput';
import OtpResendCountdown from '@/Components/OtpResendCountdown';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Buyer signup, steps 3-4 of 4 — shown right after email verification (see
 * Auth\EmailOtpController::store()) whenever `auth.needsPhoneVerification` is true (see
 * HandleInertiaRequests and User::needsPhoneVerification()). Deliberately not closeable: this is a
 * required step, not an optional prompt — EnsureBuyerCompletesSignup keeps the buyer confined to
 * the home page until it's done, so there is nowhere for "close" to meaningfully send them anyway.
 */
export default function CompleteProfileModal() {
    const { __ } = useTranslation();
    const [step, setStep] = useState('profile'); // profile | phone_otp
    const [resendKey, setResendKey] = useState(0);

    const profileForm = useForm({ name: '', phone: '' });
    const otpForm = useForm({ code: '' });

    const submitProfile = (e) => {
        e.preventDefault();
        profileForm.post(route('register.complete-profile'), {
            preserveScroll: true,
            onSuccess: () => setStep('phone_otp'),
        });
    };

    const verifyOtp = (e) => {
        e.preventDefault();
        otpForm.post(route('register.verify-phone'), {
            preserveScroll: true,
        });
    };

    const resendOtp = () => {
        otpForm.clearErrors();
        otpForm.post(route('register.complete-profile.resend-otp'), {
            preserveScroll: true,
            onSuccess: () => setResendKey((k) => k + 1),
        });
    };

    return (
        <Modal show closeable={false} maxWidth="md">
            <div className="p-6 sm:p-8">
                {step === 'profile' ? (
                    <>
                        <h2 className="font-display text-xl font-bold text-gray-900">
                            {__("You're almost done!")}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {__("Tell us your name and phone number so we can finish setting up your account.")}
                        </p>

                        <form onSubmit={submitProfile} className="mt-6 space-y-4">
                            <div>
                                <InputLabel htmlFor="complete-profile-name" value={__('Full Name')} />
                                <TextInput
                                    id="complete-profile-name"
                                    className="mt-1 block w-full"
                                    value={profileForm.data.name}
                                    onChange={(e) => profileForm.setData('name', e.target.value)}
                                    autoFocus
                                    required
                                />
                                <InputError message={profileForm.errors.name} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="complete-profile-phone" value={__('Phone Number')} />
                                <PhoneInput
                                    id="complete-profile-phone"
                                    value={profileForm.data.phone}
                                    onChange={(phone) => profileForm.setData('phone', phone)}
                                    className="mt-1"
                                />
                                <InputError message={profileForm.errors.phone} className="mt-1" />
                            </div>

                            <button
                                type="submit"
                                disabled={profileForm.processing}
                                className="w-full rounded-lg bg-orange-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-orange-700 disabled:opacity-50"
                            >
                                {profileForm.processing ? __('Sending code…') : __('Send Verification Code')}
                            </button>
                        </form>
                    </>
                ) : (
                    <>
                        <h2 className="font-display text-xl font-bold text-gray-900">
                            {__('Verify your phone')}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {__('We sent a 4-digit code to :phone', { phone: profileForm.data.phone })}
                        </p>

                        <form onSubmit={verifyOtp} className="mt-6">
                            <OtpInput value={otpForm.data.code} onChange={(code) => otpForm.setData('code', code)} autoFocus />
                            <InputError message={otpForm.errors.code} className="mt-3 text-center" />

                            <button
                                type="submit"
                                disabled={otpForm.processing || otpForm.data.code.length !== 4}
                                className="mt-6 w-full rounded-lg bg-orange-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-orange-700 disabled:opacity-50"
                            >
                                {otpForm.processing ? __('Verifying…') : __('Verify')}
                            </button>
                        </form>

                        <div className="mt-4">
                            <OtpResendCountdown onResend={resendOtp} resending={otpForm.processing} resetKey={resendKey} />
                        </div>

                        <button
                            type="button"
                            onClick={() => setStep('profile')}
                            className="mt-4 w-full text-center text-sm text-gray-500 hover:underline"
                        >
                            {__('Use a different phone number')}
                        </button>
                    </>
                )}
            </div>
        </Modal>
    );
}
