<?php

namespace App\Support;

/**
 * Fallback bank list used only when Korapay's own /misc/banks endpoint is unreachable or
 * misconfigured (see Vendor\ProfileController::listBanksSafely() and
 * Vendor\OnboardingController::listBanksSafely()) — this is the standard set of NIBSS bank codes
 * that Nigerian payment processors (Korapay, Paystack, Flutterwave) all use identically, so a code
 * from this list resolves correctly against Korapay's account-resolution API regardless of whether
 * their bank-list endpoint itself is up.
 */
class NigerianBanks
{
    /**
     * @return array<int, array{code: string, name: string}>
     */
    public static function list(): array
    {
        return [
            ['code' => '044', 'name' => 'Access Bank'],
            ['code' => '063', 'name' => 'Access Bank (Diamond)'],
            ['code' => '023', 'name' => 'Citibank Nigeria'],
            ['code' => '050', 'name' => 'Ecobank Nigeria'],
            ['code' => '011', 'name' => 'First Bank of Nigeria'],
            ['code' => '214', 'name' => 'First City Monument Bank'],
            ['code' => '070', 'name' => 'Fidelity Bank'],
            ['code' => '058', 'name' => 'Guaranty Trust Bank'],
            ['code' => '030', 'name' => 'Heritage Bank'],
            ['code' => '301', 'name' => 'Jaiz Bank'],
            ['code' => '082', 'name' => 'Keystone Bank'],
            ['code' => '526', 'name' => 'Parallex Bank'],
            ['code' => '076', 'name' => 'Polaris Bank'],
            ['code' => '101', 'name' => 'Providus Bank'],
            ['code' => '221', 'name' => 'Stanbic IBTC Bank'],
            ['code' => '068', 'name' => 'Standard Chartered Bank'],
            ['code' => '232', 'name' => 'Sterling Bank'],
            ['code' => '032', 'name' => 'Union Bank of Nigeria'],
            ['code' => '033', 'name' => 'United Bank For Africa'],
            ['code' => '215', 'name' => 'Unity Bank'],
            ['code' => '035', 'name' => 'Wema Bank'],
            ['code' => '057', 'name' => 'Zenith Bank'],
            ['code' => '50211', 'name' => 'Kuda Bank'],
            ['code' => '100004', 'name' => 'Opay'],
            ['code' => '090267', 'name' => 'Moniepoint MFB'],
            ['code' => '999992', 'name' => 'PalmPay'],
        ];
    }
}
