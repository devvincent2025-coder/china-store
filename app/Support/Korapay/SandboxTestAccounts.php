<?php

namespace App\Support\Korapay;

/**
 * Korapay's own documented sandbox test bank accounts — see
 * https://developers.korapay.com/docs/testing-your-integration. These only resolve/disburse
 * against a Korapay TEST secret key (config('services.korapay.secret_key') starting with sk_test_);
 * they are rejected outright in live mode, and any other made-up account number fails in sandbox
 * too — Korapay's test environment only recognizes these specific numbers.
 *
 * Used to seed dev/test vendors with an account that actually succeeds (see VendorSeeder), and
 * available here for any test that specifically wants to exercise a failed or invalid payout.
 */
final class SandboxTestAccounts
{
    public const NGN_SUCCESS = ['bank_code' => '033', 'account_number' => '0000000000'];

    public const NGN_FAILED = ['bank_code' => '035', 'account_number' => '0000000000'];

    public const NGN_INVALID = ['bank_code' => '011', 'account_number' => '9999999999'];
}
