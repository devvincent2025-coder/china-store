<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhoneOtpBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_completes_signup_with_no_code_when_otp_is_disabled(): void
    {
        Config::set('services.smart_sms.phone_otp_required', false);

        $user = User::factory()->create(['requires_phone_verification' => true, 'phone_verified_at' => null]);
        $this->actingAs($user);

        $this->post(route('register.complete-profile'), ['name' => 'Jane Doe', 'phone' => '08012345678'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'))
            ->assertSessionHas('justRegistered', true);

        $user->refresh();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertFalse($user->requires_phone_verification);
        $this->assertNull($user->otp_hash);
        $this->assertFalse($user->needsPhoneVerification());
    }

    public function test_buyer_still_gets_a_real_code_when_otp_is_enabled(): void
    {
        Config::set('services.smart_sms.phone_otp_required', true);
        // Force SmsService::isConfigured() false regardless of what's in this environment's real
        // .env, so this test exercises the "no gateway" log-only branch rather than attempting a
        // real network call to SmartSMSSolutions.
        Config::set('services.smart_sms.username', null);
        Config::set('services.smart_sms.password', null);

        $user = User::factory()->create(['requires_phone_verification' => true, 'phone_verified_at' => null]);
        $this->actingAs($user);

        $this->post(route('register.complete-profile'), ['name' => 'Jane Doe', 'phone' => '08012345678'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(); // back(), not straight to home.

        $user->refresh();
        $this->assertNull($user->phone_verified_at);
        $this->assertNotNull($user->otp_hash);
        $this->assertTrue($user->needsPhoneVerification());
    }

    public function test_vendor_phone_is_verified_immediately_with_no_code_when_otp_is_disabled(): void
    {
        Config::set('services.smart_sms.phone_otp_required', false);

        Role::firstOrCreate(['name' => 'vendor']);
        // identity.phone.send-otp sits behind the 'vendor.active' middleware group.
        $vendor = Vendor::factory()->verified()->create(['phone_verified_at' => null]);
        $vendor->user->assignRole('vendor');
        $this->actingAs($vendor->user);

        $this->post(route('vendor.identity.phone.send-otp'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Phone number verified.');

        $vendor->refresh();
        $this->assertNotNull($vendor->phone_verified_at);
        $this->assertNull($vendor->phone_otp_hash);
    }
}
