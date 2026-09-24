/**
 * Translatable fields (product titles, category names, etc.) arrive from the backend as objects
 * like { en: "...", zh: "...", fr: "..." } rather than plain strings. This resolves the best
 * value for a given locale, falling back to English, then to whatever is available.
 *
 * Callers can pass an explicit locale; otherwise the last locale synced from Inertia
 * (see LocaleSync / setAppLocale) is used.
 */
let appLocale = 'en';

export function setAppLocale(locale) {
    if (locale && typeof locale === 'string') {
        appLocale = locale;
    }
}

export function getAppLocale() {
    return appLocale;
}

export function t(field, locale = appLocale) {
    if (field == null) return '';
    if (typeof field === 'string') return field;

    return field?.[locale] ?? field?.en ?? Object.values(field ?? {})[0] ?? '';
}
