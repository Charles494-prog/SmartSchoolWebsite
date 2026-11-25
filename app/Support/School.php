<?php

namespace App\Support;

use App\Models\GradeLevel;
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
        // Class cards with assignments/projects (Google Classroom style). In LAN mode teachers use the gradebook only.
        'classwork' => [self::HOSTED => true, self::LAN => false],
        // Students pick their own section during an enrollment window (needs students online).
        'student_section_choice' => [self::HOSTED => true, self::LAN => false],
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

    /** Grading period names by how many there are in a year: 4 = quarters, 3 = trimesters, 2 = semesters. */
    public static function periodName(int $count): string
    {
        return match ($count) {
            3 => 'Trimester', 2 => 'Semester', default => 'Quarter'
        };
    }

    /** Short label for one grading period, e.g. Q1, T2, S1. */
    public static function term(int $t, int $count = 4): string
    {
        return self::periodName($count)[0].$t;
    }

    /** The period count most grade levels use (what the admin picked in Settings). */
    public static function periods(): int
    {
        return (int) (GradeLevel::selectRaw('terms, count(*) n')->groupBy('terms')->orderByDesc('n')->value('terms') ?: 4);
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
