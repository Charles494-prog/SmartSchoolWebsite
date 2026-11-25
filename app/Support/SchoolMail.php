<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Outgoing email (SMTP) configured by the admin in School settings. Only used when the
 * school runs Online; the password is stored encrypted.
 */
class SchoolMail
{
    public const KEYS = ['mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_from_address', 'mail_from_name'];

    public static function configured(): bool
    {
        return filled(Setting::get('mail_host')) && filled(Setting::get('mail_from_address'));
    }

    public static function password(): ?string
    {
        $enc = Setting::get('mail_password');
        if (blank($enc)) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (Throwable) {
            return null; // APP_KEY changed: the admin must type the password again
        }
    }

    /** Use the saved SMTP settings for this request/job. Safe to call before the database exists. */
    public static function apply(): void
    {
        try {
            if (! School::allows('email_notifications') || ! self::configured()) {
                return;
            }
            $enc = Setting::get('mail_encryption');
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => Setting::get('mail_host'),
                'mail.mailers.smtp.port' => (int) (Setting::get('mail_port') ?: 587),
                'mail.mailers.smtp.scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => Setting::get('mail_username') ?: null,
                'mail.mailers.smtp.password' => self::password(),
                'mail.from.address' => Setting::get('mail_from_address'),
                'mail.from.name' => Setting::get('mail_from_name') ?: Setting::get('school_name'),
            ]);
            app()->forgetInstance('mail.manager');
            app()->forgetInstance('mailer');
        } catch (Throwable) {
            // settings table not migrated yet
        }
    }

    public static function save(array $d): void
    {
        foreach (self::KEYS as $k) {
            Setting::put($k, isset($d[$k]) ? trim((string) $d[$k]) : '');
        }
        if (filled($d['mail_password'] ?? null)) {
            Setting::put('mail_password', Crypt::encryptString((string) $d['mail_password']));
        }
        if (! empty($d['mail_password_clear'])) {
            Setting::put('mail_password', '');
        }
    }

    public static function rules(): array
    {
        return [
            'mail_host' => 'nullable|string|max:190', 'mail_port' => 'nullable|integer|between:1,65535', 'mail_encryption' => 'nullable|in:tls,ssl,none',
            'mail_username' => 'nullable|string|max:190', 'mail_password' => 'nullable|string|max:500', 'mail_password_clear' => 'nullable|boolean',
            'mail_from_address' => 'nullable|required_with:mail_host|email|max:190', 'mail_from_name' => 'nullable|string|max:150',
        ];
    }
}
