<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SectionAllocation;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Puts students into sections.
 * Randomize is seeded (same seed + same students = same result) and balances by capacity, optionally by sex,
 * optionally "snake draft" by general average so every section gets a mix; siblings and named students can be
 * kept together, and named students kept apart. Locked students never move.
 */
class Allocator
{
    /**
     * @param  array<int, array{sex: ?string, ga: ?float, family: ?string}>  $students  enrollment_id => info
     * @param  array<int, int>  $capacity  section_id => seats
     * @param  array<int, int>  $locked  enrollment_id => section_id
     * @param  array{gender?: bool, snake?: bool, siblings?: bool, together?: array<array<int>>, apart?: array<array<int>>}  $opts
     * @return array{plan: array<int, int>, warnings: array<string>}
     */
    public static function randomize(array $students, array $capacity, array $locked, array $opts, int $seed): array
    {
        $rng = new Randomizer(new Mt19937($seed));
        $sections = array_keys($capacity);
        if (! $sections) {
            return ['plan' => [], 'warnings' => ['Add at least one section first.']];
        }
        $ids = array_keys($students);
        sort($ids);
        $ids = $rng->shuffleArray($ids);

        // Units: students who must stay together (siblings, "keep together" lists) move as one.
        $parent = array_combine($ids, $ids);
        $find = function ($x) use (&$parent, &$find) {
            return $parent[$x] === $x ? $x : $parent[$x] = $find($parent[$x]);
        };
        $union = function ($a, $b) use (&$parent, $find) {
            if (isset($parent[$a], $parent[$b])) {
                $parent[$find($a)] = $find($b);
            }
        };
        if (! empty($opts['siblings'])) {
            $byFamily = [];
            foreach ($students as $id => $s) {
                if (! empty($s['family'])) {
                    $byFamily[$s['family']][] = $id;
                }
            }
            foreach ($byFamily as $group) {
                for ($i = 1; $i < count($group); $i++) {
                    $union($group[0], $group[$i]);
                }
            }
        }
        foreach ($opts['together'] ?? [] as $group) {
            $group = array_values(array_filter($group, fn ($x) => isset($students[$x])));
            for ($i = 1; $i < count($group); $i++) {
                $union($group[0], $group[$i]);
            }
        }
        $units = [];
        foreach ($ids as $id) {
            $units[$find($id)][] = $id;
        }
        $apart = [];
        foreach ($opts['apart'] ?? [] as $group) {
            foreach ($group as $a) {
                foreach ($group as $b) {
                    if ($a !== $b) {
                        $apart[$a][$b] = true;
                    }
                }
            }
        }

        $plan = [];
        $count = array_fill_keys($sections, 0);
        $male = array_fill_keys($sections, 0);
        $female = array_fill_keys($sections, 0);
        $warnings = [];
        $place = function (array $unit, int $sec) use (&$plan, &$count, &$male, &$female, $students) {
            foreach ($unit as $id) {
                $plan[$id] = $sec;
                $count[$sec]++;
                $sx = strtolower((string) $students[$id]['sex']);
                $sx === 'male' ? $male[$sec]++ : ($sx === 'female' ? $female[$sec]++ : null);
            }
        };

        // Locked first: their unit goes with them.
        $free = [];
        foreach ($units as $unit) {
            $lockedIn = collect($unit)->map(fn ($id) => $locked[$id] ?? null)->filter(fn ($s) => $s && isset($capacity[$s]))->first();
            $lockedIn ? $place($unit, $lockedIn) : $free[] = $unit;
        }

        // Order: snake draft = strongest first so each section takes turns; otherwise the shuffled order.
        $avg = fn ($u) => collect($u)->map(fn ($id) => $students[$id]['ga'])->filter(fn ($g) => $g !== null)->avg() ?? 0;
        if (! empty($opts['snake'])) {
            usort($free, fn ($a, $b) => $avg($b) <=> $avg($a));
        }
        $n = count($sections);
        $turn = 0;
        foreach ($free as $unit) {
            $size = count($unit);
            $round = intdiv($turn, $n);
            $preferred = $sections[$round % 2 === 0 ? $turn % $n : $n - 1 - ($turn % $n)];
            $turn++;
            $clashes = fn ($sec) => collect($unit)->contains(fn ($id) => collect($apart[$id] ?? [])->keys()->contains(fn ($o) => ($plan[$o] ?? null) === $sec));
            $fits = array_values(array_filter($sections, fn ($s) => $capacity[$s] >= $count[$s] + $size && ! $clashes($s)));
            if (! $fits) {
                $fits = array_values(array_filter($sections, fn ($s) => ! $clashes($s))) ?: $sections;
            }
            $unitMale = collect($unit)->filter(fn ($id) => strtolower((string) $students[$id]['sex']) === 'male')->count();
            $unitFemale = collect($unit)->filter(fn ($id) => strtolower((string) $students[$id]['sex']) === 'female')->count();
            $score = function ($s) use ($count, $capacity, $male, $female, $unitMale, $unitFemale, $opts, $preferred, $size) {
                $load = ($count[$s] + $size) / max(1, $capacity[$s]);
                $gender = ! empty($opts['gender']) ? abs(($male[$s] + $unitMale) - ($female[$s] + $unitFemale)) / 10 : 0;

                return [round($load, 2) + $gender + (! empty($opts['snake']) && $s !== $preferred ? 0.001 : 0), $s === $preferred ? 0 : 1, $s];
            };
            usort($fits, fn ($a, $b) => $score($a) <=> $score($b));
            $place($unit, $fits[0]);
        }
        foreach ($sections as $s) {
            if ($count[$s] > $capacity[$s]) {
                $warnings[] = "over:$s";
            }
        }

        return ['plan' => $plan, 'warnings' => $warnings];
    }

    /** Students of an allocation in the shape randomize() wants. GA = last year's general average when known. */
    public static function studentsFor(SectionAllocation $a, Collection $enrollments): array
    {
        $prev = Enrollment::whereIn('student_id', $enrollments->pluck('student_id'))->where('school_year_id', '!=', $a->school_year_id)
            ->whereNotNull('general_average')->orderBy('enrolled_on')->get()->keyBy('student_id');

        return $enrollments->mapWithKeys(fn ($e) => [$e->id => [
            'sex' => $e->student->sex,
            'ga' => $e->general_average ?? ($prev[$e->student_id] ?? null)?->general_average,
            'family' => $e->student->guardians->sortBy('id')->first()?->id ? 'g'.$e->student->guardians->sortBy('id')->first()->id : null,
        ]])->all();
    }

    /** Per-section meter: count, boys/girls, average grade. */
    public static function stats(array $plan, array $students, array $sectionIds): array
    {
        $out = [];
        foreach ($sectionIds as $s) {
            $mine = array_keys(array_filter($plan, fn ($x) => $x === $s));
            $gas = array_filter(array_map(fn ($id) => $students[$id]['ga'] ?? null, $mine), fn ($g) => $g !== null);
            $out[$s] = ['count' => count($mine),
                'male' => count(array_filter($mine, fn ($id) => strtolower((string) ($students[$id]['sex'] ?? '')) === 'male')),
                'female' => count(array_filter($mine, fn ($id) => strtolower((string) ($students[$id]['sex'] ?? '')) === 'female')),
                'avg' => $gas ? round(array_sum($gas) / count($gas), 1) : null];
        }

        return $out;
    }
}
