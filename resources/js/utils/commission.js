/**
 * Mirrors App\Services\Commission\CommissionService and Vendor::calculateVat()/
 * calculateShippingFee() in JS — used ONLY to give a vendor a live "Estimated Earnings" preview
 * while editing a product (see Components/EstimatedEarnings.jsx). The real charge always happens
 * server-side (CheckoutService), so this never needs to be authoritative, only accurate enough to
 * match what checkout will actually compute.
 */

// Same basis-point integer math as the PHP side, so a rate like 7.5% never drifts from rounding.
function applyPercentage(amountKobo, percentage) {
    const basisPoints = Math.round((Number(percentage) || 0) * 100);

    return Math.floor((amountKobo * basisPoints) / 10000);
}

export function commissionRateFor(afterDiscountKobo, tiers, vendorDefaultRate) {
    const sorted = [...(tiers || [])].sort((a, b) => a.min_amount - b.min_amount);

    for (const tier of sorted) {
        const min = Number(tier.min_amount);
        const max = tier.max_amount === null || tier.max_amount === '' || tier.max_amount === undefined
            ? null
            : Number(tier.max_amount);

        if (afterDiscountKobo >= min && (max === null || afterDiscountKobo < max)) {
            return { rate: Number(tier.rate), source: 'tier' };
        }
    }

    return { rate: Number(vendorDefaultRate) || 0, source: 'vendor default' };
}

export function shippingFeeFor(afterDiscountKobo, vendor) {
    switch (vendor?.shipping_fee_type) {
        case 'fixed':
            return Number(vendor.shipping_fee_fixed_amount) || 0;
        case 'percentage':
            return applyPercentage(afterDiscountKobo, vendor.shipping_fee_percentage);
        case 'both':
            return (Number(vendor.shipping_fee_fixed_amount) || 0) + applyPercentage(afterDiscountKobo, vendor.shipping_fee_percentage);
        default:
            return 0;
    }
}

/**
 * @param {number} priceKobo unit price
 * @param {number} quantity
 * @param {object} vendor { commission_rate, vat_rate, shipping_fee_type, shipping_fee_fixed_amount, shipping_fee_percentage }
 * @param {array} commissionTiers
 */
export function estimateEarnings(priceKobo, quantity, vendor, commissionTiers) {
    const subtotal = Math.max(0, priceKobo) * Math.max(0, quantity);
    const vatAmount = applyPercentage(subtotal, vendor?.vat_rate);
    const shippingAmount = shippingFeeFor(subtotal, vendor);
    const total = subtotal + vatAmount + shippingAmount;
    const { rate: commissionRate, source } = commissionRateFor(subtotal, commissionTiers, vendor?.commission_rate);
    const commission = applyPercentage(subtotal, commissionRate);
    const payout = total - commission;

    return {
        subtotal,
        vatAmount,
        shippingAmount,
        total,
        commissionRate,
        commissionSource: source,
        commission,
        payout,
        payoutPerUnit: quantity > 0 ? Math.floor(payout / quantity) : 0,
    };
}
