<?php

namespace App\Notifications;

use App\Models\Vendor;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin/super_admin once a vendor uploads a passport/ID for KYC review — see
 * App\Services\Vendor\VendorIdentityService and Filament\Resources\VendorResource's identity
 * section.
 */
class NewIdentityVerificationSubmitted extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly Vendor $vendor)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Identity verification submitted: '.$this->vendor->business_name)
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->vendor->business_name} uploaded an identity document for KYC review.")
            ->action('Review Vendor', url('/admin/vendors/'.$this->vendor->id));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Identity verification submitted',
            body: $this->vendor->business_name.' uploaded an ID for KYC review',
            actionUrl: url('/admin/vendors/'.$this->vendor->id),
            icon: 'warning',
        );
    }
}
