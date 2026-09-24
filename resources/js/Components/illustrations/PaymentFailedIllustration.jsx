/**
 * Flat-style "payment failed" illustration for PaymentResultModal — the same receipt-card
 * composition as PaymentSuccessIllustration, but with a red alert badge instead of a checkmark so
 * the two states read as a clear before/after pair.
 */
export default function PaymentFailedIllustration({ className = '' }) {
    return (
        <svg
            viewBox="0 0 320 280"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={className}
            role="img"
            aria-labelledby="payment-failed-illustration-title"
        >
            <title id="payment-failed-illustration-title">A receipt with a red alert badge, indicating the payment could not be completed</title>

            <ellipse cx="160" cy="220" rx="120" ry="16" fill="#FEE2E2" />

            {/* Receipt card */}
            <g transform="translate(80, 40)">
                <path d="M0 10a10 10 0 0110-10h140a10 10 0 0110 10v170l-15-10-15 10-15-10-15 10-15-10-15 10-15-10-15 10-15-10-15 10V10z" fill="#FFFFFF" stroke="#FCA5A5" strokeWidth="3" />
                <rect x="20" y="26" width="120" height="10" rx="4" fill="#FEE2E2" />
                <rect x="20" y="48" width="90" height="8" rx="4" fill="#F3F4F6" />
                <rect x="20" y="66" width="100" height="8" rx="4" fill="#F3F4F6" />
                <rect x="20" y="84" width="70" height="8" rx="4" fill="#F3F4F6" />
                <rect x="20" y="108" width="120" height="1.5" fill="#E5E7EB" />
                <rect x="20" y="122" width="50" height="10" rx="4" fill="#111827" opacity="0.7" />
                <rect x="100" y="122" width="40" height="10" rx="4" fill="#DC2626" />
            </g>

            {/* Alert badge */}
            <g transform="translate(190, 150)">
                <circle cx="40" cy="40" r="40" fill="#DC2626" />
                <circle cx="40" cy="40" r="40" fill="none" stroke="#FECACA" strokeWidth="6" opacity="0.6" />
                <line x1="40" y1="20" x2="40" y2="46" stroke="white" strokeWidth="6" strokeLinecap="round" />
                <circle cx="40" cy="58" r="4" fill="white" />
            </g>
        </svg>
    );
}
