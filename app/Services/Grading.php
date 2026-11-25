<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Support\GradeScale;

/**
 * DepEd K-12 grading (DepEd Order No. 8, s. 2015), configurable per subject:
 *   component % = total score / total highest possible score × 100
 *   initial grade = Σ component % × weight
 *   quarterly grade = transmuted initial grade (can be turned off in Settings)
 */
class Grading
{
    /** Preset weights (ww, pt, qa) per subject group. */
    public const PRESETS = [
        'Languages, AP, EsP (Gr 1–10)' => [30, 50, 20],
        'Science, Math (Gr 1–10)' => [40, 40, 20],
        'MAPEH, EPP/TLE (Gr 1–10)' => [20, 60, 20],
        'SHS Core subjects' => [25, 50, 25],
        'SHS Academic track' => [25, 45, 30],
        'SHS Work immersion / Research' => [35, 40, 25],
        'SHS TVL / Sports / Arts' => [20, 60, 20],
    ];

    /** DepEd transmutation table as a formula. */
    public static function transmute(float $initial): int
    {
        $initial = max(0, min(100, $initial));
        if ($initial >= 60) {
            return (int) min(100, 75 + floor(round(($initial - 60) / 1.6, 6)));
        }

        return (int) (60 + floor(round($initial / 4, 6)));
    }

    /**
     * @param  array<string, array{0: float, 1: float}>  $totals  component => [score total, highest possible total]
     * @param  array<string, int>  $weights  component => weight (percent)
     */
    public static function initialGrade(array $totals, array $weights): ?float
    {
        $sum = 0.0;
        $usedWeight = 0;
        foreach ($weights as $c => $w) {
            [$score, $max] = $totals[$c] ?? [0, 0];
            if ($max <= 0) {
                continue;
            }
            $sum += ($score / $max * 100) * $w / 100;
            $usedWeight += $w;
        }
        if ($usedWeight === 0) {
            return null;
        }

        // If a component has no assessments yet, scale by the weights that exist.
        return round($sum * 100 / $usedWeight, 2);
    }

    public static function quarterly(?float $initial): ?int
    {
        if ($initial === null) {
            return null;
        }

        return Setting::get('use_transmutation') === '1' ? self::transmute($initial) : (int) round($initial);
    }

    public static function descriptor(?float $grade): string
    {
        return GradeScale::descriptor($grade);
    }

    public static function honor(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        return match (true) {
            $average >= (float) Setting::get('honors_with_highest') => 'With Highest Honors',
            $average >= (float) Setting::get('honors_with_high') => 'With High Honors',
            $average >= (float) Setting::get('honors_with') => 'With Honors',
            default => null,
        };
    }

    /**
     * Grade sheet for a class and term: per student the component totals, initial and quarterly grade.
     *
     * @return array<int, array{student: Student, totals: array, initial: ?float, quarterly: ?int}>
     */
    public static function sheet(ClassSubject $cs, int $term): array
    {
        $students = $cs->section->students()->get();
        $assessments = $cs->assessments()->where('term', $term)->get();
        $scores = Score::whereIn('assessment_id', $assessments->pluck('id'))->get()->groupBy('student_id');
        $weights = $cs->weights();
        $att = $weights['at'] > 0 ? self::attendanceTotals($cs, $term) : [];
        $rows = [];
        foreach ($students as $s) {
            $mine = ($scores[$s->id] ?? collect())->keyBy('assessment_id');
            $totals = [];
            foreach ($assessments as $a) {
                $totals[$a->component] ??= [0.0, 0.0];
                $totals[$a->component][0] += (float) ($mine[$a->id]->score ?? 0);
                $totals[$a->component][1] += (float) $a->max_score;
            }
            if (isset($att[$s->id])) {
                $totals['at'] = $att[$s->id];
            }
            $initial = self::initialGrade($totals, $weights);
            $rows[$s->id] = ['student' => $s, 'totals' => $totals, 'initial' => $initial, 'quarterly' => self::quarterly($initial)];
        }

        return $rows;
    }

    /** Quarterly grades of one student in one class: term => grade. */
    public static function studentTerms(ClassSubject $cs, Student $s): array
    {
        $out = [];
        $terms = $cs->section->gradeLevel->terms ?: 4;
        $all = $cs->relationLoaded('assessments') ? $cs->assessments : $cs->load('assessments')->assessments;
        $assessments = $all->groupBy('term');
        $scores = $all->isEmpty() ? collect() : Score::where('student_id', $s->id)->whereIn('assessment_id', $all->pluck('id'))->get()->keyBy('assessment_id');
        for ($t = 1; $t <= $terms; $t++) {
            $totals = [];
            foreach ($assessments[$t] ?? [] as $a) {
                $totals[$a->component] ??= [0.0, 0.0];
                $totals[$a->component][0] += (float) ($scores[$a->id]->score ?? 0);
                $totals[$a->component][1] += (float) $a->max_score;
            }
            $w = $cs->weights();
            if ($w['at'] > 0 && ($a = self::attendanceTotalsMemo($cs, $t)[$s->id] ?? null)) {
                $totals['at'] = $a;
            }
            $out[$t] = self::quarterly(self::initialGrade($totals, $w));
        }

        return $out;
    }

    /** attendanceTotals() computed once per class and term while this request runs. */
    private static function attendanceTotalsMemo(ClassSubject $cs, int $t): array
    {
        static $memo;
        $memo ??= new \WeakMap;
        $m = $memo[$cs] ?? [];
        if (! array_key_exists($t, $m)) {
            $m[$t] = self::attendanceTotals($cs, $t);
            $memo[$cs] = $m;
        }

        return $m[$t];
    }

