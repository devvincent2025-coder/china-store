import { useEffect, useRef, useState } from 'react';

/**
 * Debounced "as you type" product suggestions, backed by GET /search/suggestions (see
 * App\Http\Controllers\SearchSuggestionController). Cancels any still-in-flight request when the
 * query changes again before it resolves, so a slow earlier response can never overwrite a newer
 * one (classic race condition with live search).
 */
export default function useSearchSuggestions(query, { minLength = 2, debounceMs = 250 } = {}) {
    const [suggestions, setSuggestions] = useState([]);
    const [loading, setLoading] = useState(false);
    const abortRef = useRef(null);
    const timerRef = useRef(null);

    useEffect(() => {
        clearTimeout(timerRef.current);
        abortRef.current?.abort();

        const trimmed = query.trim();

        if (trimmed.length < minLength) {
            setSuggestions([]);
            setLoading(false);
            return;
        }

        timerRef.current = setTimeout(() => {
            const controller = new AbortController();
            abortRef.current = controller;
            setLoading(true);

            window.axios
                .get(route('search.suggestions'), { params: { q: trimmed }, signal: controller.signal })
                .then((response) => setSuggestions(response.data.suggestions ?? []))
                .catch((error) => {
                    if (error.code === 'ERR_CANCELED') return;
                    setSuggestions([]);
                })
                .finally(() => setLoading(false));
        }, debounceMs);

        return () => clearTimeout(timerRef.current);
    }, [query, minLength, debounceMs]);

    return { suggestions, loading };
}
