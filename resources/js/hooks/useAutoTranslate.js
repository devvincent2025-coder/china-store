import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

const LOCALES = ['zh', 'en', 'fr'];

async function requestTranslations(text, from, to) {
    const { data } = await axios.post(route('vendor.translate'), { text, from, to });
    const translations = { ...(data?.translations ?? {}) };
    delete translations[from];
    const failed = Array.isArray(data?.failed)
        ? data.failed.filter((locale) => locale !== from)
        : to.filter((locale) => !translations[locale]);
    return { translations, failed };
}

/**
 * Translate on blur after the vendor edits text.
 * Applies successful locales immediately; auto-retries once for any that failed (common for zh).
 */
export function useAutoTranslate({ onApply }) {
    const [status, setStatus] = useState('idle'); // idle | translating | done | partial | error
    const requestIdRef = useRef(0);
    const onApplyRef = useRef(onApply);
    const lastAttemptRef = useRef({});
    onApplyRef.current = onApply;

    useEffect(() => () => {
        requestIdRef.current += 1;
    }, []);

    const translate = useCallback(async (text, fromLocale) => {
        const trimmed = String(text ?? '').trim();
        if (!trimmed) {
            setStatus('idle');
            return;
        }

        const previous = lastAttemptRef.current[fromLocale];
        if (previous?.text === trimmed && previous.complete) {
            return;
        }

        const reqId = ++requestIdRef.current;
        const source = fromLocale;
        const targets = LOCALES.filter((locale) => locale !== source);
        setStatus('translating');

        try {
            let { translations, failed } = await requestTranslations(trimmed, source, targets);
            if (reqId !== requestIdRef.current) return;

            if (Object.keys(translations).length > 0) {
                onApplyRef.current?.(translations, source);
            }

            // One automatic retry for missing locales (MyMemory often drops zh on the first pass).
            if (failed.length > 0) {
                await new Promise((resolve) => setTimeout(resolve, 350));
                if (reqId !== requestIdRef.current) return;

                const retry = await requestTranslations(trimmed, source, failed);
                if (reqId !== requestIdRef.current) return;

                if (Object.keys(retry.translations).length > 0) {
                    onApplyRef.current?.(retry.translations, source);
                    translations = { ...translations, ...retry.translations };
                }
                failed = retry.failed;
            }

            const complete = targets.every((locale) => Boolean(translations[locale]));
            lastAttemptRef.current[source] = { text: trimmed, complete };

            if (complete) {
                setStatus('done');
            } else if (Object.keys(translations).length > 0) {
                setStatus('partial');
            } else {
                setStatus('error');
            }
        } catch {
            if (reqId === requestIdRef.current) {
                setStatus('error');
            }
        }
    }, []);

    return { status, translate };
}
