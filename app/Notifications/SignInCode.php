<?php

namespace App\Notifications;

use App\Mail\SignInCodeMail;
use Illuminate\Notifications\Notification;

/** The 6-digit two-step sign-in code. Sent right away (not queued) so the person isn't kept waiting. */
class SignInCode extends Notification
{
    public function __construct(public string $code, public string $school) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SignInCodeMail
    {
        return (new SignInCodeMail($this->code, explode(' ', (string) $notifiable->name)[0]))->to($notifiable->email);
    }
}
