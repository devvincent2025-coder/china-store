/**
 * Flat marketplace illustration for the Open a shop CTA — seller with laptop,
 * floating shop UI, package, and sale accents (style inspired by modern e-commerce vectors).
 * Tuned for an orange (#FF5000) panel: light fills + soft blues/yellows for contrast.
 */
export default function OpenShopIllustration({ className = '' }) {
    return (
        <svg
            className={className}
            viewBox="0 0 420 280"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Seller launching an online shop"
        >
            {/* Soft blob backdrop */}
            <ellipse cx="250" cy="160" rx="155" ry="105" fill="#ffffff" fillOpacity="0.14" />
            <ellipse cx="290" cy="120" rx="70" ry="48" fill="#FFE08A" fillOpacity="0.35" />

            {/* Desk / ground */}
            <rect x="48" y="228" width="300" height="10" rx="5" fill="#ffffff" fillOpacity="0.25" />

            {/* Shipping box */}
            <g transform="translate(52 155)">
                <path d="M8 28 L48 8 L88 28 L88 68 L48 88 L8 68 Z" fill="#F5D0A9" />
                <path d="M8 28 L48 48 L88 28 L48 8 Z" fill="#E8B882" />
                <path d="M48 48 L48 88 L88 68 L88 28 Z" fill="#D4A06A" />
                <path d="M8 28 L8 68 L48 88 L48 48 Z" fill="#C4894F" />
                <rect x="40" y="20" width="16" height="52" fill="#FF5000" fillOpacity="0.85" />
                <rect x="20" y="42" width="56" height="10" fill="#FF5000" fillOpacity="0.85" />
            </g>

            {/* Laptop */}
            <g transform="translate(145 130)">
                <rect x="0" y="8" width="150" height="95" rx="8" fill="#1E3A5F" />
                <rect x="8" y="16" width="134" height="72" rx="4" fill="#E8F4FF" />
                {/* Dashboard UI */}
                <rect x="16" y="24" width="50" height="8" rx="2" fill="#FF5000" />
                <rect x="16" y="40" width="70" height="6" rx="2" fill="#94B8D9" />
                <rect x="16" y="52" width="55" height="6" rx="2" fill="#B8D4EA" />
                <rect x="16" y="64" width="40" height="14" rx="3" fill="#00A26A" />
                <circle cx="118" cy="48" r="22" fill="#FFE08A" />
                <path d="M110 48 L116 54 L128 40" stroke="#1E3A5F" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                {/* Base */}
                <path d="M-8 103 H158 L148 112 H2 Z" fill="#152A45" />
                <rect x="55" y="105" width="40" height="4" rx="2" fill="#94B8D9" fillOpacity="0.5" />
            </g>

            {/* Seller figure */}
            <g transform="translate(268 70)">
                {/* Hair */}
                <ellipse cx="42" cy="28" rx="28" ry="26" fill="#2C1810" />
                {/* Face */}
                <circle cx="42" cy="34" r="22" fill="#F0C4A0" />
                {/* Eyes */}
                <circle cx="34" cy="34" r="2.2" fill="#2C1810" />
                <circle cx="50" cy="34" r="2.2" fill="#2C1810" />
                {/* Smile */}
                <path d="M34 42 Q42 48 50 42" stroke="#C4894F" strokeWidth="2" strokeLinecap="round" fill="none" />
                {/* Body */}
                <path d="M18 62 Q42 54 66 62 L72 120 L12 120 Z" fill="#ffffff" />
                <path d="M28 62 L28 95 L56 95 L56 62" fill="#1E90FF" />
                {/* Arm pointing to laptop */}
                <path d="M18 72 Q0 88 -10 100" stroke="#F0C4A0" strokeWidth="10" strokeLinecap="round" fill="none" />
                <circle cx="-12" cy="102" r="7" fill="#F0C4A0" />
            </g>

            {/* Floating phone */}
            <g transform="translate(330 40)">
                <rect x="0" y="0" width="48" height="78" rx="8" fill="#1E3A5F" />
                <rect x="4" y="8" width="40" height="58" rx="3" fill="#ffffff" />
                <rect x="10" y="14" width="28" height="18" rx="2" fill="#FF5000" />
                <rect x="10" y="38" width="20" height="4" rx="1" fill="#B8D4EA" />
                <rect x="10" y="46" width="16" height="4" rx="1" fill="#B8D4EA" />
                <circle cx="24" cy="70" r="3" fill="#94B8D9" />
            </g>

            {/* SALE bubble */}
            <g transform="translate(95 35)">
                <circle cx="36" cy="36" r="34" fill="#FFE08A" />
                <circle cx="36" cy="36" r="28" fill="#FFD24D" />
                <text
                    x="36"
                    y="32"
                    textAnchor="middle"
                    fill="#1E3A5F"
                    fontFamily="Urbanist, Manrope, sans-serif"
                    fontSize="11"
                    fontWeight="700"
                >
                    SALE
                </text>
                <text
                    x="36"
                    y="48"
                    textAnchor="middle"
                    fill="#FF5000"
                    fontFamily="Urbanist, Manrope, sans-serif"
                    fontSize="14"
                    fontWeight="800"
                >
                    ₦
                </text>
            </g>

            {/* Floating tag */}
            <g transform="translate(355 145)">
                <path d="M0 12 L12 0 H40 A8 8 0 0 1 48 8 V28 A8 8 0 0 1 40 36 H12 Z" fill="#ffffff" />
                <circle cx="12" cy="18" r="3.5" fill="#FF5000" />
                <rect x="20" y="14" width="18" height="4" rx="1" fill="#94B8D9" />
                <rect x="20" y="22" width="12" height="3" rx="1" fill="#B8D4EA" />
            </g>

            {/* Star rating chip */}
            <g transform="translate(300 210)">
                <rect x="0" y="0" width="72" height="28" rx="14" fill="#ffffff" fillOpacity="0.95" />
                <path d="M14 20 L16.5 12.5 L19 20 L12 15.5 H20 Z" fill="#FFD24D" />
                <path d="M28 20 L30.5 12.5 L33 20 L26 15.5 H34 Z" fill="#FFD24D" />
                <path d="M42 20 L44.5 12.5 L47 20 L40 15.5 H48 Z" fill="#FFD24D" />
                <text
                    x="58"
                    y="18"
                    textAnchor="middle"
                    fill="#1E3A5F"
                    fontFamily="Urbanist, Manrope, sans-serif"
                    fontSize="11"
                    fontWeight="700"
                >
                    5.0
                </text>
            </g>

            {/* Megaphone accent */}
            <g transform="translate(200 28)">
                <path d="M8 22 L28 10 L28 42 Z" fill="#ffffff" />
                <rect x="0" y="16" width="10" height="12" rx="2" fill="#1E90FF" />
                <path d="M28 16 Q42 10 48 8" stroke="#FFE08A" strokeWidth="3" strokeLinecap="round" fill="none" />
                <path d="M28 28 Q44 28 52 30" stroke="#FFE08A" strokeWidth="3" strokeLinecap="round" fill="none" />
                <path d="M28 36 Q40 40 46 44" stroke="#FFE08A" strokeWidth="3" strokeLinecap="round" fill="none" />
            </g>
        </svg>
    );
}
