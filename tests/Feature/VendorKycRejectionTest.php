<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Notifications\IdentityVerificationApproved;
use App\Notifications\IdentityVerificationRejected;
use App\Services\Vendor\VendorIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorKycRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejecting_identity_notifies_the_vendor_and_records_the_reason(): void
    {
        Notification::fake();

        $vendor = Vendor::factory()->verified()->create(['identity_verification_status' => 'pending']);
        $admin = User::factory()->create();

        app(VendorIdentityService::class)->rejectIdentity($vendor, $admin, 'Photo ID was blurry — please retake it.');

        $vendor->refresh();
        $this->assertSame('rejected', $vendor->identity_verification_status);
        $this->assertSame('Photo ID was blurry — please retake it.', $vendor->identity_rejection_reason);

        Notification::assertSentTo(
            $vendor->user,
            IdentityVerificationRejected::class,
            fn ($notification) => $notification->toArray($vendor->user)['body'] === 'Photo ID was blurry — please retake it.',
        );
    }

    public function test_approving_identity_notifies_the_vendor(): void
    {
        Notification::fake();

        $vendor = Vendor::factory()->verified()->create(['identity_verification_status' => 'pending']);
        $admin = User::factory()->create();

        app(VendorIdentityService::class)->approveIdentity($vendor, $admin);

        Notification::assertSentTo($vendor->user, IdentityVerificationApproved::class);
    }

    /**
     * The whole reason NotificationDispatcher::sendIsolated() exists: Laravel's own multi-channel
     * notify() aborts every later channel once an earlier one throws (see its doc comment) — a
     * broken mail channel must never also swallow the in-app/push ones. Proven here with a real
     * (non-faked) dispatch: 'nonexistent-channel' throws immediately (no such driver registered),
     * but 'database' — listed after it — must still go through and leave a real row behind.
     */
    public function test_a_failing_channel_does_not_prevent_a_later_channel_from_being_attempted(): void
    {
        $vendor = Vendor::factory()->verified()->create();

        $notification = new class('a reason') extends IdentityVerificationRejected {
            public function via(object $notifiable): array
            {
                return ['nonexistent-channel', 'database'];
            }
        };

        \App\Support\Notifications\NotificationDispatcher::sendIsolated($vendor->user, $notification);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($vendor->user->id, $vendor->user->notifications()->first()->notifiable_id);
    }

    public function test_dashboard_shows_a_rejection_banner_with_the_reason(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'vendor']);

        $vendor = Vendor::factory()->verified()->create([
            'identity_verification_status' => 'rejected',
            'identity_rejection_reason' => 'Document was expired.',
        ]);
        $vendor->user->assignRole('vendor');
        $this->actingAs($vendor->user);

        $this->get(route('vendor.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('vendor.identity_verification_status', 'rejected')
                ->where('vendor.identity_rejection_reason', 'Document was expired.'));
    }
}
