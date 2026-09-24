<?php

namespace App\Notifications;

use App\Models\SupportTicketMessage;
use App\Notifications\Concerns\BuildsDatabasePayload;
use App\Support\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketReplied extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly SupportTicketMessage $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        $ticket = $this->message->ticket;

        return $this->payload(
            title: 'Support ticket reply',
            body: "Reply to \"{$ticket->subject}\": ".str($this->message->body)->limit(100),
            actionUrl: route('support.show', $ticket->id),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->message->ticket;

        $message = (new MailMessage)
            ->subject("Reply to your support ticket: {$ticket->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line('Our support team replied to your ticket:')
            ->line('"'.$this->message->body.'"')
            ->action('View Ticket', route('support.show', $ticket->id));

        return MailSender::support($message);
    }
}
