/**
 * Flat-style delivery/tracking illustration for the Track Order page — hand-built inline SVG
 * (no external asset dependency), using the brand orange palette plus neutral/soft accents.
 */
export default function TrackOrderIllustration({ className = '' }) {
    return (
        <svg
            viewBox="0 0 560 480"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={className}
            role="img"
            aria-labelledby="track-order-illustration-title"
        >
            <title id="track-order-illustration-title">A delivery truck following a dotted route to a pinned location</title>

            {/* Soft backdrop blob */}
            <ellipse cx="280" cy="250" rx="250" ry="220" fill="#FFF1E6" />
            <ellipse cx="280" cy="420" rx="180" ry="18" fill="#FDE3D0" />

            {/* Dotted route from truck to pin */}
            <path
                d="M120 300 C 200 220, 260 340, 340 260 S 420 160, 430 120"
                stroke="#FDBA8C"
                strokeWidth="4"
                strokeLinecap="round"
                strokeDasharray="2 16"
            />

            {/* Destination pin */}
            <g transform="translate(400, 60)">
                <path
                    d="M30 0C13.4 0 0 13.4 0 30c0 22.5 30 50 30 50s30-27.5 30-50C60 13.4 46.6 0 30 0z"
                    fill="#EA580C"
                />
                <circle cx="30" cy="30" r="12" fill="#FFF7ED" />
                <ellipse cx="30" cy="86" rx="16" ry="4" fill="#FDBA8C" opacity="0.6" />
            </g>

            {/* Small floating package (top left) */}
            <g transform="translate(70, 90) rotate(-8)">
                <rect x="0" y="14" width="52" height="42" rx="6" fill="#FDBA8C" />
                <rect x="0" y="14" width="52" height="16" rx="6" fill="#EA580C" />
                <rect x="22" y="0" width="8" height="30" fill="#C2410C" />
            </g>

            {/* Delivery truck */}
            <g transform="translate(90, 250)">
                {/* Cargo box */}
                <rect x="0" y="20" width="200" height="110" rx="14" fill="#FFFFFF" stroke="#EA580C" strokeWidth="4" />
                <rect x="20" y="45" width="160" height="60" rx="8" fill="#FFEDD5" />
                {/* Box icon on cargo */}
                <g transform="translate(78, 58)">
                    <rect x="0" y="10" width="44" height="34" rx="4" fill="#EA580C" />
                    <rect x="0" y="10" width="44" height="12" rx="4" fill="#C2410C" />
                    <rect x="18" y="0" width="8" height="22" fill="#9A3412" />
                </g>

                {/* Cab */}
                <path d="M200 55h48c8 0 15 5 18 12l14 30c2 5 3 10 3 16v17h-83V55z" fill="#EA580C" />
                <rect x="216" y="70" width="34" height="26" rx="4" fill="#FFEDD5" />

                {/* Headlight + bumper */}
                <rect x="263" y="118" width="18" height="12" rx="3" fill="#FDBA8C" />

                {/* Wheels */}
                <circle cx="55" cy="140" r="22" fill="#1F2937" />
                <circle cx="55" cy="140" r="9" fill="#E5E7EB" />
                <circle cx="235" cy="140" r="22" fill="#1F2937" />
                <circle cx="235" cy="140" r="9" fill="#E5E7EB" />

                {/* Speed lines */}
                <line x1="-30" y1="60" x2="-4" y2="60" stroke="#FDBA8C" strokeWidth="5" strokeLinecap="round" />
                <line x1="-42" y1="80" x2="-10" y2="80" stroke="#FDBA8C" strokeWidth="5" strokeLinecap="round" />
                <line x1="-30" y1="100" x2="-4" y2="100" stroke="#FDBA8C" strokeWidth="5" strokeLinecap="round" />
            </g>

            {/* Checkmark badge (delivered confidence) */}
            <g transform="translate(360, 300)">
                <circle cx="30" cy="30" r="30" fill="#16A34A" />
                <path d="M17 30l9 9 17-19" stroke="white" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round" fill="none" />
            </g>
        </svg>
    );
}
