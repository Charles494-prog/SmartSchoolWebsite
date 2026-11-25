<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Class cards: colours, post types and how a post counts toward the grade. */
class Classwork
{
    public const COLORS = [
        'indigo' => 'from-indigo-600 to-violet-600', 'emerald' => 'from-emerald-600 to-teal-600', 'sky' => 'from-sky-600 to-blue-600',
        'amber' => 'from-amber-500 to-orange-600', 'rose' => 'from-rose-600 to-pink-600', 'fuchsia' => 'from-fuchsia-600 to-purple-600',
        'teal' => 'from-teal-600 to-cyan-600', 'slate' => 'from-slate-700 to-slate-900',
    ];

    /** label, icon, badge classes, graded? (has a due date and points) */
    public const TYPES = [
        'announcement' => ['Announcement', 'megaphone', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300', false],
        'material' => ['Document', 'file', 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200', false],
        'assignment' => ['Assignment', 'clipboard', 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300', true],
        'quiz' => ['Quiz', 'check-square', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300', true],
        'project' => ['Project', 'briefcase', 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300', true],
        'activity' => ['Activity', 'pencil', 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300', true],
    ];

    public const COMPONENTS = ['ww' => 'Quizzes / written work', 'pt' => 'Projects / performance tasks', 'qa' => 'Exams / summative'];

    public static function gradable(string $type): bool
    {
        return self::TYPES[$type][3] ?? false;
    }

    /** Colour key: the viewer's own choice, else the teacher's, else one picked from the subject name. */
    public static function colorKey(ClassSubject $cs, ?User $viewer = null): string
    {
        $own = $viewer ? DB::table('class_card_prefs')->where('user_id', $viewer->id)->where('class_subject_id', $cs->id)->value('color') : null;
        $keys = array_keys(self::COLORS);

        return $own ?: ($cs->card_color ?: $keys[crc32($cs->subject->name) % count($keys)]);
    }

    public static function color(ClassSubject $cs, ?User $viewer = null): string
    {
        return self::COLORS[self::colorKey($cs, $viewer)] ?? self::COLORS['indigo'];
    }

    /** "Counts toward Quizzes (30%) · Q2" or "Not included in the grade". */
    public static function countsToward(Assignment $a): string
    {
        if (! $a->component) {
            return 'Not included in the grade';
        }
        $w = $a->classSubject->weights()[$a->component] ?? null;
        $terms = $a->classSubject->section->gradeLevel->terms ?: 4;

        return 'Counts toward '.self::COMPONENTS[$a->component].($w !== null ? ' ('.$w.'%)' : '').' · '.School::term((int) $a->term, $terms);
    }
}
