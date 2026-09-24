<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushMessage;

/**
 * One consistent shape for every in-app ("database" channel) notification across the whole
 * platform — buyers, vendors, and admins all read the same `data` JSON shape, whether it's
 * rendered by the storefront's NotificationBell.jsx or by Filament's own built-in notification
 * bell (admins). Keeping this in one trait means every notification class's toArray() is a
 * one-line call instead of hand-rolling the same four keys everywhere.
 *
 * Also provides a default toWebPush() — since it's built from the exact same toArray() payload,
 * a class only needs to add 'webpush' to its via() array to get real browser push notifications;
 * no per-class push-specific code is needed unless a notification wants a custom push shape.
 */
trait BuildsDatabasePayload
{
    protected function payload(string $title, string $body, ?string $actionUrl = null, string $icon = 'bell'): array
    {
        return [
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'icon' => $icon,
        ];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $data = $this->toArray($notifiable);

        $message = (new WebPushMessage)
            ->title($data['title'])
            ->body($data['body'])
            ->icon('/images/branding/icon.svg')
            ->data(['url' => $data['action_url']])
            // A push notification for a delayed order update shouldn't still be delivered a day
            // later once the browser reconnects — TTL is in seconds (1 hour).
            ->options(['TTL' => 3600]);

        return $message;
    }
}
