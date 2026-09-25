<?php

namespace App\Jobs;

use App\Models\Vendor;
use App\Notifications\VendorBankVerificationResult;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use App\Services\Vendor\VendorOnboardingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Step 5 of vendor onboarding: automatically resolves the vendor's entered account number/bank
 * code against Korapay with zero human review. Dispatched right after the vendor submits their
 * bank details form.
 */
// Pinned to the "database" queue connection in the constructor below (not QUEUE_CONNECTION's default) — this calls an external API and shouldn't block the request that triggers it, even on a shared host that sets QUEUE_CONNECTION=sync so NOTIFICATIONS send instantly instead (see .env.example). Still needs something processing the "database" queue: a real `queue:work` daemon, or the cron-driven Schedule::command('queue:work --stop-when-empty') in routes/console.php.
class VerifyVendorBankAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly Vendor $vendor)
    {
        $this->onConnection('database');
    }

    public function handle(KorapayService $korapay, VendorOnboardingService $onboarding): void
    {
        try {
            $result = $korapay->resolveBankAccount($this->vendor->account_number, $this->vendor->bank_code);

            $resolvedName = $result['account_name'] ?? null;
            $nameMatches = $resolvedName && $this->namesReasonablyMatch($resolvedName, $this->vendor->business_name);

            $this->vendor->update([
                'resolved_account_name' => $resolvedName,
                'bank_verification_status' => $resolvedName ? 'verified' : 'failed',
                'bank_verification_response' => $result,
                'bank_verified_at' => now(),
            ]);

            $this->vendor->user->notify(new VendorBankVerificationResult(
                success: (bool) $resolvedName,
                resolvedName: $resolvedName,
                nameMatches: $nameMatches,
            ));

            // Both automated gates (documents + bank) may now be satisfied — attempt to go live.
            $onboarding->activateIfReady($this->vendor->refresh());
        } catch (KorapayRequestException $e) {
            $this->vendor->update([
                'bank_verification_status' => 'failed',
                'bank_verification_response' => ['error' => $e->getMessage()],
            ]);

            $this->vendor->user->notify(new VendorBankVerificationResult(success: false, resolvedName: null, nameMatches: false));
        }
    }

    /**
     * A vendor's registered business name rarely matches the bank account holder's name exactly
     * (personal account vs. registered business name), so this is a soft heuristic used only to
     * decide whether to show a warning banner — it never blocks onboarding on its own.
     */
    private function namesReasonablyMatch(string $resolvedName, string $businessName): bool
    {
        $normalize = fn (string $value) => Str::of($value)->lower()->replaceMatches('/[^a-z0-9 ]/', '')->squish();

        $resolvedWords = explode(' ', (string) $normalize($resolvedName));
        $businessWords = explode(' ', (string) $normalize($businessName));

        return count(array_intersect($resolvedWords, $businessWords)) > 0;
    }
}
