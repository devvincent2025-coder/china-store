<?php

namespace App\Support\Notifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a notification with each of its channels isolated from the others' failures, and always
 * immediately (never queued) — for the rare, high-stakes, admin-triggered notices (KYC approved/
 * rejected) where a vendor must actually be told, one way or another, regardless of any other
 * channel's problems or whether a queue worker is even running.
 *
 * Why this exists rather than just calling $notifiable->notify($notification): Laravel's own
 * multi-channel dispatch does NOT isolate channels from each other. Every channel in via() —
 * mail, database, webpush, whatever — runs through Illuminate\Queue\SyncQueue::executeJob(), which
 * re-throws after marking a failed channel failed (see its handleException()). Since a
 * ShouldQueue notification's channels are dispatched as SEPARATE jobs in a single foreach (see
 * Illuminate\Notifications\NotificationSender::queueNotification()), the first channel to throw
 * — most often mail, if SMTP is down or misconfigured — aborts that foreach before the later
 * channels (e.g. database, the in-app notification) are ever even attempted. A vendor whose email
 * happened to bounce would then also silently never see their in-app notification or push, for a
 * completely unrelated reason. Sending each channel as its own top-level call sidesteps this
 * entirely: one channel failing has no bearing on whether the others are attempted.
 */
class NotificationDispatcher
{
    public static function sendIsolated(mixed $notifiable, mixed $notification): void
    {
        foreach ((array) $notification->via($notifiable) as $channel) {
            try {
                Notification::sendNow($notifiable, clone $notification, [$channel]);
            } catch (\Throwable $e) {
                Log::error('Notification channel failed', [
                    'notification' => $notification::class,
                    'channel' => $channel,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
