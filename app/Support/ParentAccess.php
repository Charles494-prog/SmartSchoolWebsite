<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** What a parent may do for one child. The student chooses this (Personal setup → Parents). */
class ParentAccess
{
    public const LABELS = [
        'grades' => 'See grades', 'attendance' => 'See attendance', 'schedule' => 'See class schedule',
        'classes' => 'See my classes (read only)', 'pay' => 'Pay school fees', 'contact' => 'Teachers can message them',
    ];

    /** @return string[] */
    public static function for(User $parent, Student $student): array
    {
        $rows = DB::table('guardian_student')->join('guardians', 'guardians.id', '=', 'guardian_student.guardian_id')
            ->where('guardians.user_id', $parent->id)->where('guardian_student.student_id', $student->id)->pluck('guardian_student.access');
        if ($rows->isEmpty()) {
            return [];
        }

        return collect($rows)->flatMap(fn ($a) => $a === null ? array_keys(self::LABELS) : (array) json_decode($a, true))->unique()->values()->all();
    }

    public static function allows(User $parent, Student $student, string $what): bool
    {
        return in_array($what, self::for($parent, $student), true);
    }
}
