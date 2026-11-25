<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Support\ParentAccess;
use App\Support\Role;
use Illuminate\Support\Collection;

/**
 * Who may chat with whom.
 *  - Students: their own teachers, admin, cashier, librarian, nurse. Never another student.
 *  - Parents: their child's teachers, admin, cashier, nurse.
 *  - Teachers: their own students (and those students' parents), all staff.
 *  - Other staff: all staff, plus students/parents for their service (cashier, librarian, nurse; admin: everyone).
 */
class Chat
{
    private const STUDENT_STAFF = [Role::ADMIN, Role::CASHIER, Role::LIBRARIAN, Role::NURSE];

    private const PARENT_STAFF = [Role::ADMIN, Role::CASHIER, Role::NURSE];

    /** Teacher ids of a set of students' current sections. */
    private static function teachersOf(Collection $students): Collection
    {
        $ids = collect();
        foreach ($students as $s) {
            $sec = $s->currentEnrollment()?->section;
            if ($sec) {
                $ids = $ids->merge($sec->classSubjects()->pluck('teacher_id'))->push($sec->adviser_id);
            }
        }

        return $ids->filter()->unique();
    }

    public static function allowed(User $a, User $b): bool
    {
        if ($a->id === $b->id || ! $a->is_active || ! $b->is_active) {
            return false;
        }

        return self::oneWay($a, $b) || self::oneWay($b, $a);
    }

    private static function oneWay(User $a, User $b): bool
    {
        if ($a->role === Role::STUDENT) {
            if ($b->role === Role::STUDENT || $b->role === Role::PARENT) {
                return false;
            }
            if (in_array($b->role, self::STUDENT_STAFF, true)) {
                return true;
            }

            return $b->role === Role::TEACHER && self::teachersOf(Student::where('user_id', $a->id)->get())->contains($b->id);
        }
        if ($a->role === Role::PARENT) {
            if (in_array($b->role, self::PARENT_STAFF, true)) {
                return true;
            }

            // Teachers only when the student allowed "Teachers can message them" for this parent.
            return $b->role === Role::TEACHER && self::teachersOf(self::contactableChildren($a))->contains($b->id);
        }
        // Staff ↔ staff is always fine.
        if ($a->isStaff() && $b->isStaff()) {
            return true;
        }

        return false;
    }

    private static function contactableChildren(User $parent)
    {
        return $parent->children()->get()->filter(fn ($c) => ParentAccess::allows($parent, $c, 'contact'))->values();
    }

    /** Everyone this user may start a chat with. */
    public static function contacts(User $u): Collection
    {
        $base = User::where('is_active', true)->where('id', '!=', $u->id);
        if ($u->role === Role::STUDENT) {
            $teachers = self::teachersOf(Student::where('user_id', $u->id)->get());

            return $base->where(fn ($q) => $q->whereIn('role', self::STUDENT_STAFF)->orWhereIn('id', $teachers))->orderBy('name')->get();
        }
        if ($u->role === Role::PARENT) {
            $teachers = self::teachersOf(self::contactableChildren($u));

            return $base->where(fn ($q) => $q->whereIn('role', self::PARENT_STAFF)->orWhereIn('id', $teachers))->orderBy('name')->get();
        }
        $q = (clone $base)->whereIn('role', Role::staff());
        $extra = collect();
        if ($u->role === Role::ADMIN) {
            $extra = User::where('is_active', true)->whereIn('role', [Role::STUDENT, Role::PARENT])->pluck('id');
        } elseif ($u->role === Role::TEACHER) {
            $studentIds = Enrollment::whereIn('section_id', $u->teacherSectionIds())->where('status', 'enrolled')->pluck('student_id');
            $extra = Student::whereIn('id', $studentIds)->whereNotNull('user_id')->pluck('user_id')
                ->merge(Guardian::whereHas('students', fn ($s) => $s->whereIn('students.id', $studentIds))->whereNotNull('user_id')->with('user')->get()
                    ->filter(fn ($g) => $g->user && self::contactableChildren($g->user)->whereIn('id', $studentIds)->isNotEmpty())->pluck('user_id'));
        } elseif (in_array($u->role, [Role::CASHIER, Role::NURSE], true)) {
            $extra = User::where('is_active', true)->whereIn('role', [Role::STUDENT, Role::PARENT])->pluck('id');
        } elseif ($u->role === Role::LIBRARIAN) {
            $extra = User::where('is_active', true)->where('role', Role::STUDENT)->pluck('id');
        }

        return $q->orWhere(fn ($w) => $w->whereIn('id', $extra)->where('id', '!=', $u->id))->orderBy('name')->get();
    }
}
