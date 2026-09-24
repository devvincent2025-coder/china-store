import { formatNaira } from '@/utils/money';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * The Google-style "results as you type" card — shared by the header search box and the Compare
 * Prices search box (see useSearchSuggestions.js for the data-fetching half). Each caller decides
 * what clicking a result actually does via `onSelect`, since that differs by page (go straight to
 * the product on the header search, but re-run the compare search on Compare Prices).
 */
export default function SearchSuggestionsDropdown({ show, suggestions, loading, onSelect, className = '' }) {
    const { __ } = useTranslation();

    if (!show) return null;

    return (
        <div
            className={`no-scrollbar absolute z-50 mt-1 max-h-96 overflow-y-auto rounded-lg bg-white text-left shadow-lift ${className}`}
        >
            {loading && suggestions.length === 0 && (
                <div className="space-y-3 p-3">
                    {[0, 1, 2].map((i) => (
                        <div key={i} className="flex animate-pulse items-center gap-3">
                            <div className="h-10 w-10 shrink-0 rounded bg-neutral-100" />
                            <div className="h-3 flex-1 rounded bg-neutral-100" />
                        </div>
                    ))}
                </div>
            )}

            {!loading && suggestions.length === 0 && (
                <p className="px-4 py-6 text-center text-sm text-neutral-400">{__('No matching products found')}</p>
            )}

            {suggestions.map((item) => (
                <button
                    key={item.id}
                    type="button"
                    // Fires before the input's onBlur, so the click actually registers instead of
                    // the dropdown closing (from losing focus) a moment before the click lands.
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => onSelect(item)}
                    className="flex w-full items-center gap-3 border-b border-neutral-50 px-3 py-2 text-left last:border-0 hover:bg-neutral-50"
                >
                    <div className="h-10 w-10 shrink-0 overflow-hidden rounded bg-neutral-100">
                        {item.image && <img src={item.image} alt="" className="h-full w-full object-cover" />}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm text-neutral-800">{item.title}</p>
                        {item.store_name && <p className="truncate text-xs text-neutral-400">{item.store_name}</p>}
                    </div>
                    <span className="shrink-0 text-sm font-semibold text-orange-600">{formatNaira(item.price)}</span>
                </button>
            ))}
        </div>
    );
}
