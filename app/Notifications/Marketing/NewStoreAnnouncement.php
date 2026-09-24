<?php

namespace App\Notifications\Marketing;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Broadcast to buyers when one or more vendors have finished onboarding since the last send — see
 * App\Console\Commands\SendNewStoreAnnouncements.
 *
 * @param  Collection<int, \App\Models\Vendor>  $vendors
 */
class NewStoreAnnouncement extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly Collection $vendors)
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
            title: $this->vendors->count() > 1 ? 'New stores just joined' : "New store: {$this->vendors->first()->business_name}",
            body: $this->vendors->pluck('business_name')->implode(', '),
            actionUrl: route('stores.index'),
            icon: 'package',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->vendors->count() > 1 ? 'New stores just joined ChinaInNigeria' : "New store: {$this->vendors->first()->business_name}")
            ->greeting("Hi {$notifiable->name},")
            ->line('New stores just opened on ChinaInNigeria:');

        foreach ($this->vendors as $vendor) {
            $store = $vendor->store;
            $location = collect([$store?->address_line, $store?->city, $store?->state])->filter()->implode(', ');
            $description = $store?->getTranslation('description', 'en');

            $message->line("**{$vendor->business_name}**".($location ? " — {$location}" : ''));

            if ($description) {
                $message->line($description);
            }
        }

        return $message->action('Browse New Stores', route('stores.index'));
    }
}
