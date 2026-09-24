export default function StarRating({ rating = 0, count = null, size = 'sm' }) {
    const rounded = Math.round(Number(rating) || 0);
    const sizeClass = size === 'lg' ? 'text-lg' : 'text-xs';

    if (!rating && !count) return null;

    return (
        <span className={`inline-flex items-center gap-1 text-amber-500 ${sizeClass}`}>
            <span aria-hidden="true">
                {'★'.repeat(rounded)}
                <span className="text-neutral-300">{'★'.repeat(5 - rounded)}</span>
            </span>
            {count != null && <span className="text-neutral-400">({count})</span>}
        </span>
    );
}
