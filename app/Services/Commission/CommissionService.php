<?php

namespace App\Services\Commission;

use App\Models\Setting;
use App\Models\Vendor;

/**
 * Tiered commission rates, matching how Shopify's own transaction fees shrink as merchants move up
 * plans/volume (2.9% → 2.7% → 2.5% → 2.15%, eventually 0.25% of revenue past $800k/mo on Plus) —
 * here the tier is chosen per vendor_order by that ORDER's own subtotal amount, since a marketplace
 * commission (unlike a subscription plan) is decided transaction-by-transaction, not month-to-month.
 *
 * Tiers are admin-configurable from Filament\Pages\Settings, stored as JSON under the
 * 'commission_tiers' key: [{"min_amount": 0, "max_amount": 5000000, "rate": 10.0}, ...] (amounts in
 * kobo). If no tiers are configured, or none match, the vendor's own flat commission_rate applies —
 * so this is purely additive on top of the existing per-vendor rate, never a breaking change.
 */
class CommissionService
{
    /**
     * @return array{0: float, 1: string} [rate percentage, source label for display]
     */
    public static function rateFor(Vendor $vendor, int $amountKobo): array
    {
        $tiers = self::tiers();

        foreach ($tiers as $tier) {
            $min = (int) $tier['min_amount'];
            $max = $tier['max_amount'] !== null && $tier['max_amount'] !== '' ? (int) $tier['max_amount'] : null;

            if ($amountKobo >= $min && ($max === null || $amountKobo < $max)) {
                return [(float) $tier['rate'], 'tier'];
            }
        }

        return [(float) $vendor->commission_rate, 'vendor default'];
    }

    /**
     * @return array<int, array{min_amount: int, max_amount: int|null, rate: float}>
     */
    public static function tiers(): array
    {
        $raw = Setting::get('commission_tiers', '[]');
        $tiers = json_decode((string) $raw, true) ?: [];

        // Ascending by min_amount so the first match in rateFor() is always the narrowest/lowest
        // applicable bracket, regardless of the order an admin saved them in.
        usort($tiers, fn ($a, $b) => $a['min_amount'] <=> $b['min_amount']);

        return $tiers;
    }
}
