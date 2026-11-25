<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Two-step sign-in: after the password, a 6-digit code is emailed. Needs the Online mode
 * and working email (SMTP), otherwise nobody could sign in.
 */
class TwoStep
{
    public const OPTIONS = ['off' => 'Off', 'staff' => 'Staff only', 'all' => 'Everyone (staff, parents and students)'];

    public static function available(): bool
    {
        return School::allows('email_notifications') && SchoolMail::configured();
    }

    public static function requiredFor(User $u): bool
    {
        $mode = Setting::get('two_step', 'off');
        if ($mode === 'off' || ! self::available()) {
            return false;
        }

        return $mode === 'all' || ! $u->hasRole(Role::STUDENT, Role::PARENT);
    }
}
