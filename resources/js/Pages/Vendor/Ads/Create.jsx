import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import AdsTermsModal from '@/Components/AdsTermsModal';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * "Boost your store" checkout — a vendor picks how many days to run and the date they're ready to
 * start, optionally uploads their own banner (defaults to their store's own banner/logo — see
 * AdCampaignService::create()), sees the total cost live, and pays via Korapay. The campaign only
 * starts showing to buyers once an admin approves it after payment clears
 * (Filament\Resources\AdCampaignResource) — see AdCampaignService::approve() for how the chosen
 * start date interacts with approval timing.
 */
export default function Create({ vendor, costPerDayKobo, minDays, maxDays, today }) {
    const { __ } = useTranslation();
    const [bannerPreview, setBannerPreview] = useState(null);
    const [termsAgreed, setTermsAgreed] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        duration_days: minDays,
        banner: null,
        starts_at: today,
        terms_accepted: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('vendor.ads.store'), { forceFormData: true });
    };

    const agreeToTerms = () => {
        setData('terms_accepted', true);
        setTermsAgreed(true);
    };

    const onBannerChange = (file) => {
        setData('banner', file);
        setBannerPreview(file ? URL.createObjectURL(file) : null);
    };

    const totalKobo = (Number(data.duration_days) || 0) * costPerDayKobo;

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Boost Your Store')} />

            <AdsTermsModal show={!termsAgreed} onAgree={agreeToTerms} />

            <h1 className="mb-1 text-xl font-bold text-neutral-900">{__('Boost Your Store')}</h1>
            <p className="mb-6 text-sm text-neutral-500">
                {__('Your store appears as a "Top Picks" card on the homepage and other pages buyers browse — for the number of days you choose below.')}
            </p>

            <form onSubmit={submit} className="max-w-2xl space-y-6">
                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-4 text-sm font-semibold text-neutral-800">{__('Duration')}</h2>
                    <InputLabel htmlFor="duration_days" value={__('Number of days (:min–:max)', { min: minDays, max: maxDays })} />
                    <TextInput
                        id="duration_days"
                        type="number"
                        min={minDays}
                        max={maxDays}
                        className="mt-1 block w-full max-w-xs"
                        value={data.duration_days}
                        onChange={(e) => setData('duration_days', e.target.value)}
                    />
                    <InputError message={errors.duration_days} className="mt-1" />

                    <div className="mt-4">
                        <InputLabel htmlFor="starts_at" value={__('Start date')} />
                        <TextInput
                            id="starts_at"
                            type="date"
                            min={today}
                            className="mt-1 block w-full max-w-xs"
                            value={data.starts_at}
                            onChange={(e) => setData('starts_at', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-neutral-400">
                            {__("Choose a day you're actually ready to run this — payment is final and can't be cancelled once submitted.")}
                        </p>
                        <InputError message={errors.starts_at} className="mt-1" />
                    </div>

                    <div className="mt-4 rounded-md bg-neutral-50 px-4 py-3 text-sm">
                        <div className="flex justify-between text-neutral-500">
                            <span>{__('Cost per day')}</span>
                            <span>{formatNaira(costPerDayKobo)}</span>
                        </div>
                        <div className="mt-1 flex justify-between text-base font-bold text-neutral-900">
                            <span>{__('Total')}</span>
                            <span>{formatNaira(totalKobo)}</span>
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border border-neutral-200 bg-white p-5">
                    <h2 className="mb-1 text-sm font-semibold text-neutral-800">{__('Banner (optional)')}</h2>
                    <p className="mb-4 text-xs text-neutral-500">
                        {__("Leave this blank to use your store's own banner or logo instead.")}
                    </p>
                    <input
                        type="file"
                        accept="image/*"
                        onChange={(e) => onBannerChange(e.target.files?.[0] ?? null)}
                        className="block w-full text-sm text-neutral-600"
                    />
                    <InputError message={errors.banner} className="mt-1" />
                    {bannerPreview && (
                        <img src={bannerPreview} alt="" className="mt-3 h-32 w-48 rounded-md border border-neutral-200 object-cover" />
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing || !termsAgreed}
                    className="w-full rounded-md bg-rose-600 py-3 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50 sm:w-auto sm:px-10"
                >
                    {processing ? __('Uploading…') : __('Pay & Submit for Approval')}
                </button>
            </form>
        </VendorLayout>
    );
}
