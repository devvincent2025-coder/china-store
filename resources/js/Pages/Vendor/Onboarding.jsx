import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import StatusBadge from '@/Components/StatusBadge';
import VendorWelcomeModal from '@/Components/VendorWelcomeModal';
import { useTranslation } from '@/hooks/useTranslation';

export default function Onboarding({ vendor, banks }) {
    const { __ } = useTranslation();
    const { flash } = usePage().props;
    const [showWelcome, setShowWelcome] = useState(!!flash?.justRegisteredVendor);
    const profileForm = useForm({
        business_name: vendor?.business_name ?? '',
        contact_email: vendor?.contact_email ?? '',
        contact_phone: vendor?.contact_phone ?? '',
        documents: [],
    });

    const bankForm = useForm({
        account_number: vendor?.account_number ?? '',
        bank_code: vendor?.bank_code ?? '',
        bank_name: vendor?.bank_name ?? '',
    });

    const submitProfile = (e) => {
        e.preventDefault();
        profileForm.post(route('vendor.onboarding.profile'), { forceFormData: true });
    };

    const submitBank = (e) => {
        e.preventDefault();
        bankForm.post(route('vendor.onboarding.bank-details'));
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
                    <div className="mt-2"><StatusBadge status={vendor?.documents_status ?? 'not_submitted'} /></div>
                    {vendor?.documents_rejection_reason && (
                        <p className="mt-2 text-xs text-red-600">{vendor.documents_rejection_reason}</p>
                    )}
                </div>
                <div className="rounded-lg border border-neutral-200 bg-white p-4">
                    <p className="text-xs text-neutral-500">{__('Bank Verification')}</p>
                    <div className="mt-2"><StatusBadge status={vendor?.bank_verification_status ?? 'not_submitted'} /></div>
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
                {/* Business profile + documents */}
                <form onSubmit={submitProfile} className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Business Profile & Documents')}</h2>

                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="business_name" value={__('Business Name')} />
                            <TextInput
                                id="business_name"
                                className="mt-1 block w-full"
                                value={profileForm.data.business_name}
                                onChange={(e) => profileForm.setData('business_name', e.target.value)}
                            />
                            <InputError message={profileForm.errors.business_name} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="contact_email" value={__('Contact Email')} />
                            <TextInput
                                id="contact_email"
                                type="email"
                                className="mt-1 block w-full"
                                value={profileForm.data.contact_email}
                                onChange={(e) => profileForm.setData('contact_email', e.target.value)}
                            />
                            <InputError message={profileForm.errors.contact_email} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="contact_phone" value={__('Contact Phone')} />
                            <TextInput
                                id="contact_phone"
                                className="mt-1 block w-full"
                                value={profileForm.data.contact_phone}
                                onChange={(e) => profileForm.setData('contact_phone', e.target.value)}
                            />
                            <InputError message={profileForm.errors.contact_phone} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="documents" value={__('Verification Documents (PDF/JPG/PNG)')} />
                            <input
                                id="documents"
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => profileForm.setData('documents', Array.from(e.target.files))}
                                className="mt-1 block w-full text-sm text-neutral-600"
                            />
                            <InputError message={profileForm.errors.documents} className="mt-1" />
                        </div>
                        <button
                            type="submit"
                            disabled={profileForm.processing}
                            className="w-full rounded-md bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            {__('Submit for Review')}
                        </button>
                    </div>
                </form>

                {/* Bank details */}
                <form onSubmit={submitBank} className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Bank Details')}</h2>

                    {(!banks || banks.length === 0) && (
                        <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800">
                            {__("We couldn't load the bank list right now. Please try again in a few minutes.")}
                        </div>
                    )}

                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="account_number" value={__('Account Number')} />
                            <TextInput
                                id="account_number"
                                className="mt-1 block w-full"
                                value={bankForm.data.account_number}
                                onChange={(e) => bankForm.setData('account_number', e.target.value)}
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
                        <button
                            type="submit"
                            disabled={bankForm.processing}
                            className="w-full rounded-md bg-orange-600 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            {__('Verify Bank Details')}
                        </button>
                    </div>
                </form>
            </div>
        </VendorLayout>
    );
}
