<?php

namespace App\Support;

use App\Models\ClassSubject;

/** Look of class cards: one stable colour per subject and the classwork post types. */
class Classwork
{
    private const COLORS = ['from-indigo-600 to-violet-600', 'from-emerald-600 to-teal-600', 'from-sky-600 to-blue-600', 'from-amber-500 to-orange-600', 'from-rose-600 to-pink-600', 'from-fuchsia-600 to-purple-600', 'from-teal-600 to-cyan-600', 'from-slate-700 to-slate-900'];

    public const TYPES = [
        'assignment' => ['Assignment', 'clipboard', 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300'],
        'project' => ['Project', 'briefcase', 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'],
        'activity' => ['Activity', 'pencil', 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'],
    ];

    public static function color(ClassSubject $cs): string
    {
        return self::COLORS[crc32($cs->subject->name) % count(self::COLORS)];
    }
}
