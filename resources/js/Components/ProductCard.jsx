import { Link } from '@inertiajs/react';
import { formatNaira } from '@/utils/money';
import { t } from '@/utils/translatable';
import DiscountBadge from '@/Components/DiscountBadge';
import { useTranslation } from '@/hooks/useTranslation';

export default function ProductCard({ product }) {
    const { __ } = useTranslation();
    const image = product.images?.find((img) => img.is_primary) ?? product.images?.[0];
    const imageUrl = image?.enhanced_url ?? image?.original_url ?? null;
    const sold = Number(product.views_count) || 0;
    const hasDiscount = Number(product.discount_percentage) > 0;

    return (
        <Link
            href={route('products.show', product.slug)}
            className="group flex flex-col overflow-hidden rounded-[8px] bg-white shadow-card transition hover:shadow-lift"
        >
            <div className="relative aspect-square w-full overflow-hidden bg-[#fafafa]">
                {hasDiscount && (
                    <DiscountBadge percentage={product.discount_percentage} className="absolute top-2 left-2 z-10" />
                )}
                {imageUrl ? (
                    <img
                        src={imageUrl}
                        alt={t(product.title)}
                        className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                        loading="lazy"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-neutral-300">
                        <svg className="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 16.5l4.5-4.5a2 2 0 012.8 0l3.2 3.2m0 0l2-2a2 2 0 012.8 0L21 16.5M3 5.5h18v13H3v-13z" />
                        </svg>
                    </div>
                )}
            </div>
            <div className="flex flex-1 flex-col gap-1 px-2.5 pb-2.5 pt-2">
                <h3 className="line-clamp-2 min-h-[36px] text-[12px] leading-[18px] text-tb-text">
                    {t(product.title)}
                </h3>
                <span className="text-[11px] font-medium text-tb">{__('Shipping insurance')}</span>
                <div className="mt-auto flex items-baseline justify-between gap-1 pt-0.5">
                    <span className="flex items-baseline gap-1.5">
                        <span className="font-display text-[16px] font-bold leading-none text-tb">
                            {formatNaira(product.price)}
                        </span>
                        {hasDiscount && (
                            <span className="text-[11px] text-neutral-400 line-through">
                                {formatNaira(product.compare_price)}
                            </span>
                        )}
                    </span>
                    <span className="truncate text-[11px] text-tb-muted">
                        {sold > 0 ? __(':count people purchased', { count: sold }) : __('New')}
                    </span>
                </div>
            </div>
        </Link>
    );
}
