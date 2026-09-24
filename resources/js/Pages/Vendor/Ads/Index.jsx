import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import Modal from '@/Components/Modal';
import StatusBadge from '@/Components/StatusBadge';
import EmptyState from '@/Components/EmptyState';
import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

function PauseConfirmModal({ show, onClose, onConfirm, processing }) {
    const { __ } = useTranslation();

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h2 className="text-lg font-bold text-neutral-900">{__('Pause this campaign?')}</h2>
                <p className="mt-2 text-sm text-neutral-500">
                    {__("Once paused, you won't be able to resume it again for 24 hours. Only pause if you're sure you want to stop showing right now.")}
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-neutral-300 px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50"
                    >
                        {__('Cancel')}
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="rounded-md bg-neutral-800 px-4 py-2 text-sm font-semibold text-white hover:bg-neutral-900 disabled:opacity-50"
                    >
                        {__('Yes, Pause Campaign')}
                    </button>
                </div>
            </div>
        </Modal>
    );
}

function resumeAvailableLabel(isoString) {
    if (!isoString) return null;

    return new Date(isoString).toLocaleString('en-NG', {
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function CampaignCard({ campaign, onRequestPause }) {
    const { __ } = useTranslation();
    const [resuming, setResuming] = useState(false);

    const resume = () => {
        setResuming(true);
        router.post(route('vendor.ads.resume', campaign.id), {}, {
            preserveScroll: true,
            onFinish: () => setResuming(false),
        });
    };

    const resumeLocked = campaign.status === 'paused' && !campaign.can_resume;

    return (
        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
            <div className="flex flex-col gap-4 p-5 sm:flex-row">
                <div className="h-24 w-36 shrink-0 overflow-hidden rounded-md bg-neutral-100">
                    {campaign.banner_url && <img src={campaign.banner_url} alt="" className="h-full w-full object-cover" />}
                </div>
                <div className="flex-1">
                    <div className="mb-2 flex flex-wrap items-center gap-2">
                        <StatusBadge status={campaign.status} />
                        <span className="text-sm text-neutral-500">{campaign.duration_days} {__('day(s)')}</span>
                        <span className="text-sm text-neutral-500">· {formatNaira(campaign.amount_kobo)}</span>
                    </div>

                    {campaign.status === 'rejected' && campaign.rejection_reason && (
                        <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">{campaign.rejection_reason}</p>
                    )}

                    {campaign.starts_at && (
                        <p className="mb-2 text-xs text-neutral-400">
                            {new Date(campaign.starts_at).toLocaleDateString('en-NG')} — {campaign.ends_at ? new Date(campaign.ends_at).toLocaleDateString('en-NG') : '—'}
                        </p>
                    )}

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div>
                            <p className="text-xs text-neutral-400">{__('Impressions')}</p>
                            <p className="text-sm font-semibold text-neutral-800">{campaign.impressions_count}</p>
                        </div>
                        <div>
                            <p className="text-xs text-neutral-400">{__('Clicks')}</p>
                            <p className="text-sm font-semibold text-neutral-800">{campaign.clicks_count}</p>
                        </div>
                        <div>
                            <p className="text-xs text-neutral-400">{__('Click-through rate')}</p>
                            <p className="text-sm font-semibold text-neutral-800">{campaign.click_through_rate}%</p>
                        </div>
                        <div>
                            <p className="text-xs text-neutral-400">{__('Avg. time on page')}</p>
                            <p className="text-sm font-semibold text-neutral-800">{campaign.average_dwell_seconds}s</p>
                        </div>
                    </div>

                    {(campaign.status === 'active' || campaign.status === 'paused') && (
                        <div className="mt-4">
                            {campaign.status === 'active' ? (
                                <button
                                    onClick={() => onRequestPause(campaign)}
                                    className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50"
                                >
                                    {__('Pause Campaign')}
                                </button>
                            ) : (
                                <div>
                                    <button
                                        onClick={resume}
                                        disabled={resumeLocked || resuming}
                                        className="rounded-md bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:bg-neutral-300"
                                    >
                                        {__('Resume Campaign')}
                                    </button>
                                    {resumeLocked && (
                                        <p className="mt-1.5 text-xs text-neutral-400">
                                            {__('You can resume this on :date — 24 hours after pausing.', { date: resumeAvailableLabel(campaign.resume_available_at) })}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Index({ vendor, campaigns, hasActiveOrPending }) {
    const { __ } = useTranslation();
    const [pausingCampaign, setPausingCampaign] = useState(null);
    const [pauseProcessing, setPauseProcessing] = useState(false);

    const confirmPause = () => {
        if (!pausingCampaign) return;

        setPauseProcessing(true);
        router.post(route('vendor.ads.pause', pausingCampaign.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                setPauseProcessing(false);
                setPausingCampaign(null);
            },
        });
    };

    return (
        <VendorLayout vendor={vendor}>
            <Head title={__('Store Boosts')} />

            <PauseConfirmModal
                show={!!pausingCampaign}
                onClose={() => setPausingCampaign(null)}
                onConfirm={confirmPause}
                processing={pauseProcessing}
            />

            <div className="mb-6 flex items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-neutral-900">{__('Store Boosts (Ads)')}</h1>
                {!hasActiveOrPending && (
                    <Link href={route('vendor.ads.create')} className="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                        {__('Boost My Store')}
                    </Link>
                )}
            </div>

            {campaigns.length === 0 ? (
                <EmptyState
                    title={__('No campaigns yet')}
                    description={__('Boost your store to appear as a "Top Picks" card on the homepage and other pages buyers browse.')}
                />
            ) : (
                <div className="space-y-4">
                    {campaigns.map((campaign) => (
                        <CampaignCard key={campaign.id} campaign={campaign} onRequestPause={setPausingCampaign} />
                    ))}
                </div>
            )}
        </VendorLayout>
    );
}
