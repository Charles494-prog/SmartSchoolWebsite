<?php

namespace App\Support;

use App\Models\Student;
use App\Notifications\GeneralNotice;

/** When a student turned on "send my grades to my parents live", their parents get each new score. */
class LiveReports
{
    public static function send(?Student $student, string $what, string $score): void
    {
        if (! $student || ! School::allows('parent_accounts') || ! $student->user?->pref('live_reports', false)) {
            return;
        }
        foreach ($student->guardians()->with('user')->get()->pluck('user')->filter() as $parent) {
            if ($parent->pref('notify_grades', true)) {
                $parent->notify(new GeneralNotice('New score for '.$student->first_name, $what.': '.$score, '/portal/grades'));
            }
        }
    }
}
