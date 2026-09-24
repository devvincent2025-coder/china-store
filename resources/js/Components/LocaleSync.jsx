import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { setAppLocale } from '@/utils/translatable';

/**
 * Keeps the Spatie-field helper `t()` and <html lang> in sync with the shared Inertia locale.
 */
export default function LocaleSync() {
    const { locale = 'en' } = usePage().props;

    useEffect(() => {
        setAppLocale(locale);
        if (typeof document !== 'undefined') {
            document.documentElement.lang = locale;
        }
    }, [locale]);

    // Sync immediately during render so first paint of child components uses the right locale.
    setAppLocale(locale);

    return null;
}
