import { usePage } from '@inertiajs/react';

/**
 * Mirrors Laravel's __() short-key JSON translation convention: the key IS the English string
 * (e.g. __('Add to Cart')), and lang/{locale}.json supplies the zh/fr equivalents. Falls back to
 * the key itself when a translation is missing, so untranslated strings still render sensibly
 * instead of breaking.
 *
 * `locale` and `translations` are shared on every request by
 * App\Http\Middleware\HandleInertiaRequests::share() — see that file for how the locale itself is
 * resolved (query param > user preference > session > app default).
 */
export function useTranslation() {
    const { translations = {}, locale = 'en' } = usePage().props;

    function __(key, replacements = {}) {
        let translation = translations[key] ?? key;

        Object.entries(replacements).forEach(([placeholder, value]) => {
            translation = translation.replace(`:${placeholder}`, value);
        });

        return translation;
    }

    return { __, locale };
}
