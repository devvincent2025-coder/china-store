/**
 * All prices from the backend arrive as integers in kobo (NGN x 100).
 * Always render money through this helper so formatting stays consistent.
 */
export function formatNaira(kobo) {
    const value = (Number(kobo) || 0) / 100;

    return `₦${value.toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export function koboToNaira(kobo) {
    return (Number(kobo) || 0) / 100;
}

export function nairaToKobo(naira) {
    return Math.round((Number(naira) || 0) * 100);
}
