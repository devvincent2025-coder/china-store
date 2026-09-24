/**
 * Maps common color option labels (EN / ZH / FR from seed data) to CSS colors
 * so the product page can render swatch circles instead of text chips.
 */
const COLOR_MAP = {
    black: '#111111',
    blue: '#2563eb',
    white: '#ffffff',
    red: '#ef4444',
    green: '#16a34a',
    yellow: '#eab308',
    orange: '#FF5000',
    pink: '#ec4899',
    purple: '#9333ea',
    brown: '#92400e',
    grey: '#6b7280',
    gray: '#6b7280',
    gold: '#d4a017',
    silver: '#c0c0c0',
    beige: '#e8d5b7',
    navy: '#1e3a8a',
    蓝色: '#2563eb',
    黑色: '#111111',
    白色: '#ffffff',
    bleu: '#2563eb',
    noir: '#111111',
    blanc: '#ffffff',
};

/**
 * @param {string|Record<string, string>|null|undefined} field
 * @returns {string|null} hex/css color or null if not recognized as a color
 */
export function resolveColorSwatch(field) {
    if (field == null) return null;

    const candidates = typeof field === 'string'
        ? [field]
        : Object.values(field ?? {}).filter(Boolean);

    for (const raw of candidates) {
        const key = String(raw).trim().toLowerCase();
        if (COLOR_MAP[key]) return COLOR_MAP[key];
        // Accept raw hex from vendors: "#FF5000" or "FF5000"
        if (/^#?[0-9a-f]{6}$/i.test(key)) {
            return key.startsWith('#') ? key : `#${key}`;
        }
    }

    return null;
}

/**
 * True when an option is a color picker (by option name), so we render swatches.
 */
export function isColorOption(optionName) {
    if (optionName == null) return false;
    const names = typeof optionName === 'string'
        ? [optionName]
        : Object.values(optionName ?? {});
    return names.some((n) => /^(color|colour|颜色|couleur)$/i.test(String(n).trim()));
}
