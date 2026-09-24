import { formatNaira } from '@/utils/money';
import DiscountBadge from '@/Components/DiscountBadge';

export default function PriceTag({
    kobo,
    comparePriceKobo = null,
    discountPercentage = null,
    className = '',
    size = 'base',
}) {
    const sizes = {
        sm: 'text-sm',
        base: 'text-base',
        lg: 'text-xl',
        xl: 'text-2xl',
    };

    const hasDiscount = Boolean(comparePriceKobo) && comparePriceKobo > kobo;

    // If the caller didn't pass an explicit percentage, derive it from the two kobo values
    // so callers that only have kobo/comparePriceKobo (the common case) still get a badge.
    const percentage = discountPercentage != null
        ? Number(discountPercentage)
        : hasDiscount
            ? Math.round(((comparePriceKobo - kobo) / comparePriceKobo) * 100)
            : 0;

    return (
        <span className={`inline-flex items-baseline gap-2 ${className}`}>
            <span className={`font-bold text-orange-600 ${sizes[size] || sizes.base}`}>
                {formatNaira(kobo)}
            </span>
            {hasDiscount && (
                <span className="text-xs text-neutral-400 line-through">
                    {formatNaira(comparePriceKobo)}
                </span>
            )}
            {percentage > 0 && <DiscountBadge percentage={percentage} />}
        </span>
    );
}
