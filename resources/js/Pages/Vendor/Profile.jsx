import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import StatusBadge from '@/Components/StatusBadge';
import AvatarUploader from '@/Components/AvatarUploader';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Read-only account profile (what the vendor submitted during onboarding) plus the bank-account
 * change request flow — see App\Services\Vendor\VendorBankChangeService for why this is a
 * three-step process (email OTP, a support phone call, then admin approval) rather than a plain
 * edit form.
 */
export default function Profile({ vendor, banks, user }) {
    const { __ } = useTranslation();
    const [step, setStep] = useState(vendor?.bank_change_status === 'otp_pending' ? 'otp' : 'form');

    const requestForm = useForm({
        account_number: '',
        bank_code: '',
        bank_name: '',
    });

    const otpForm = useForm({ code: '' });

    // Live "who owns this account" preview — resolved via Korapay as the vendor finishes typing
    // the account number, so they can confirm the name before an OTP is even sent. requestChange()
    // on the backend re-resolves this itself regardless, so this is purely a preview.
    const [resolveState, setResolveState] = useState('idle'); // idle | resolving | resolved | error
    const [resolvedName, setResolvedName] = useState(null);
    const [resolveError, setResolveError] = useState(null);

    useEffect(() => {
        setResolvedName(null);
        setResolveError(null);

        if (requestForm.data.account_number.length !== 10 || !requestForm.data.bank_code) {
            setResolveState('idle');
            return;
        }

        setResolveState('resolving');
        const timeout = setTimeout(() => {
            window.axios
                .post(route('vendor.profile.bank-change.resolve'), {
                    account_number: requestForm.data.account_number,
                    bank_code: requestForm.data.bank_code,
                })
                .then((response) => {
                    setResolvedName(response.data.resolved_name);
                    setResolveState('resolved');
                })
                .catch((error) => {
                    setResolveError(error.response?.data?.message ?? __('Could not verify this account.'));
                    setResolveState('error');
                });
        }, 500);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [requestForm.data.account_number, requestForm.data.bank_code]);

    const submitRequest = (e) => {
        e.preventDefault();
        requestForm.post(route('vendor.profile.bank-change.request'), {
            preserveScroll: true,
            onSuccess: () => {
                setStep('otp');
                setResolveState('idle');
                setResolvedName(null);
            },
        });
    };

    const submitOtp = (e) => {
        e.preventDefault();
        otpForm.post(route('vendor.profile.bank-change.verify'), {
            preserveScroll: true,
            onSuccess: () => {
                otpForm.reset();
                requestForm.reset();
                setStep('form');
            },
        });
    };

    const status = vendor?.bank_change_status ?? 'none';

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Profile')} />

            <h1 className="mb-6 text-xl font-bold text-neutral-900">{__('Account Profile')}</h1>

            <div className="mb-6 rounded-lg border border-neutral-200 bg-white p-5">
                <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Profile Photo')}</h2>
                <AvatarUploader user={user} />
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Business profile, read-only summary of what was submitted at onboarding */}
                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Business Profile')}</h2>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Business Name')}</dt>
                            <dd className="font-medium text-neutral-800">{vendor?.business_name ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Contact Email')}</dt>
                            <dd className="font-medium text-neutral-800">{vendor?.contact_email ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Contact Phone')}</dt>
                            <dd className="font-medium text-neutral-800">{vendor?.contact_phone ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Documents Status')}</dt>
                            <dd><StatusBadge status={vendor?.documents_status ?? 'not_submitted'} /></dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Store Status')}</dt>
                            <dd><StatusBadge status={vendor?.status ?? 'pending'} /></dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Commission Rate')}</dt>
                            <dd className="font-medium text-neutral-800">{vendor?.commission_rate ?? 0}%</dd>
                        </div>
                    </dl>
                </div>

                {/* Current bank details + change request */}
                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Payout Bank Account')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__('Changing this account requires an email confirmation code, a phone call from our support team, and final admin approval — this protects your payouts.')}
                    </p>

                    <dl className="mb-5 space-y-3 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Bank')}</dt>
                            <dd className="font-medium text-neutral-800">{vendor?.bank_name ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Account Number')}</dt>
                            <dd className="font-medium text-neutral-800">{vendor?.account_number ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Account Name')}</dt>
                            <dd className="font-medium text-neutral-800">{vendor?.resolved_account_name ?? '—'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-neutral-500">{__('Verification Status')}</dt>
                            <dd><StatusBadge status={vendor?.bank_verification_status ?? 'not_submitted'} /></dd>
                        </div>
                    </dl>

                    {status === 'awaiting_approval' && (
                        <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800">
                            {__('Your bank account change is confirmed and awaiting admin approval. Expect a call from our support team shortly.')}
                        </div>
                    )}

                    {status === 'none' && step === 'form' && (
                        <>
                            <button
                                type="button"
                                onClick={() => setStep('request')}
                                className="w-full rounded-md border border-orange-600 py-2 text-sm font-semibold text-orange-600 hover:bg-orange-50"
                            >
                                {__('Request Bank Account Change')}
                            </button>
                        </>
                    )}

                    {step === 'request' && status === 'none' && (
                        <form onSubmit={submitRequest} className="space-y-4 border-t border-neutral-100 pt-4">
                            {(!banks || banks.length === 0) && (
                                <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800">
                                    {__("We couldn't load the bank list right now. Please try again in a few minutes.")}
                                </div>
                            )}
                            <div>
                                <InputLabel htmlFor="account_number" value={__('New Account Number')} />
                                <TextInput
                                    id="account_number"
                                    className="mt-1 block w-full"
                                    maxLength={10}
                                    value={requestForm.data.account_number}
                                    onChange={(e) => requestForm.setData('account_number', e.target.value)}
                                />
                                <InputError message={requestForm.errors.account_number} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="bank_code" value={__('New Bank')} />
                                <select
                                    id="bank_code"
                                    value={requestForm.data.bank_code}
                                    onChange={(e) => {
                                        const bank = (banks ?? []).find((b) => b.code === e.target.value);
                                        requestForm.setData('bank_code', e.target.value);
                                        requestForm.setData('bank_name', bank?.name ?? '');
                                    }}
                                    className="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                >
                                    <option value="">{__('Select bank...')}</option>
                                    {(banks ?? []).map((bank) => (
                                        <option key={bank.code} value={bank.code}>{bank.name}</option>
                                    ))}
                                </select>
                                <InputError message={requestForm.errors.bank_code} className="mt-1" />
                            </div>

                            {resolveState === 'resolving' && (
                                <p className="text-xs text-neutral-500">{__('Verifying account name...')}</p>
                            )}
                            {resolveState === 'resolved' && resolvedName && (
                                <div className="rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                                    <span className="font-semibold">{__('Account name:')}</span> {resolvedName}
                                </div>
                            )}
                            {resolveState === 'error' && (
                                <div className="rounded-md border border-red-300 bg-red-50 p-3 text-xs text-red-700">
                                    {resolveError}
                                </div>
                            )}

                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={requestForm.processing || resolveState !== 'resolved'}
                                    className="flex-1 rounded-md bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                                >
                                    {__('Send Confirmation Code')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setStep('form')}
                                    className="rounded-md border border-neutral-300 px-4 text-sm text-neutral-600 hover:bg-neutral-50"
                                >
                                    {__('Cancel')}
                                </button>
                            </div>
                        </form>
                    )}

                    {step === 'otp' && (
                        <form onSubmit={submitOtp} className="space-y-4 border-t border-neutral-100 pt-4">
                            <p className="text-xs text-neutral-500">
                                {__('Enter the 6-digit code sent to your registered email to confirm this change.')}
                            </p>
                            <div>
                                <InputLabel htmlFor="code" value={__('Confirmation Code')} />
                                <TextInput
                                    id="code"
                                    className="mt-1 block w-full text-center text-lg tracking-[0.5em]"
                                    maxLength={6}
                                    value={otpForm.data.code}
                                    onChange={(e) => otpForm.setData('code', e.target.value)}
                                />
                                <InputError message={otpForm.errors.code} className="mt-1" />
                            </div>
                            <button
                                type="submit"
                                disabled={otpForm.processing}
                                className="w-full rounded-md bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                            >
                                {__('Confirm Change')}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </VendorLayout>
    );
}
