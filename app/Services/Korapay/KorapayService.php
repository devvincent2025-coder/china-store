<?php

namespace App\Services\Korapay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin, dependency-free wrapper around Korapay's Merchant API.
 *
 * There is no official Korapay PHP SDK, so every call here is a plain HTTP request built from
 * Korapay's published API reference. Endpoint paths are kept as class constants so a future
 * Korapay API version bump only touches one place. If Korapay changes a payload shape, update the
 * corresponding `build*Payload()` method rather than the call site.
 *
 * All secret-key calls (resolve account, create subaccount, initialize charge) MUST run server-side
 * only — never expose KORAPAY_SECRET_KEY to the frontend. The public key is safe for client-side
 * inline checkout widgets, which this platform does not use (checkout redirects instead).
 */
class KorapayService
{
    private const BASE_URL = 'https://api.korapay.com/merchant/api/v1';

    private const ENDPOINT_BANKS = '/misc/banks';

    private const ENDPOINT_RESOLVE_ACCOUNT = '/misc/banks/resolve';

    private const ENDPOINT_SUBACCOUNTS = '/subaccounts';

    private const ENDPOINT_CHARGE_INITIALIZE = '/charges/initialize';

    private const ENDPOINT_CHARGE_VERIFY = '/charges';

    private const ENDPOINT_DISBURSE = '/transactions/disburse';

    private const ENDPOINT_REFUND_INITIATE = '/refunds/initiate';

    // Not readonly: falls back to config() when the constructor arg is omitted, which means
    // reassigning the promoted property inside the constructor body below — PHP treats that as a
    // second write to a readonly property and throws, since promotion already counts as the first.
    public function __construct(
        private string $secretKey = '',
    ) {
        $this->secretKey = $secretKey !== '' ? $secretKey : (string) config('services.korapay.secret_key');
    }

    /**
     * Fetches Korapay's list of supported Nigerian banks (name + CBN bank code), used to populate
     * the bank dropdown in the vendor onboarding form.
     */
    public function listBanks(): array
    {
        $response = $this->client()->get(self::BASE_URL.self::ENDPOINT_BANKS, [
            'countryCode' => 'NG',
        ]);

        return $this->unwrap($response, 'list Nigerian banks');
    }

    /**
     * Automated bank account resolution — step 5 of vendor onboarding. Korapay looks up the account
     * name for the given account number + bank code so we can show it back to the vendor for
     * confirmation, with zero human review.
     */
    public function resolveBankAccount(string $accountNumber, string $bankCode): array
    {
        $response = $this->client()->post(self::BASE_URL.self::ENDPOINT_RESOLVE_ACCOUNT, [
            'bank' => $bankCode,
            'account' => $accountNumber,
        ]);

        return $this->unwrap($response, 'resolve bank account');
    }

    /**
     * Creates a Korapay subaccount (split payment destination) for a vendor once their bank details
     * are verified and their documents are approved. This is the automated step 7 of onboarding —
     * no manual dashboard work required.
     */
    public function createSubaccount(string $businessName, string $bankCode, string $accountNumber, string $email): array
    {
        $response = $this->client()->post(self::BASE_URL.self::ENDPOINT_SUBACCOUNTS, [
            'business_name' => $businessName,
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'email' => $email,
        ]);

        return $this->unwrap($response, 'create vendor subaccount');
    }

    /**
     * Initializes a single checkout charge that Korapay will automatically split across every
     * vendor represented in the order, plus the platform's commission. `splits` is an array of
     * ['subaccount_code' => string, 'amount' => float] pairs — amounts in naira, not kobo, per
     * Korapay's API convention (unlike the rest of this app, which stores money in kobo internally).
     */
    public function initializeSplitCharge(
        string $reference,
        float $amountNaira,
        string $customerEmail,
        string $customerName,
        array $splits,
        string $redirectUrl,
    ): array {
        $response = $this->client()->post(self::BASE_URL.self::ENDPOINT_CHARGE_INITIALIZE, [
            'amount' => $amountNaira,
            'currency' => 'NGN',
            'reference' => $reference,
            'narration' => 'ChinaInNigeria order '.$reference,
            'redirect_url' => $redirectUrl,
            'notification_url' => route('webhooks.korapay'),
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
            ],
            'merchant_bears_cost' => false,
            'split_payment' => [
                'type' => 'flat',
                'bearer' => 'customer',
                'subaccounts' => $splits,
            ],
        ]);

