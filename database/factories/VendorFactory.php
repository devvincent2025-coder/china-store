<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Korapay\SandboxTestAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        $businessName = fake()->company();

        return [
            'user_id' => User::factory(),
            'business_name' => $businessName,
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => '0'.fake()->numerify('#########'),
            'verification_documents' => null,
            'documents_status' => 'pending',
            'documents_rejection_reason' => null,
            'documents_reviewed_by' => null,
            'documents_reviewed_at' => null,
            'bank_code' => null,
            'bank_name' => null,
            'account_number' => null,
            'resolved_account_name' => null,
            'bank_verification_status' => 'pending',
            'bank_verified_at' => null,
            'bank_verification_response' => null,
            'korapay_subaccount_id' => null,
            'korapay_subaccount_created_at' => null,
            'commission_rate' => 10.00,
            'status' => 'pending',
        ];
    }

    /**
     * A vendor that has already cleared both onboarding gates (document review + bank
     * verification) and has a provisioned Korapay subaccount — ready to sell immediately.
     */
    public function verified(): static
    {
        return $this->state(function (array $attributes) {
            $businessName = $attributes['business_name'] ?? fake()->company();

            return [
                'documents_status' => 'approved',
                'documents_reviewed_at' => now()->subDays(fake()->numberBetween(1, 30)),
                // Korapay's documented NGN sandbox "always succeeds" test account (see
                // App\Support\Korapay\SandboxTestAccounts) — a random fake() number here made every
                // escrow-release/payout test fail against Korapay's sandbox API with "Invalid
                // account.", since only these specific documented test numbers actually resolve.
                'bank_code' => SandboxTestAccounts::NGN_SUCCESS['bank_code'],
                'bank_name' => 'United Bank For Africa',
                'account_number' => SandboxTestAccounts::NGN_SUCCESS['account_number'],
                'resolved_account_name' => strtoupper($businessName),
                'bank_verification_status' => 'verified',
                'bank_verified_at' => now()->subDays(fake()->numberBetween(1, 30)),
                'bank_verification_response' => ['status' => true, 'message' => 'Account resolved'],
                'korapay_subaccount_id' => 'SUB_'.Str::random(10),
                'korapay_subaccount_created_at' => now()->subDays(fake()->numberBetween(1, 30)),
                'status' => 'active',
            ];
        });
    }
}
