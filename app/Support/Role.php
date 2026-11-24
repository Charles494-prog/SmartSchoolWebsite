<?php

namespace App\Support;

final class Role
{
    public const ADMIN = 'admin';

    public const REGISTRAR = 'registrar';

    public const CASHIER = 'cashier';

    public const TEACHER = 'teacher';

    public const LIBRARIAN = 'librarian';

    public const NURSE = 'nurse';

    public const GUIDANCE = 'guidance';

    public const HR = 'hr';

    public const GUARD = 'guard';

    public const STUDENT = 'student';

    public const PARENT = 'parent';

    public const LABELS = [
        self::ADMIN => 'Administrator',
        self::REGISTRAR => 'Registrar',
        self::CASHIER => 'Cashier / Accounting',
        self::TEACHER => 'Teacher',
        self::LIBRARIAN => 'Librarian',
        self::NURSE => 'School Nurse',
        self::GUIDANCE => 'Guidance Counselor',
        self::HR => 'HR / Payroll',
        self::GUARD => 'Security guard (gate)',
        self::STUDENT => 'Student',
        self::PARENT => 'Parent / Guardian',
    ];

    /** Roles that work for the school (not students or parents). */
    public static function staff(): array
    {
        return array_values(array_diff(array_keys(self::LABELS), [self::STUDENT, self::PARENT]));
    }

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? ucfirst($role);
    }
}
