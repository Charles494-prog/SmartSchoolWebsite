<?php

namespace App\Notifications;

use App\Models\AttendanceRecord;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceAlert extends Notification
{
    public function __construct(public AttendanceRecord $record) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    private function text(): string
    {
        $s = $this->record->student;
        $what = $this->record->status === 'late' ? 'arrived LATE ('.ampm($this->record->time_in).')' : 'was marked ABSENT';

        return "{$s->first_name} {$s->last_name} {$what} on ".$this->record->date->format('F j, Y').'.';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(Setting::get('school_name').' – Attendance notice')
            ->greeting('Good day!')
            ->line($this->text())
            ->line('If this is a mistake or there is a reason for the absence, please message the adviser through the parent portal.')
            ->action('Open parent portal', url('/portal'));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Attendance notice', 'body' => $this->text(), 'url' => '/portal'];
    }
}
