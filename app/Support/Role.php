<?php

namespace App\Support;

use App\Models\User;

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

    public const BOOKSTORE = 'bookstore';

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
        self::BOOKSTORE => 'Bookstore',
        self::STUDENT => 'Student',
        self::PARENT => 'Parent / Guardian',
    ];

    /** Roles that work for the school (not students or parents). */
    public static function staff(): array
    {
        return array_values(array_diff(array_keys(self::LABELS), [self::STUDENT, self::PARENT]));
    }

    /** Plain-language descriptions shown when giving someone a login. */
    public const DESCRIPTIONS = [
        self::TEACHER => 'Teacher – own classes, grades, assignments and class QR scanning',
        self::REGISTRAR => 'Registrar – enrollment, students, sections, schedules, report cards',
        self::CASHIER => 'Cashier / Accounting – fees, payments and receipts',
        self::NURSE => 'School nurse / health – clinic visits (private health records)',
        self::GUIDANCE => 'Guidance counselor – counseling notes (confidential)',
        self::LIBRARIAN => 'Librarian – books and borrowing',
        self::HR => 'HR / Payroll – employees and payroll',
        self::GUARD => 'Security guard – gate QR scanner only (in and out)',
        self::BOOKSTORE => 'Bookstore – posts items, approves and releases orders',
        self::ADMIN => 'Administrator – everything, including user accounts and settings',
    ];

    /** Staff roles this user may give out (only an admin can create another admin). */
    public static function staffChoices(?User $by): array
    {
        $roles = self::DESCRIPTIONS;
        if (! $by?->isAdmin()) {
            unset($roles[self::ADMIN]);
        }

        return $roles;
    }

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? ucfirst($role);
    }
}
