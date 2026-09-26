<?php

namespace App\Notifications;

use App\Models\Vendor;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin/super_admin once a vendor uploads (or re-uploads, after a rejection) their
 * business verification document during onboarding — see App\Http\Controllers\Vendor\
 * OnboardingController::updateProfile() and Filament\Resources\VendorResource's documents section.
 * Mirrors NewIdentityVerificationSubmitted, which does the same for the separate KYC document.
 */
class NewVendorDocumentsSubmitted extends Notification implements ShouldQueue
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
            ->subject('Business documents submitted: '.$this->vendor->business_name)
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->vendor->business_name} submitted a business verification document for review.")
            ->action('Review Vendor', url('/admin/vendors/'.$this->vendor->id));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Business documents submitted',
            body: $this->vendor->business_name.' submitted a document for review',
            actionUrl: url('/admin/vendors/'.$this->vendor->id),
            icon: 'warning',
        );
    }
}
