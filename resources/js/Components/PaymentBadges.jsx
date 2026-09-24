import { useTranslation } from '@/hooks/useTranslation';

/**
 * Generic card-scheme badges shown under the checkout payment button so buyers recognize their
 * card will be accepted before they get redirected to Korapay. Drawn as plain inline SVG (not
 * fetched brand logo images) since Korapay itself accepts all three schemes regardless of which
 * logos are shown — this is just a trust signal, not a real logo license.
 */
function VisaBadge() {
    return (
        <svg viewBox="0 0 48 30" className="h-6 w-10" aria-hidden="true">
            <rect width="48" height="30" rx="4" fill="#1A1F71" />
            <text x="24" y="20" textAnchor="middle" fontFamily="Arial, sans-serif" fontSize="12" fontStyle="italic" fontWeight="bold" fill="#fff">
                VISA
            </text>
        </svg>
    );
}

function MastercardBadge() {
    return (
        <svg viewBox="0 0 48 30" className="h-6 w-10" aria-hidden="true">
            <rect width="48" height="30" rx="4" fill="#fff" stroke="#e5e5e5" />
            <circle cx="20" cy="15" r="9" fill="#EB001B" />
            <circle cx="28" cy="15" r="9" fill="#F79E1B" fillOpacity="0.9" />
        </svg>
    );
}

function VerveBadge() {
    return (
        <svg viewBox="0 0 48 30" className="h-6 w-10" aria-hidden="true">
            <rect width="48" height="30" rx="4" fill="#00594C" />
            <text x="24" y="19" textAnchor="middle" fontFamily="Arial, sans-serif" fontSize="11" fontWeight="bold" fill="#fff">
                Verve
            </text>
        </svg>
    );
}

export default function PaymentBadges() {
    const { __ } = useTranslation();

    return (
        <div className="mt-4 rounded-lg border border-neutral-200 bg-white p-4 text-center">
            <p className="mb-2 text-xs text-neutral-400">{__('We accept')}</p>
            <div className="flex items-center justify-center gap-2">
                <VisaBadge />
                <MastercardBadge />
                <VerveBadge />
            </div>
        </div>
    );
}
