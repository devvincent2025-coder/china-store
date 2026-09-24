const ICONS = {
    bag: 'M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4M3 6h18M16 10a4 4 0 01-8 0',
    box: 'M21 8L12 3 3 8m18 0l-9 5m9-5v10l-9 5m0-10L3 8m9 5v10M3 8v10l9 5',
    tag: 'M12 2H2v10l9.29 9.29a1 1 0 001.41 0l8.59-8.59a1 1 0 000-1.41L12 2zM7 7h.01',
    cart: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
    card: 'M2 6a2 2 0 012-2h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm0 4h20',
    gift: 'M20 12v9a1 1 0 01-1 1H5a1 1 0 01-1-1v-9M2 7h20v5H2V7zm10 0v14M12 7c-1.5 0-4-1-4-3.25S9.5 1 12 3.5C14.5 1 16 3.5 16 3.75S14.5 7 12 7z',
    star: 'M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z',
    receipt: 'M4 2v20l2.5-1.5L9 22l2.5-1.5L14 22l2.5-1.5L19 22V2l-2.5 1.5L14 2l-2.5 1.5L9 2 6.5 3.5 4 2zM8 9h8M8 13h8M8 17h5',
};

const SCATTER = [
    { icon: 'bag', top: '8%', left: '10%', size: 44, rotate: -12, opacity: 0.16 },
    { icon: 'box', top: '18%', left: '85%', size: 56, rotate: 10, opacity: 0.14 },
    { icon: 'tag', top: '72%', left: '6%', size: 40, rotate: 18, opacity: 0.16 },
    { icon: 'cart', top: '82%', left: '88%', size: 50, rotate: -8, opacity: 0.15 },
    { icon: 'card', top: '38%', left: '4%', size: 46, rotate: -6, opacity: 0.13 },
    { icon: 'gift', top: '55%', left: '92%', size: 42, rotate: 14, opacity: 0.15 },
    { icon: 'star', top: '10%', left: '48%', size: 26, rotate: 0, opacity: 0.18 },
    { icon: 'receipt', top: '90%', left: '45%', size: 38, rotate: -10, opacity: 0.13 },
    { icon: 'star', top: '30%', left: '95%', size: 20, rotate: 0, opacity: 0.2 },
    { icon: 'star', top: '65%', left: '2%', size: 18, rotate: 0, opacity: 0.2 },
];

/**
 * Purely decorative ecommerce-themed icons scattered across PaymentResultModal's full-viewport
 * gradient background so it reads as a themed moment rather than empty color — low-opacity white
 * outline icons, absolutely positioned, never intercepting clicks (pointer-events-none).
 */
export default function EcommerceScatterIcons() {
    return (
        <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            {SCATTER.map((item, i) => (
                <svg
                    key={i}
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="white"
                    strokeWidth={1.5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    style={{
                        position: 'absolute',
                        top: item.top,
                        left: item.left,
                        width: item.size,
                        height: item.size,
                        opacity: item.opacity,
                        transform: `rotate(${item.rotate}deg)`,
                    }}
                >
                    <path d={ICONS[item.icon]} />
                </svg>
            ))}
        </div>
    );
}