    /** Date range of a grading period: the school year split into equal parts. */
    public static function termRange(ClassSubject $cs, int $term): array
    {
        $cs->loadMissing('section.schoolYear', 'section.gradeLevel');
        $sy = $cs->section->schoolYear;
        $n = max(1, $cs->section->gradeLevel->terms ?: 4);
        $days = $sy->starts_on->diffInDays($sy->ends_on) + 1;
        $from = $sy->starts_on->copy()->addDays((int) floor($days * ($term - 1) / $n));
        $to = $sy->starts_on->copy()->addDays((int) floor($days * $term / $n) - 1);

        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * Attendance as a grade component: [present days, days recorded] per student for this class and term.
     * Present and excused count fully, late counts half. Uses this subject's class attendance; if the teacher
     * never scanned in class, the section's daily (gate) attendance is used instead.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function attendanceTotals(ClassSubject $cs, int $term): array
    {
        [$from, $to] = self::termRange($cs, $term);
        $q = AttendanceRecord::whereBetween('date', [$from, $to]);
        $rows = (clone $q)->where('class_subject_id', $cs->id)->get();
        if ($rows->isEmpty()) {
            $rows = (clone $q)->whereNull('class_subject_id')->where('section_id', $cs->section_id)->get();
        }
        $points = ['present' => 1, 'excused' => 1, 'late' => 0.5, 'absent' => 0];
        $out = [];
        foreach ($rows->groupBy('student_id') as $sid => $recs) {
            $out[$sid] = [(float) $recs->sum(fn ($r) => $points[$r->status] ?? 0), (float) $recs->count()];
        }

        return $out;
    }

    /** Year average so far: the average of the grading periods that already have a grade (live, before Q3/Q4). */
    public static function runningFinal(array $terms): ?float
    {
        $vals = array_values(array_filter($terms, fn ($g) => $g !== null));

        return $vals === [] ? null : round(array_sum($vals) / count($vals), 2);
    }

    public static function finalGrade(array $terms): ?float
    {
        $vals = array_values(array_filter($terms, fn ($g) => $g !== null));
        if ($vals === [] || count($vals) < count($terms)) {
            return null; // final grade only when every term has a grade
        }

        return round(array_sum($vals) / count($vals));
    }

    /**
     * Report card data (SF9) for an enrollment.
     *
     * @return array{subjects: array, general_average: ?float, honor: ?string}
     */
    public static function reportCard(Enrollment $e, $classSubjects = null): array
    {
        $subjects = [];
        if ($e->section) {
            $classSubjects ??= $e->section->classSubjects()->with(['subject', 'section.gradeLevel', 'assessments'])->get();
            foreach ($classSubjects->sortBy('subject.name') as $cs) {
                $terms = self::studentTerms($cs, $e->student);
                $final = self::finalGrade($terms);
                $subjects[] = ['name' => $cs->subject->name, 'terms' => $terms, 'final' => $final, 'remark' => $final === null ? '' : ($final >= (float) Setting::get('passing_grade') ? 'Passed' : 'Failed')];
            }
        }
        $finals = array_filter(array_column($subjects, 'final'), fn ($g) => $g !== null);
        $ga = ($finals !== [] && count($finals) === count($subjects)) ? round(array_sum($finals) / count($finals), 2) : null;

        return ['subjects' => $subjects, 'general_average' => $ga, 'honor' => self::honor($ga)];
    }

    /** Recompute and store general averages for a section; returns ranking (best first). */
    /**
     * Ranking of a section, best first. Without $term: by general average (whole year, stored on the enrollment).
     * With $term: by the average of that grading period's subject grades – so honors show during the year too.
     */
    public static function rankSection(Section $section, ?int $term = null): array
    {
        $rows = [];
        $classSubjects = $section->classSubjects()->with(['subject', 'section.gradeLevel', 'assessments', 'submissions'])->get(); // once per section, not per student
        // Periods each subject already submitted/approved – an unfinished period would pull the live year average down.
        $locked = $classSubjects->mapWithKeys(fn ($cs) => [$cs->subject->name => $cs->submissions->whereIn('status', ['submitted', 'approved'])->pluck('term')->map(fn ($t) => (int) $t)->all()])->all();
        foreach ($section->enrollments()->where('status', 'enrolled')->with('student')->get() as $e) {
            $e->setRelation('section', $section);
            $rc = self::reportCard($e, $classSubjects);
            $grades = [];
            foreach ($rc['subjects'] as $sub) {
                // Whole year is live: average of the periods already submitted so far (official final still needs every period).
                $grades[$sub['name']] = $term === null ? self::runningFinal(array_intersect_key($sub['terms'], array_flip($locked[$sub['name']] ?? []))) : ($sub['terms'][$term] ?? null);
            }
            if ($term === null) {
                $e->updateQuietly(['general_average' => $rc['general_average']]);
                $avg = $grades !== [] && ! in_array(null, $grades, true) ? round(array_sum($grades) / count($grades), 2) : null;
            } else {
                $avg = $grades !== [] && ! in_array(null, $grades, true) ? round(array_sum($grades) / count($grades), 2) : null;
            }
            $pass = (float) (Setting::get('passing_grade') ?: 75);
            // 'failed' rows are never shown on public lists – kept private for the adviser and parents.
            $failed = collect($grades)->filter(fn ($g) => $g !== null && $g < $pass)->isNotEmpty();
            $rows[] = ['enrollment' => $e, 'average' => $avg, 'honor' => $failed ? null : self::honor($avg), 'failed' => $failed, 'grades' => $grades];
        }
        usort($rows, fn ($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1));

        return $rows;
    }

    public static function componentLabel(string $c): string
    {
        return Assessment::COMPONENTS[$c] ?? ($c === 'at' ? 'Attendance' : $c);
    }
}
