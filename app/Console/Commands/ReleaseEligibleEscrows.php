<?php

namespace App\Console\Commands;

use App\Models\VendorOrder;
use App\Services\Escrow\EscrowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily sweep that auto-releases escrow for any delivered order whose return window has passed
 * with no open dispute — mirrors Jumia's own behavior of releasing funds to the seller once the
 * customer's return window lapses, whether or not the buyer ever clicks "Confirm Receipt".
 */
class ReleaseEligibleEscrows extends Command
{
    protected $signature = 'escrow:release-eligible';

    protected $description = 'Auto-release escrow for delivered orders whose return window has passed with no open dispute';

    public function handle(EscrowService $escrow): int
    {
        $vendorOrders = VendorOrder::query()
            ->where('fulfillment_status', 'delivered')
            ->where('escrow_status', 'holding')
            ->whereNotNull('return_window_expires_at')
            ->where('return_window_expires_at', '<=', now())
            ->whereDoesntHave('disputes', fn ($query) => $query->where('status', 'open'))
            ->get();

        $released = 0;

        foreach ($vendorOrders as $vendorOrder) {
            try {
                $escrow->release($vendorOrder);
                $released++;
            } catch (Throwable $e) {
                Log::error('escrow:release-eligible failed for one vendor_order', [
                    'vendor_order_id' => $vendorOrder->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Released escrow for {$released} of {$vendorOrders->count()} eligible orders.");

        return self::SUCCESS;
    }
}
