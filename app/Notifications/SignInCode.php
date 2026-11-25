<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The 6-digit two-step sign-in code. Sent right away (not queued) so the person isn't kept waiting. */
class SignInCode extends Notification
{
    public function __construct(public string $code, public string $school) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Your {$this->school} sign-in code: {$this->code}")
            ->line('Use this code to finish signing in:')
            ->line("**{$this->code}**")
            ->line('It expires in 10 minutes. If you did not try to sign in, change your password and tell the school office.');
    }
}
