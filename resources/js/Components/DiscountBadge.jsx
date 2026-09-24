export default function DiscountBadge({ percentage, className = '' }) {
    const pct = Number(percentage) || 0;
    if (pct <= 0) return null;

    return (
        <span
            className={`inline-flex items-center rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-bold leading-none text-white shadow-sm ${className}`}
        >
            -{pct}%
        </span>
    );
}
