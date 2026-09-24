import { useTranslation } from '@/hooks/useTranslation';

/**
 * One-time modal shown right after a vendor completes signup, landing on the onboarding page —
 * driven by flash.justRegisteredVendor (see Auth\VendorRegisteredUserController and
 * Pages/Vendor/Onboarding.jsx). Its whole point is to mention identity verification (KYC) up
 * front without making it a blocker: onboarding here only needs a business profile, documents, and
 * bank details — KYC (email/phone/ID) is a separate, later step only required before payouts.
 */
export default function VendorWelcomeModal({ onClose }) {
    const { __ } = useTranslation();

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl sm:p-8">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-orange-100">
                    <svg className="h-8 w-8 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 21v-4m0 0V5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H7l-4 4zm9-13.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                </div>

                <h2 className="mt-4 text-xl font-bold text-neutral-900">{__('Welcome to ChinaInNigeria!')}</h2>
                <p className="mt-3 text-sm leading-relaxed text-neutral-600">
                    {__("Let's get your store set up — complete your business profile, upload your documents, and add your payout bank details below.")}
                </p>

                <div className="mt-4 rounded-lg bg-amber-50 p-3 text-left text-xs text-amber-800">
                    <p className="font-semibold">{__('One more thing (not required today):')}</p>
                    <p className="mt-1">
                        {__('Before we can release payments to your bank account, you\'ll need to complete identity verification (KYC) — verifying your email, phone number, and a government ID. You can do this any time from your dashboard.')}
                    </p>
                </div>

                <button
                    type="button"
                    onClick={onClose}
                    className="mt-6 w-full rounded-lg bg-orange-600 px-6 py-3 text-sm font-bold text-white hover:bg-orange-700"
                >
                    {__('Get Started')}
                </button>
            </div>
        </div>
    );
}
