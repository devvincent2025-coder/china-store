/**
 * Compact country list for phone login/signup. Nigeria is default for this marketplace.
 * Flags are emoji (no image assets). Dial codes exclude the leading +.
 */
export const PHONE_COUNTRIES = [
    { iso: 'NG', name: 'Nigeria', dial: '234', flag: '🇳🇬' },
    { iso: 'GH', name: 'Ghana', dial: '233', flag: '🇬🇭' },
    { iso: 'KE', name: 'Kenya', dial: '254', flag: '🇰🇪' },
    { iso: 'ZA', name: 'South Africa', dial: '27', flag: '🇿🇦' },
    { iso: 'CN', name: 'China', dial: '86', flag: '🇨🇳' },
    { iso: 'US', name: 'United States', dial: '1', flag: '🇺🇸' },
    { iso: 'GB', name: 'United Kingdom', dial: '44', flag: '🇬🇧' },
    { iso: 'AE', name: 'United Arab Emirates', dial: '971', flag: '🇦🇪' },
    { iso: 'IN', name: 'India', dial: '91', flag: '🇮🇳' },
    { iso: 'FR', name: 'France', dial: '33', flag: '🇫🇷' },
];

/**
 * Builds an E.164-ish phone string from country dial code + national number.
 * Strips spaces/dashes and a leading 0 on the national part (common for NG local format).
 */
export function formatIntlPhone(dialCode, nationalNumber) {
    const digits = String(nationalNumber || '').replace(/\D/g, '');
    const national = digits.replace(/^0+/, '');
    if (!national) return '';
    return `+${dialCode}${national}`;
}
