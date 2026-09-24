import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import StatusBadge from '@/Components/StatusBadge';
import { useTranslation } from '@/hooks/useTranslation';

const DOCUMENT_TYPES = [
    { value: 'passport', label: 'Passport' },
    { value: 'national_id', label: 'National ID' },
    { value: 'drivers_license', label: "Driver's License" },
    { value: 'voters_card', label: "Voter's Card" },
];

/**
 * KYC ("know your customer") verification — three independent checks a vendor must clear before
 * any escrow payout is released (see App\Services\Escrow\EscrowService::release() and
 * Vendor::hasCompletedKyc()). Deliberately NOT required to complete onboarding or go live; this is
 * only surfaced as a dashboard banner + this modal, never a blocking gate on selling itself.
 */
export default function KycModal({ show, onClose, vendor }) {
    const { __ } = useTranslation();
    const { auth } = usePage().props;
    const emailVerified = !!auth?.user?.email_verified_at;
    const phoneVerified = !!vendor?.phone_verified_at;
    const identityStatus = vendor?.identity_verification_status ?? 'not_submitted';

    const [otpStep, setOtpStep] = useState('idle'); // idle | sent
    const [otpSendError, setOtpSendError] = useState(null);
    const otpForm = useForm({ code: '' });
    const documentForm = useForm({ document_type: '', document: null });
    const [isDragging, setIsDragging] = useState(false);

    const addDocumentFile = (fileList) => {
        const file = fileList?.[0];
        if (file) {
            documentForm.setData('document', file);
        }
    };

    const handleDocumentDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        addDocumentFile(e.dataTransfer.files);
    };

    const resendEmail = () => {
        router.post(route('verification.send'), {}, { preserveScroll: true });
    };

    const sendPhoneOtp = () => {
        setOtpSendError(null);
        router.post(route('vendor.identity.phone.send-otp'), {}, {
            preserveScroll: true,
            onSuccess: () => setOtpStep('sent'),
            onError: (errors) => setOtpSendError(errors.phone ?? null),
        });
    };

    const verifyPhoneOtp = (e) => {
        e.preventDefault();
        otpForm.post(route('vendor.identity.phone.verify-otp'), {
            preserveScroll: true,
            onSuccess: () => { otpForm.reset(); setOtpStep('idle'); },
        });
    };

    const submitDocument = (e) => {
        e.preventDefault();
        documentForm.post(route('vendor.identity.document'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => documentForm.reset(),
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="xl">
            <div className="p-6">
                <h2 className="text-lg font-bold text-neutral-900">{__('Complete Your Identity Verification (KYC)')}</h2>
                <p className="mt-1 text-sm text-neutral-500">
                    {__('Required before we can release escrow payouts to your bank account — this protects both you and your buyers.')}
                </p>

                <div className="mt-6 space-y-5">
                    {/* Email */}
                    <div className="flex items-start justify-between gap-4 rounded-lg border border-neutral-200 p-4">
                        <div>
                            <p className="text-sm font-semibold text-neutral-800">{__('1. Verify your email')}</p>
                            <p className="mt-0.5 text-xs text-neutral-500">{auth?.user?.email}</p>
                        </div>
                        {emailVerified ? (
                            <StatusBadge status="verified" />
                        ) : (
                            <button type="button" onClick={resendEmail} className="shrink-0 rounded-md bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-orange-700">
                                {__('Send Link')}
                            </button>
                        )}
                    </div>

                    {/* Phone */}
                    <div className="rounded-lg border border-neutral-200 p-4">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-neutral-800">{__('2. Verify your phone number')}</p>
                                <p className="mt-0.5 text-xs text-neutral-500">{vendor?.contact_phone}</p>
                            </div>
                            {phoneVerified ? (
                                <StatusBadge status="verified" />
                            ) : otpStep === 'idle' ? (
                                <button type="button" onClick={sendPhoneOtp} className="shrink-0 rounded-md bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-orange-700">
                                    {__('Send Code')}
                                </button>
                            ) : null}
                        </div>
                        {otpSendError && <p className="mt-2 text-xs text-red-600">{otpSendError}</p>}
                        {!phoneVerified && otpStep === 'sent' && (
                            <form onSubmit={verifyPhoneOtp} className="mt-3 flex items-start gap-2">
                                <div className="flex-1">
                                    <TextInput
                                        value={otpForm.data.code}
                                        onChange={(e) => otpForm.setData('code', e.target.value)}
                                        placeholder={__('6-digit code')}
                                        maxLength={6}
                                        className="w-full text-center tracking-[0.4em]"
                                    />
                                    <InputError message={otpForm.errors.code} className="mt-1" />
                                </div>
                                <button type="submit" disabled={otpForm.processing} className="rounded-md bg-neutral-800 px-3 py-2 text-xs font-semibold text-white hover:bg-neutral-900 disabled:opacity-50">
                                    {__('Confirm')}
                                </button>
                            </form>
                        )}
                    </div>

                    {/* Identity document */}
                    <div className="rounded-lg border border-neutral-200 p-4">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-neutral-800">{__('3. Upload a government ID')}</p>
                                <p className="mt-0.5 text-xs text-neutral-500">{__('PDF, JPG, or PNG — reviewed by our team, usually within one business day.')}</p>
                            </div>
                            <StatusBadge status={identityStatus} />
                        </div>
                        {identityStatus === 'rejected' && vendor?.identity_rejection_reason && (
                            <p className="mt-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{vendor.identity_rejection_reason}</p>
                        )}
                        {(identityStatus === 'not_submitted' || identityStatus === 'rejected') && (
                            <form onSubmit={submitDocument} className="mt-3 space-y-3">
                                <div>
                                    <select
                                        value={documentForm.data.document_type}
                                        onChange={(e) => documentForm.setData('document_type', e.target.value)}
                                        className="w-full rounded-md border-neutral-300 text-sm text-neutral-700 focus:border-orange-500 focus:ring-orange-500"
                                    >
                                        <option value="">{__('Select the ID you wish to upload')}</option>
                                        {DOCUMENT_TYPES.map((type) => (
                                            <option key={type.value} value={type.value}>
                                                {__(type.label)}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={documentForm.errors.document_type} className="mt-1" />
                                </div>

                                <label
                                    htmlFor="kyc-document-input"
                                    onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                                    onDragLeave={() => setIsDragging(false)}
                                    onDrop={handleDocumentDrop}
                                    className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-6 text-center transition ${
                                        isDragging ? 'border-orange-500 bg-orange-50' : 'border-neutral-300 bg-neutral-50 hover:border-orange-300 hover:bg-orange-50/50'
                                    }`}
                                >
                                    <svg className="mb-2 h-6 w-6 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <p className="text-xs font-medium text-neutral-700">
                                        {documentForm.data.document
                                            ? documentForm.data.document.name
                                            : isDragging
                                                ? __('Drop your document here')
                                                : __('Drag and drop your document here, or click to browse')}
                                    </p>
                                    <p className="mt-1 text-[11px] text-neutral-400">{__('PDF, JPG, or PNG — up to 10MB')}</p>
                                    <input
                                        id="kyc-document-input"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(e) => addDocumentFile(e.target.files)}
                                        className="hidden"
                                    />
                                </label>
                                <InputError message={documentForm.errors.document} className="mt-1" />

                                <button
                                    type="submit"
                                    disabled={documentForm.processing || !documentForm.data.document || !documentForm.data.document_type}
                                    className="w-full rounded-md bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                                >
                                    {__('Submit')}
                                </button>
                            </form>
                        )}
                    </div>
                </div>

                <div className="mt-6 flex justify-end">
                    <button type="button" onClick={onClose} className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50">
                        {__('Close')}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
