/**
 * Mirrors ProductCard.jsx's exact structure/dimensions (aspect-square image, a min-h-[36px]
 * two-line title block, a thin "shipping insurance" line, then a price/sold-count row) so the
 * grid doesn't visibly reflow the instant real cards swap in.
 */
export default function ProductGridSkeleton({ count = 12 }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="animate-pulse overflow-hidden rounded-[8px] bg-white shadow-card">
                    <div className="aspect-square w-full bg-neutral-200" />
                    <div className="flex flex-col gap-1 px-2.5 pb-2.5 pt-2">
                        <div className="min-h-[36px] space-y-1.5">
                            <div className="h-3 w-full rounded bg-neutral-200" />
                            <div className="h-3 w-2/3 rounded bg-neutral-200" />
                        </div>
                        <div className="h-2.5 w-1/3 rounded bg-neutral-200" />
                        <div className="mt-auto flex items-baseline justify-between gap-1 pt-0.5">
                            <div className="h-4 w-14 rounded bg-neutral-200" />
                            <div className="h-2.5 w-10 rounded bg-neutral-200" />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
