import { useMemo, useState } from 'react';
import { formatNaira, nairaToKobo } from '@/utils/money';
import { estimateEarnings } from '@/utils/commission';
import { useTranslation } from '@/hooks/useTranslation';

/**
 * Live "how much would I actually make" preview shown at the bottom of the vendor product
 * form — mirrors the exact math CheckoutService/EscrowService use for a real sale (tiered
 * commission on goods revenue, the vendor's own VAT and shipping configuration added on top),
 * so what a vendor sees here matches what an order breakdown will later show.
 */
export default function EstimatedEarnings({ price, stock, vendor, commissionTiers = [], vatRatePercent = 7.5 }) {
    const { __ } = useTranslation();
    const [quantity, setQuantity] = useState('');

    const effectiveQuantity = quantity !== '' ? Number(quantity) : Number(stock) || 1;
    const priceKobo = nairaToKobo(price);

    const estimate = useMemo(
        () => estimateEarnings(priceKobo, effectiveQuantity, vendor, commissionTiers, vatRatePercent),
        [priceKobo, effectiveQuantity, vendor, commissionTiers, vatRatePercent],
    );

    if (!vendor || !price || Number(price) <= 0) {
        return (
            <div className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-5 text-sm text-neutral-400">
                {__('Enter a price to see your estimated earnings.')}
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-neutral-200 bg-white p-5">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-semibold text-neutral-800">{__('Estimated Earnings')}</h2>
                <label className="flex items-center gap-2 text-xs text-neutral-500">
                    {__('If you sell')}
                    <input
                        type="number"
                        min="1"
                        value={quantity}
                        onChange={(e) => setQuantity(e.target.value)}
                        placeholder={String(stock || 1)}
                        className="w-20 rounded-md border-neutral-300 text-sm"
                    />
                    {__('unit(s)')}
                </label>
            </div>

            <div className="space-y-1.5 text-sm">
                <div className="flex justify-between">
                    <span className="text-neutral-500">{__('Revenue')} ({effectiveQuantity} × {formatNaira(priceKobo)})</span>
                    <span>{formatNaira(estimate.subtotal)}</span>
                </div>
                {estimate.vatAmount > 0 && (
                    <div className="flex justify-between">
                        <span className="text-neutral-500">{__('VAT')} ({Number(vatRatePercent)}%)</span>
                        <span>+{formatNaira(estimate.vatAmount)}</span>
                    </div>
                )}
                {estimate.shippingAmount > 0 && (
                    <div className="flex justify-between">
                        <span className="text-neutral-500">{__('Shipping fee you collect')}</span>
                        <span>+{formatNaira(estimate.shippingAmount)}</span>
                    </div>
                )}
                <div className="flex justify-between border-t border-neutral-100 pt-1.5 text-neutral-700">
                    <span>{__('Total charged to buyer')}</span>
                    <span>{formatNaira(estimate.total)}</span>
                </div>
                <div className="flex justify-between text-neutral-500">
                    <span>
                        {__('Platform commission')} ({estimate.commissionRate}%)
                        {estimate.commissionSource === 'tier' && (
                            <span className="ml-1 rounded bg-blue-50 px-1 text-[10px] font-semibold text-blue-600">{__('tiered')}</span>
                        )}
                    </span>
                    <span>-{formatNaira(estimate.commission)}</span>
                </div>
                <div className="flex justify-between border-t border-neutral-100 pt-1.5 text-base font-bold text-green-700">
                    <span>{__('Estimated Payout')}</span>
                    <span>{formatNaira(estimate.payout)}</span>
                </div>
                <div className="flex justify-between text-xs text-neutral-400">
                    <span>{__('Per unit')}</span>
                    <span>{formatNaira(estimate.payoutPerUnit)}</span>
                </div>
            </div>

            <p className="mt-3 text-[11px] text-red-500">
                {__('Estimate only — actual payout depends on the final order (coupons, exact commission tier, etc.) and is held in escrow until delivery is confirmed.')}
            </p>
        </div>
    );
}
