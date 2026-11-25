<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AwardNomination;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Top achievers and awards for one section or a whole grade level, for one grading period or the year.
 * Students with a failing grade are left out of every public list (kept confidential).
 */
class Honors
{
    public static function topN(): int
    {
        return max(3, (int) (Setting::get('honors_top_n') ?: 10));
    }

    /** @param Collection<int, Section> $sections */
    public static function rows(Collection $sections, ?int $term): Collection
    {
        return $sections->flatMap(fn ($s) => collect(Grading::rankSection($s, $term))->map(fn ($r) => $r + ['section' => $s]));
    }

    /** Ranked list (ties share a rank), passing students only, cut at the configured top N. */
    public static function top(Collection $rows): Collection
    {
        $list = $rows->whereNotNull('average')->where('failed', false)->sortByDesc('average')->values();
        $rank = 0;
        $prev = null;
        $out = collect();
        foreach ($list as $i => $r) {
            if ($r['average'] !== $prev) {
                $rank = $i + 1;
                $prev = $r['average'];
            }
            if ($rank > self::topN()) {
                break;
            }
            $out->push($r + ['rank' => $rank]);
        }

        return $out;
    }

    /** Each subject's top 3 (passing students only): subject => rows with 'grade' and 'place' (ties share a place). */
    public static function bestPerSubject(Collection $rows): Collection
    {
        $subjects = $rows->flatMap(fn ($r) => array_keys($r['grades']))->unique()->sort()->values();

        return $subjects->mapWithKeys(function ($sub) use ($rows) {
            $ok = $rows->where('failed', false)->filter(fn ($r) => ($r['grades'][$sub] ?? null) !== null)->sortByDesc(fn ($r) => $r['grades'][$sub])->values();
            $out = collect();
            $place = 0;
            $prev = null;
            foreach ($ok as $i => $r) {
                $g = $r['grades'][$sub];
                if ($g !== $prev) {
                    $place = $i + 1;
                    $prev = $g;
                }
                if ($place > 3) {
                    break;
                }
                $out->push($r + ['grade' => $g, 'place' => $place]);
            }

            return [$sub => $out];
        })->filter(fn ($v) => $v->isNotEmpty());
    }

    /** Students with no absence and no late (daily attendance) during the period. */
    public static function perfectAttendance(Collection $sections, ?int $term): Collection
    {
        $out = collect();
        foreach ($sections as $s) {
            $cs = $s->classSubjects()->with('section.schoolYear', 'section.gradeLevel')->first();
            if (! $cs) {
                continue;
            }
            [$from, $to] = $term ? Grading::termRange($cs, $term) : [$s->schoolYear->starts_on->toDateString(), $s->schoolYear->ends_on->toDateString()];
            $recs = AttendanceRecord::whereNull('class_subject_id')->where('section_id', $s->id)->whereBetween('date', [$from, $to])->get()->groupBy('student_id');
            foreach ($s->students()->get() as $st) {
                $r = $recs[$st->id] ?? collect();
                if ($r->isNotEmpty() && $r->whereIn('status', ['absent', 'late'])->isEmpty()) {
                    $out->push(['student' => $st, 'section' => $s, 'days' => $r->count()]);
                }
            }
        }

        return $out->sortBy(fn ($x) => $x['student']->last_name)->values();
    }

    public static function nominations(Collection $sections, ?int $term, string $status): Collection
    {
        $ids = $sections->flatMap(fn ($s) => $s->students()->pluck('students.id'));

        return AwardNomination::with('award', 'student', 'nominator')->whereIn('student_id', $ids)->where('school_year_id', SchoolYear::current()?->id)
            ->where('status', $status)->when($term, fn ($q) => $q->where(fn ($w) => $w->where('term', $term)->orWhereNull('term')))->latest()->get();
    }
}
