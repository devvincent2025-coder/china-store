/**
 * Mirrors ComparePrices/Index.jsx's ProductGroup block (thumbnail + title row, then a seller
 * comparison table) so results don't visibly reflow once the real comparison loads in.
 */
export default function CompareGroupSkeleton({ groups = 3, rows = 3 }) {
    return (
        <div className="animate-pulse space-y-6">
            {Array.from({ length: groups }).map((_, g) => (
                <div key={g} className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    <div className="flex items-center gap-4 border-b border-neutral-100 px-4 py-3">
                        <div className="h-14 w-14 shrink-0 rounded-md bg-neutral-200" />
                        <div className="min-w-0 flex-1 space-y-2">
                            <div className="h-4 w-1/2 rounded bg-neutral-200" />
                            <div className="h-3 w-1/3 rounded bg-neutral-200" />
                        </div>
                    </div>
                    <div className="divide-y divide-neutral-100">
                        {Array.from({ length: rows }).map((_, r) => (
                            <div key={r} className="flex items-center gap-6 px-4 py-3">
                                <div className="h-3 w-24 rounded bg-neutral-200" />
                                <div className="h-3 w-16 rounded bg-neutral-200" />
                                <div className="h-3 w-16 rounded bg-neutral-200" />
                                <div className="ml-auto h-3 w-16 rounded bg-neutral-200" />
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