        return $this->unwrap($response, 'initialize split charge');
    }

    /**
     * Plain (non-split) charge — the whole amount lands in the platform's own Korapay balance
     * rather than fanning out to vendor subaccounts at charge time. This is what makes escrow
     * possible: see App\Services\Escrow\EscrowService, which later disburses each vendor's payout
     * separately via disburseToBank() once their order clears the return window.
     */
    public function initializeCharge(
        string $reference,
        float $amountNaira,
        string $customerEmail,
        string $customerName,
        string $redirectUrl,
    ): array {
        $response = $this->client()->post(self::BASE_URL.self::ENDPOINT_CHARGE_INITIALIZE, [
            'amount' => $amountNaira,
            'currency' => 'NGN',
            'reference' => $reference,
            'narration' => 'ChinaInNigeria order '.$reference,
            'redirect_url' => $redirectUrl,
            'notification_url' => route('webhooks.korapay'),
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
            ],
        ]);

        return $this->unwrap($response, 'initialize charge');
    }

    /**
     * Verifies a charge's final status — called from the webhook handler (and as a fallback poll
     * from the order confirmation page) so payment confirmation never depends solely on the buyer's
     * browser reaching the redirect_url.
     */
    public function verifyCharge(string $reference): array
    {
        $response = $this->client()->get(self::BASE_URL.self::ENDPOINT_CHARGE_VERIFY.'/'.$reference);

        return $this->unwrap($response, 'verify charge');
    }

    /**
     * Escrow release — pays a vendor's held-back amount straight into their own (already
     * onboarding-verified) bank account. This is a real bank transfer, not a Korapay subaccount
     * split, so it can happen independently and much later than the original charge — exactly the
     * "hold, then release on delivery" behavior escrow requires. Completion is asynchronous: Korapay
     * confirms via the `transfer.success`/`transfer.failed` webhook (see KorapayWebhookController).
     */
    public function disburseToBank(
        string $reference,
        float $amountNaira,
        string $bankCode,
        string $accountNumber,
        string $customerName,
        string $customerEmail,
        string $narration,
    ): array {
        $response = $this->client()->post(self::BASE_URL.self::ENDPOINT_DISBURSE, [
            'reference' => $reference,
            'destination' => [
                'type' => 'bank_account',
                'amount' => $amountNaira,
                'currency' => 'NGN',
                'narration' => $narration,
                'bank_account' => [
                    'bank' => $bankCode,
                    'account' => $accountNumber,
                ],
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                ],
            ],
        ]);

        return $this->unwrap($response, 'disburse escrow payout to vendor bank account');
    }

    /**
     * Refunds all or part of the original charge back to the buyer — used when an admin resolves a
     * dispute (see Filament\Resources\VendorOrderDisputeResource) in the buyer's favor. Omitting
     * `amountNaira` refunds the original transaction in full; passing it refunds only that vendor's
     * share of a multi-vendor order, leaving the rest of the charge (and the other vendors' escrow)
     * untouched. Completion is asynchronous via the `refund.success`/`refund.failed` webhook.
     */
    public function refundCharge(string $paymentReference, string $reference, ?float $amountNaira = null, ?string $reason = null): array
    {
        $response = $this->client()->post(self::BASE_URL.self::ENDPOINT_REFUND_INITIATE, array_filter([
            'payment_reference' => $paymentReference,
            'reference' => $reference,
            'amount' => $amountNaira,
            'reason' => $reason,
        ], fn ($value) => $value !== null));

        return $this->unwrap($response, 'initiate refund');
    }

    private function client()
    {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    private function unwrap($response, string $action): array
    {
        if ($response->failed()) {
            Log::error("Korapay: failed to {$action}", [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new KorapayRequestException("Korapay request failed while trying to {$action}: ".$response->body());
        }

        return $response->json('data') ?? [];
    }
}
