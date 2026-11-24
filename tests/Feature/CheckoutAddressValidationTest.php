<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server-side validation of a NEW shipping address on "Complete Payment" — mirrored client-side
 * in Pages/Checkout/Index.jsx (validateAddress), which outlines bad fields in red.
 */
class CheckoutAddressValidationTest extends TestCase
{
    use RefreshDatabase;

    private function submit(array $address)
    {
        return $this->actingAs(User::factory()->create())
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), ['address_id' => null, 'save_address' => false, 'shipping_address' => $address]);
    }

    public function test_empty_address_flags_every_field(): void
    {
        $this->submit(['name' => '', 'phone' => '', 'line1' => '', 'city' => '', 'state' => ''])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors([
                'shipping_address.name' => 'Full name is required.',
                'shipping_address.phone' => 'Phone number is required.',
                'shipping_address.line1' => 'Address is required.',
                'shipping_address.city' => 'City is required.',
                'shipping_address.state' => 'State is required.',
            ]);
    }

    public function test_rejects_invalid_values(): void
    {
        $this->submit(['name' => 'A', 'phone' => '12345', 'line1' => 'x', 'city' => 'L', 'state' => 'Atlantis'])
            ->assertSessionHasErrors([
                'shipping_address.name' => 'Please enter your full name.',
                'shipping_address.phone' => 'Enter a valid Nigerian phone number, e.g. 08031234567.',
                'shipping_address.line1' => 'Please enter your full street address.',
                'shipping_address.city' => 'Please enter a valid city.',
                'shipping_address.state' => 'Please choose a state from the list.',
            ]);
    }

    public function test_accepts_common_nigerian_phone_formats(): void
    {
        foreach (['0803 123 4567', '+234-803-123-4567', '2348031234567', '(0803) 1234567'] as $phone) {
            $this->submit(['name' => 'Ada Obi', 'phone' => $phone, 'line1' => '12 Allen Avenue', 'city' => 'Ikeja', 'state' => 'Lagos'])
                ->assertSessionDoesntHaveErrors(['shipping_address.name', 'shipping_address.phone', 'shipping_address.line1', 'shipping_address.city', 'shipping_address.state']);
        }
    }
}
