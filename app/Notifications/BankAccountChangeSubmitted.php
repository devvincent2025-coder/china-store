<?php

namespace App\Notifications;

use App\Models\Vendor;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin/super_admin once a vendor has confirmed their bank-change request via OTP —
 * this is the cue for support to call the vendor and, once satisfied, approve it in
 * Filament\Resources\VendorResource. See App\Services\Vendor\VendorBankChangeService::verifyOtp().
 */
class BankAccountChangeSubmitted extends Notification implements ShouldQueue
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
            ->subject('Bank account change awaiting approval: '.$this->vendor->business_name)
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->vendor->business_name} has confirmed a request to change their payout bank account.")
            ->line('New account: '.$this->vendor->pending_bank_name.' — '.$this->vendor->pending_account_number)
            ->line('Please call the vendor to confirm before approving.')
            ->action('Review Vendor', url('/admin/vendors/'.$this->vendor->id));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Bank change awaiting approval',
            body: $this->vendor->business_name.' requested a payout bank account change',
            actionUrl: url('/admin/vendors/'.$this->vendor->id),
            icon: 'money',
        );
    }
}
