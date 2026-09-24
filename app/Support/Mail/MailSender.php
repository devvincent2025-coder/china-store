<?php

namespace App\Support\Mail;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Routes a notification's MailMessage through one of the app's dedicated mailbox logins (each of
 * notify@/hello@/support@ is a real mailbox with its own SMTP credentials on mail.chinainnigeria.com,
 * not just a different "From" header on one shared account) — see config/mail.php's 'hello' and
 * 'support' mailer entries. Everything that doesn't call this sends via the default 'smtp' mailer,
 * i.e. as notify@.
 */
class MailSender
{
    public static function hello(MailMessage $message): MailMessage
    {
        return $message->mailer('hello')->from(config('mail.senders.hello.address'), config('mail.senders.hello.name'));
    }

    public static function support(MailMessage $message): MailMessage
    {
        return $message->mailer('support')->from(config('mail.senders.support.address'), config('mail.senders.support.name'));
    }
}
