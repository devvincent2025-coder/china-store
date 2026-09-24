import { useTranslation } from '@/hooks/useTranslation';

/**
 * One-time celebratory modal shown right after a buyer completes signup.
 * Driven entirely by `flash.justRegistered` (see Pages/Home.jsx) — no navigation happens on
 * close, it's just dismissed locally since the user is already on the homepage.
 */
export default function WelcomeModal({ onClose }) {
    const { __ } = useTranslation();

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="welcome-modal-title"
        >
            <div className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white p-6 text-center shadow-2xl sm:p-8">
                <div className="cin-checkmark-wrap mx-auto flex h-20 w-20 items-center justify-center">
                    <svg
                        className="h-20 w-20"
                        viewBox="0 0 60 60"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <circle
                            className="cin-checkmark-circle"
                            cx="30"
                            cy="30"
                            r="26"
                            stroke="#16a34a"
                            strokeWidth="4"
                            strokeLinecap="round"
                        />
                        <path
                            className="cin-checkmark-tick"
                            d="M18 31l8 8 16-18"
                            stroke="#16a34a"
                            strokeWidth="4"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </div>

                <h2
                    id="welcome-modal-title"
                    className="font-display mt-4 text-2xl font-bold text-gray-900"
                >
                    {__('Welcome to ChinaInNigeria!')}
                </h2>

                <p className="mt-3 text-sm leading-relaxed text-gray-600">
                    {__('Your account is ready. Discover thousands of products from trusted Chinese-run stores across Nigeria, buy directly from verified vendors, and enjoy fast local delivery straight to your door.')}
                </p>

                <button
                    type="button"
                    onClick={onClose}
                    className="cin-cta-blink mt-6 w-full rounded-lg px-6 py-3.5 text-base font-bold text-white transition-transform focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 active:scale-[0.98]"
                >
                    {__('Start Shopping')}
                </button>
            </div>
        </div>
    );
}
