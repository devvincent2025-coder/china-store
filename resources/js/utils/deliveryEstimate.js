/**
 * "2-4 days" or "2 days" from a vendor's delivery_days_min/max — null when the vendor hasn't set
 * an estimate, so every call site can just skip rendering anything.
 */
export function formatDeliveryEstimate(min, max, __) {
    if (!min) return null;

    const range = max && max !== min ? `${min}-${max} ${__('days')}` : `${min} ${__('days')}`;

    return __('Delivers in :range', { range });
}
