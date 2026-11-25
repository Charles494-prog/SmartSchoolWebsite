<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/** Branded sign-in code email: school logo, name and accent colour. */
class SignInCodeMail extends Mailable
{
    public function __construct(public string $code, public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your '.Setting::get('school_name').' sign-in code');
    }

    public function content(): Content
    {
        $logo = Setting::get('logo_path');

        return new Content(view: 'emails.sign-in-code', text: 'emails.sign-in-code-text', with: [
            'school' => (string) Setting::get('school_name'),
            'address' => (string) Setting::get('school_address'),
            'logoPath' => $logo && Storage::disk('local')->exists($logo) ? Storage::disk('local')->path($logo) : null,
            'accent' => '#4f46e5',
        ]);
    }
}
