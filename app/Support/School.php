<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The single place that answers "how is this school set up?" – deployment mode,
 * feature flags and setup state. Read mode/flags only through this class.
 */
class School
{
    public const HOSTED = 'hosted';

    public const LAN = 'lan';

    /** Features whose availability depends on the deployment mode. */
    private const BY_MODE = [
        'student_accounts' => [self::HOSTED => true, self::LAN => false],
        'parent_accounts' => [self::HOSTED => true, self::LAN => false],
        'online_application' => [self::HOSTED => true, self::LAN => false],
        'email_notifications' => [self::HOSTED => true, self::LAN => false],
        'student_reminders' => [self::HOSTED => true, self::LAN => false],
        'configurable_message_retention' => [self::HOSTED => true, self::LAN => false],
    ];

    public static function mode(): string
    {
        // One source of truth: the mode chosen in the installer (SMARTSCHOOL_MODE in .env),
        // unless the Superadmin later changed it in settings.
        $m = Setting::get('deployment_mode') ?: config('smartschool.mode');

        return in_array($m, [self::HOSTED, self::LAN], true) ? $m : self::HOSTED;
    }

    public static function isLan(): bool
    {
        return self::mode() === self::LAN;
    }

    public static function isHosted(): bool
    {
        return self::mode() === self::HOSTED;
    }

    /** Is a mode-dependent feature on? Unknown features are off. */
    public static function allows(string $feature): bool
    {
        return self::BY_MODE[$feature][self::mode()] ?? false;
    }

    /** Is first-run setup (mode + Superadmin) finished? */
    public static function isSetUp(): bool
    {
        return Setting::get('setup_completed_at') !== null && Setting::get('setup_completed_at') !== '';
    }

    public static function name(): string
    {
        return (string) Setting::get('school_name');
    }
}
