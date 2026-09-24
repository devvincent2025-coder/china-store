import { router } from '@inertiajs/react';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * One-time celebratory modal shown the first time a vendor loads their dashboard after an admin
 * approves their identity verification (KYC) — driven by Vendor::shouldShowKycCelebration() (a
 * durable DB flag, not a session flash, since the approval happens in an admin's own session).
 * Dismissing it POSTs vendor.identity.celebration-seen so it never shows again — see
 * Http\Controllers\Vendor\IdentityController::markCelebrationSeen().
 */
export default function VendorKycApprovedModal({ onClose }) {
    const { __ } = useTranslation();

    const dismiss = () => {
        router.post(route('vendor.identity.celebration-seen'), {}, { preserveScroll: true, onFinish: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md overflow-hidden rounded-2xl bg-white text-center shadow-2xl">
                <div className="bg-gradient-to-br from-green-500 to-emerald-600 px-6 py-10 sm:px-8">
                    <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white/20 ring-4 ring-white/30">
                        <svg className="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                        </svg>
                    </div>
                    <h2 className="mt-5 text-2xl font-bold text-white">{__("You're Verified!")}</h2>
                    <p className="mt-2 text-sm font-medium text-green-50">
                        {__('You now carry the Verified Seller badge on ChinaInNigeria.')}
                    </p>
                </div>

                <div className="p-6 sm:p-8">
                    <p className="text-sm leading-relaxed text-neutral-600">
                        {__("Your identity verification is approved.")}
                    </p>

                    <ul className="mt-4 space-y-2 text-left text-sm text-neutral-700">
                        <li className="flex items-center gap-2">
                            <svg className="h-4 w-4 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {__('Your products are now visible to buyers')}
                        </li>
                        <li className="flex items-center gap-2">
                            <svg className="h-4 w-4 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {__('Escrow payouts can now reach your bank account')}
                        </li>
                        <li className="flex items-center gap-2">
                            <svg className="h-4 w-4 shrink-0 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {__('Buyers see you as a verified seller')}
                        </li>
                    </ul>

                    <button
                        type="button"
                        onClick={dismiss}
                        className="mt-6 w-full rounded-lg bg-orange-600 px-6 py-3 text-sm font-bold text-white hover:bg-orange-700"
                    >
                        {__('Continue to Dashboard')}
                    </button>
                </div>
            </div>
        </div>
    );
}
