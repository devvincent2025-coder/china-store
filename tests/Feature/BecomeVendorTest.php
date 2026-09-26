<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VendorApplicationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BecomeVendorTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_existing_buyer_becomes_a_vendor_on_the_same_account(): void
    {
        Role::firstOrCreate(['name' => 'vendor']);
        Notification::fake();

        $user = User::factory()->create(['phone' => '08012345678']);
        $this->actingAs($user);

        $this->post(route('open-shop.become-vendor'), ['business_name' => "Vincent's Store"])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('vendor.onboarding'))
            ->assertSessionHas('justRegisteredVendor', true);

        $user->refresh();
        $this->assertTrue($user->isVendor());
        $this->assertNotNull($user->vendor);
        $this->assertSame("Vincent's Store", $user->vendor->business_name);
        // The SAME account/login — email and phone carried over, not re-collected.
        $this->assertSame($user->email, $user->vendor->contact_email);
        $this->assertSame('08012345678', $user->vendor->contact_phone);

        Notification::assertSentTo($user, VendorApplicationReceived::class);
    }

    public function test_becoming_a_vendor_does_not_remove_buyer_access(): void
    {
        Role::firstOrCreate(['name' => 'vendor']);
        Role::firstOrCreate(['name' => 'buyer']);

        $user = User::factory()->create(['phone' => '08012345678']);
        $user->assignRole('buyer');
        $this->actingAs($user);

        $this->post(route('open-shop.become-vendor'), ['business_name' => 'Test Store']);

        $user->refresh();
        $this->assertTrue($user->hasRole('buyer'));
        $this->assertTrue($user->hasRole('vendor'));

        // Buyer-facing pages must still work for this now-dual-role account.
        $this->get(route('cart.index'))->assertOk();
    }

    public function test_a_user_with_no_phone_on_file_must_supply_one(): void
    {
        Role::firstOrCreate(['name' => 'vendor']);

        $user = User::factory()->create(['phone' => null]);
        $this->actingAs($user);

        $this->post(route('open-shop.become-vendor'), ['business_name' => 'Test Store'])
            ->assertSessionHasErrors('phone');

        $this->post(route('open-shop.become-vendor'), [
            'business_name' => 'Test Store',
            'phone' => '08099999999',
        ])->assertSessionHasNoErrors();

        $this->assertSame('08099999999', $user->fresh()->vendor->contact_phone);
    }

    public function test_an_already_vendor_account_is_redirected_instead_of_duplicated(): void
    {
        Role::firstOrCreate(['name' => 'vendor']);

        $user = User::factory()->create();
        $user->assignRole('vendor');
        $user->vendor()->create([
            'business_name' => 'Existing Store',
            'contact_email' => $user->email,
            'contact_phone' => '08000000000',
        ]);
        $this->actingAs($user);

        $this->post(route('open-shop.become-vendor'), ['business_name' => 'Duplicate Attempt'])
            ->assertRedirect(route('vendor.dashboard'));

        $this->assertSame(1, $user->fresh()->vendor()->count());
        $this->assertSame('Existing Store', $user->fresh()->vendor->business_name);
    }
}
