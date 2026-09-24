/**
 * Flat-style "payment successful" illustration for PaymentResultModal — a receipt card with a
 * large animated checkmark badge and a few confetti dots, hand-built inline SVG in the same
 * no-external-asset style as TrackOrderIllustration.
 */
export default function PaymentSuccessIllustration({ className = '' }) {
    return (
        <svg
            viewBox="0 0 320 280"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={className}
            role="img"
            aria-labelledby="payment-success-illustration-title"
        >
            <title id="payment-success-illustration-title">A receipt with a large green checkmark, confirming payment was successful</title>

            <ellipse cx="160" cy="220" rx="120" ry="16" fill="#DCFCE7" />

            {/* Confetti */}
            <rect x="30" y="40" width="10" height="10" rx="2" fill="#FF5000" transform="rotate(20 35 45)" />
            <rect x="270" y="70" width="8" height="8" rx="2" fill="#16A34A" transform="rotate(-15 274 74)" />
            <circle cx="255" cy="35" r="6" fill="#FDBA8C" />
            <circle cx="50" cy="110" r="5" fill="#16A34A" />
            <rect x="45" y="160" width="8" height="8" rx="2" fill="#FF5000" transform="rotate(30 49 164)" />

            {/* Receipt card */}
            <g transform="translate(80, 40)">
                <path d="M0 10a10 10 0 0110-10h140a10 10 0 0110 10v170l-15-10-15 10-15-10-15 10-15-10-15 10-15-10-15 10-15-10-15 10V10z" fill="#FFFFFF" stroke="#FDBA8C" strokeWidth="3" />
                <rect x="20" y="26" width="120" height="10" rx="4" fill="#FFEDD5" />
                <rect x="20" y="48" width="90" height="8" rx="4" fill="#F3F4F6" />
                <rect x="20" y="66" width="100" height="8" rx="4" fill="#F3F4F6" />
                <rect x="20" y="84" width="70" height="8" rx="4" fill="#F3F4F6" />
                <rect x="20" y="108" width="120" height="1.5" fill="#E5E7EB" />
                <rect x="20" y="122" width="50" height="10" rx="4" fill="#111827" opacity="0.7" />
                <rect x="100" y="122" width="40" height="10" rx="4" fill="#16A34A" />
            </g>

            {/* Checkmark badge */}
            <g transform="translate(190, 150)">
                <circle cx="40" cy="40" r="40" fill="#16A34A" />
                <circle cx="40" cy="40" r="40" fill="none" stroke="#BBF7D0" strokeWidth="6" opacity="0.6" />
                <path d="M22 41l12 12 24-26" stroke="white" strokeWidth="6" strokeLinecap="round" strokeLinejoin="round" fill="none" />
            </g>
        </svg>
    );
}
