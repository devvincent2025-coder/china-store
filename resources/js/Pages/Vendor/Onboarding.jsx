import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import StatusBadge from '@/Components/StatusBadge';
import VendorWelcomeModal from '@/Components/VendorWelcomeModal';
import { useTranslation } from '@/hooks/useTranslation';

// Kept in sync with OnboardingController::DOCUMENT_TYPES — same convention KycModal.jsx uses for
// the separate identity-document types.
const DOCUMENT_TYPES = [
    { value: 'cac_certificate', label: 'CAC Certificate / Business Registration' },
    { value: 'valid_id', label: 'Valid Government-issued ID' },
    { value: 'utility_bill', label: 'Utility Bill (Proof of Address)' },
    { value: 'other', label: 'Other' },
];

export default function Onboarding({ vendor, banks, documentUrl }) {
    const { __ } = useTranslation();
    const { flash } = usePage().props;
    const [showWelcome, setShowWelcome] = useState(!!flash?.justRegisteredVendor);

    const documentsStatus = vendor?.documents_status ?? 'not_submitted';
    // A submission is only ever editable when nothing's been sent yet, or the last one was
    // rejected — once it's pending review or approved, there's nothing to resubmit.
    const canSubmitDocument = documentsStatus === 'not_submitted' || documentsStatus === 'rejected';

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

    const submitDocument = (e) => {
        e.preventDefault();
        documentForm.post(route('vendor.onboarding.profile'), {
            forceFormData: true,
            onSuccess: () => documentForm.reset(),
        });
    };

    const bankStatus = vendor?.bank_verification_status ?? 'not_submitted';
    // Same idea as the document form above: only editable before anything's been sent, or after
    // Korapay comes back unable to verify it.
    const canSubmitBank = bankStatus === 'not_submitted' || bankStatus === 'failed';

    const bankForm = useForm({
        account_number: vendor?.account_number ?? '',
        bank_code: vendor?.bank_code ?? '',
        bank_name: vendor?.bank_name ?? '',
    });

    // Live "who owns this account" preview via Korapay, resolved as the vendor finishes typing —
    // same 500ms-debounced pattern as Vendor/Profile.jsx's bank-change form. The actual submit
    // below re-resolves this from scratch regardless; this is purely informational.
    const [resolveState, setResolveState] = useState('idle'); // idle | resolving | resolved | error
    const [resolvedName, setResolvedName] = useState(null);
    const [resolveError, setResolveError] = useState(null);

    useEffect(() => {
        setResolvedName(null);
        setResolveError(null);

        if (!canSubmitBank || bankForm.data.account_number.length !== 10 || !bankForm.data.bank_code) {
            setResolveState('idle');
            return;
        }

        setResolveState('resolving');
        const timeout = setTimeout(() => {
            window.axios
                .post(route('vendor.onboarding.bank.resolve'), {
                    account_number: bankForm.data.account_number,
                    bank_code: bankForm.data.bank_code,
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
    }, [bankForm.data.account_number, bankForm.data.bank_code, canSubmitBank]);

    const submitBank = (e) => {
        e.preventDefault();
        bankForm.post(route('vendor.onboarding.bank-details'), {
            onSuccess: () => {
                setResolveState('idle');
                setResolvedName(null);
            },
        });
    };

    const nameMismatch =
        vendor?.resolved_account_name &&
        vendor?.business_name &&
        !vendor.resolved_account_name.toLowerCase().includes(vendor.business_name.toLowerCase().split(' ')[0]);

    return (
        <VendorLayout vendor={vendor}>
            <Head title="Vendor Onboarding" />

            {showWelcome && <VendorWelcomeModal onClose={() => setShowWelcome(false)} />}

            <h1 className="mb-6 text-xl font-bold text-neutral-900">Vendor Onboarding</h1>

            {/* Status summary */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-lg border border-neutral-200 bg-white p-4">
                    <p className="text-xs text-neutral-500">{__('Documents Status')}</p>
                    <div className="mt-2"><StatusBadge status={documentsStatus} /></div>
                    {documentsStatus === 'rejected' && vendor?.documents_rejection_reason && (
                        <p className="mt-2 text-xs text-red-600">{vendor.documents_rejection_reason}</p>
                    )}
                </div>
                <div className="rounded-lg border border-neutral-200 bg-white p-4">
                    <p className="text-xs text-neutral-500">{__('Bank Verification')}</p>
                    <div className="mt-2"><StatusBadge status={bankStatus} /></div>
                    {bankStatus === 'failed' && (
                        <p className="mt-2 text-xs text-red-600">
                            {__('Korapay could not verify this account. Please check the details and try again.')}
                        </p>
                    )}
                </div>
                <div className="rounded-lg border border-neutral-200 bg-white p-4">
                    <p className="text-xs text-neutral-500">{__('Resolved Account Name')}</p>
                    <p className="mt-2 text-sm font-medium text-neutral-800">{vendor?.resolved_account_name ?? '—'}</p>
                </div>
            </div>

            {nameMismatch && (
                <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                    {__('The resolved account name above may not match your business name. Please confirm this is correct before continuing.')}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Business profile (read-only) + documents */}
                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Business Profile & Documents')}</h2>

                    {/* business_name/contact_email/contact_phone are fixed at whatever the vendor
                        registered with — see OnboardingController::updateProfile()'s doc comment
                        for why these are read-only here rather than editable. */}
                    <div className="mb-5 space-y-4">
                        <div>
                            <InputLabel htmlFor="business_name" value={__('Business Name')} />
                            <TextInput
                                id="business_name"
                                className="mt-1 block w-full bg-neutral-50 text-neutral-500"
                                value={vendor?.business_name ?? ''}
                                disabled
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="contact_email" value={__('Contact Email')} />
                            <TextInput
                                id="contact_email"
                                type="email"
                                className="mt-1 block w-full bg-neutral-50 text-neutral-500"
                                value={vendor?.contact_email ?? ''}
                                disabled
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="contact_phone" value={__('Contact Phone')} />
                            <TextInput
                                id="contact_phone"
                                className="mt-1 block w-full bg-neutral-50 text-neutral-500"
                                value={vendor?.contact_phone ?? ''}
                                disabled
                            />
                        </div>
                        <p className="text-xs text-neutral-400">
                            {__('These were set when you registered and cannot be changed here — contact support if any of them need to be corrected.')}
                        </p>
                    </div>

                    <div className="border-t border-neutral-100 pt-5">
                        {!canSubmitDocument && documentUrl && (
                            <div className="mb-2 flex items-center gap-2 text-xs text-neutral-500">
                                <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {vendor?.verification_document_type && (
                                    <span>{DOCUMENT_TYPES.find((t) => t.value === vendor.verification_document_type)?.label ?? __('Document')}</span>
                                )}
                                <a href={documentUrl} target="_blank" rel="noopener noreferrer" className="font-medium text-orange-600 hover:underline">
                                    {__('View submitted document')}
                                </a>
                            </div>
                        )}

                        {canSubmitDocument ? (
                            <form onSubmit={submitDocument} className="space-y-3">
                                <div>
                                    <InputLabel htmlFor="document_type" value={__('Document Type')} />
                                    <select
                                        id="document_type"
                                        value={documentForm.data.document_type}
                                        onChange={(e) => documentForm.setData('document_type', e.target.value)}
                                        className="mt-1 w-full rounded-md border-neutral-300 text-sm text-neutral-700 focus:border-orange-500 focus:ring-orange-500"
                                    >
                                        <option value="">{__('Select the document you wish to upload')}</option>
                                        {DOCUMENT_TYPES.map((type) => (
                                            <option key={type.value} value={type.value}>{__(type.label)}</option>
                                        ))}
                                    </select>
                                    <InputError message={documentForm.errors.document_type} className="mt-1" />
                                </div>

                                <label
                                    htmlFor="onboarding-document-input"
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
                                    <p className="mt-1 text-[11px] text-neutral-400">{__('PDF, JPG, or PNG — one file, up to 10MB')}</p>
                                    <input
                                        id="onboarding-document-input"
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
                                    className="w-full rounded-md bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                                >
                                    {documentForm.processing ? __('Submitting…') : __('Submit for Review')}
                                </button>
                            </form>
                        ) : (
                            <button
                                type="button"
                                disabled
                                className="w-full cursor-not-allowed rounded-md bg-neutral-200 py-2.5 text-sm font-semibold text-neutral-500"
                            >
                                {documentsStatus === 'approved' ? __('Documents Approved') : __('Submitted — Awaiting Review')}
                            </button>
                        )}
                    </div>
                </div>

                {/* Bank details */}
                <form onSubmit={submitBank} className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Bank Details')}</h2>

                    {(!banks || banks.length === 0) && (
                        <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800">
                            {__("We couldn't load the bank list right now. Please try again in a few minutes.")}
                        </div>
                    )}

                    <fieldset disabled={!canSubmitBank} className="space-y-4 disabled:opacity-60">
                        <div>
                            <InputLabel htmlFor="account_number" value={__('Account Number')} />
                            <TextInput
                                id="account_number"
                                className="mt-1 block w-full"
                                value={bankForm.data.account_number}
                                onChange={(e) => bankForm.setData('account_number', e.target.value.replace(/\D/g, ''))}
                                maxLength={10}
                            />
                            <InputError message={bankForm.errors.account_number} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="bank_code" value={__('Bank')} />
                            <select
                                id="bank_code"
                                value={bankForm.data.bank_code}
                                onChange={(e) => {
                                    const bank = banks.find((b) => b.code === e.target.value);
                                    bankForm.setData('bank_code', e.target.value);
                                    bankForm.setData('bank_name', bank?.name ?? '');
                                }}
                                className="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                            >
                                <option value="">{__('Select bank...')}</option>
                                {(banks ?? []).map((bank) => (
                                    <option key={bank.code} value={bank.code}>{bank.name}</option>
                                ))}
                            </select>
                            <InputError message={bankForm.errors.bank_code} className="mt-1" />
                            <InputError message={bankForm.errors.bank_name} className="mt-1" />
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
                    </fieldset>

                    {canSubmitBank ? (
                        <button
                            type="submit"
                            disabled={bankForm.processing || resolveState !== 'resolved'}
                            className="mt-4 w-full rounded-md bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            {bankForm.processing ? __('Verifying…') : __('Verify Bank Details')}
                        </button>
                    ) : (
                        <button
                            type="button"
                            disabled
                            className="mt-4 w-full cursor-not-allowed rounded-md bg-neutral-200 py-2.5 text-sm font-semibold text-neutral-500"
                        >
                            {bankStatus === 'verified' ? __('Bank Details Verified') : __('Verifying…')}
                        </button>
                    )}
                </form>
            </div>
        </VendorLayout>
    );
}
