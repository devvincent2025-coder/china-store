<?php

namespace App\Jobs;

use App\Models\Vendor;
use App\Notifications\VendorFullyApproved;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Step 7 of vendor onboarding, fully automated: once documents are approved AND bank details are
 * verified, register the vendor as a Korapay split payment destination so future orders can route
 * funds to them without any manual dashboard work.
 */
class CreateVendorSubaccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly Vendor $vendor)
    {
    }

    public function handle(KorapayService $korapay): void
    {
        if (filled($this->vendor->korapay_subaccount_id)) {
            return; // Already provisioned — guards against duplicate dispatch races.
        }

        try {
            $result = $korapay->createSubaccount(
                businessName: $this->vendor->business_name,
                bankCode: $this->vendor->bank_code,
                accountNumber: $this->vendor->account_number,
                email: $this->vendor->contact_email,
            );

            $this->vendor->update([
                'korapay_subaccount_id' => $result['subaccount_code'] ?? $result['id'] ?? null,
                'korapay_subaccount_created_at' => now(),
                'status' => 'active',
            ]);

            $this->vendor->user->notify(new VendorFullyApproved);
        } catch (KorapayRequestException $e) {
            Log::error('Failed to create Korapay subaccount for vendor', [
                'vendor_id' => $this->vendor->id,
                'error' => $e->getMessage(),
            ]);

            $this->release(now()->addMinutes(10));
        }
    }
}
