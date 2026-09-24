<?php

namespace App\Notifications\Marketing;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to vendors with few products or no active coupon, nudging them toward listing more and
 * running discounts — see App\Console\Commands\SendVendorEngagementReminders.
 */
class VendorEngagementReminder extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly bool $needsProducts, private readonly bool $needsDiscount)
    {
    }

    public function via(object $notifiable): array
    {
        // Marketing is the one category a buyer/vendor can actually opt out of — see
        // App\Http\Controllers\EmailPreferenceController and every email footer's unsubscribe link.
        return $notifiable->marketing_opt_out ? ['database'] : ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Get more customers',
            body: $this->needsProducts
                ? 'Add more products to your catalog to get discovered more often.'
                : 'Set up a discount coupon to stand out and win more orders.',
            actionUrl: route('vendor.products.create'),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Get more customers on ChinaInNigeria')
            ->greeting("Hi {$notifiable->vendor->business_name},");

        if ($this->needsProducts) {
            $message->line("Stores with more products get discovered more often. Add a few more items to your catalog to reach more buyers.");
        }

        if ($this->needsDiscount) {
            $message->line('Buyers are drawn to a good deal — set up a discount coupon to stand out and win more orders.');
        }

        return $message
            ->action('Add Products', route('vendor.products.create'))
            ->line("Want to run a discount instead? Create one from your Coupons page: ".route('vendor.coupons.index'));
    }
}
