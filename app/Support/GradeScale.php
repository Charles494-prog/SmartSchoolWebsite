<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * How grades are shown. Grades are always computed 0–100 (DepEd); the school's scale maps
 * each 0–100 band to what people see (e.g. 1.00, 4.0, A) and a description.
 * Rows: ['min' => lowest score in the band, 'label' => shown value ('' = the number itself), 'desc' => description].
 */
class GradeScale
{
    public const PRESETS = [
        'deped' => ['DepEd (0–100)', [
            [90, '', 'Outstanding'], [85, '', 'Very Satisfactory'], [80, '', 'Satisfactory'],
            [75, '', 'Fairly Satisfactory'], [0, '', 'Did Not Meet Expectations'],
        ]],
        'college' => ['1.00 – 5.00 (1.00 highest)', [
            [97, '1.00', 'Excellent'], [94, '1.25', 'Excellent'], [91, '1.50', 'Very Good'], [88, '1.75', 'Very Good'],
            [85, '2.00', 'Good'], [82, '2.25', 'Good'], [79, '2.50', 'Satisfactory'], [76, '2.75', 'Satisfactory'],
            [75, '3.00', 'Passed'], [0, '5.00', 'Failed'],
        ]],
        'gpa4' => ['4.0 GPA (4.0 highest)', [
            [93, '4.0', 'A'], [90, '3.7', 'A-'], [87, '3.3', 'B+'], [83, '3.0', 'B'], [80, '2.7', 'B-'], [77, '2.3', 'C+'],
            [73, '2.0', 'C'], [70, '1.7', 'C-'], [67, '1.3', 'D+'], [65, '1.0', 'D'], [0, '0.0', 'F'],
        ]],
        'letter' => ['Letter grades (A – F)', [
            [90, 'A', 'Excellent'], [80, 'B', 'Good'], [70, 'C', 'Fair'], [60, 'D', 'Poor'], [0, 'F', 'Failed'],
        ]],
    ];

    /** number = only 0–100; scale = only the scale value; both = "1.25 (95)". */
    public const SHOW = ['number' => 'Number only (e.g. 95)', 'scale' => 'Scale only (e.g. 1.25)', 'both' => 'Both (e.g. 1.25 · 95)'];

    /** @return list<array{min: float, label: string, desc: string}> highest band first */
    public static function rows(): array
    {
        try {
            $rows = json_decode((string) Setting::get('grade_scale'), true);
        } catch (\Throwable) {
            $rows = null; // no database (e.g. pure unit tests): fall back to DepEd
        }
        if (! is_array($rows) || $rows === []) {
            $rows = self::preset('deped');
        }

        return collect($rows)->map(fn ($r) => ['min' => (float) $r['min'], 'label' => (string) ($r['label'] ?? ''), 'desc' => (string) ($r['desc'] ?? '')])
            ->sortByDesc('min')->values()->all();
    }

    public static function preset(string $key): array
    {
        return array_map(fn ($r) => ['min' => $r[0], 'label' => $r[1], 'desc' => $r[2]], self::PRESETS[$key][1] ?? self::PRESETS['deped'][1]);
    }

    public static function show(): string
    {
        $s = (string) Setting::get('grade_scale_show');

        return isset(self::SHOW[$s]) ? $s : 'number';
    }

    public static function band(?float $grade): ?array
    {
        if ($grade === null) {
            return null;
        }
        foreach (self::rows() as $r) {
            if ($grade >= $r['min']) {
                return $r;
            }
        }

        return null;
    }

    public static function descriptor(?float $grade): string
    {
        return self::band($grade)['desc'] ?? '';
    }

    /** The scale value alone (falls back to the number when the band has no label). */
    public static function label(?float $grade): string
    {
        if ($grade === null) {
            return '';
        }
        $l = self::band($grade)['label'] ?? '';

        return $l !== '' ? $l : self::num($grade);
    }

    /** What people see for a grade, following the school's "show" choice. */
    public static function format(?float $grade): string
    {
        if ($grade === null) {
            return '';
        }
        $l = self::band($grade)['label'] ?? '';

        return match (true) {
            $l === '' || self::show() === 'number' => self::num($grade),
            self::show() === 'scale' => $l,
            default => $l.' · '.self::num($grade),
        };
    }

    private static function num(float $g): string
    {
        return rtrim(rtrim(number_format($g, 2, '.', ''), '0'), '.');
    }

    /** Validate and store the scale from a form (grade_scale[i][min|label|desc], grade_scale_show). */
    public static function saveFrom(Request $r): void
    {
        if (! $r->has('grade_scale')) {
            return;
        }
        $d = $r->validate([
            'grade_scale' => 'required|array|min:2|max:20',
            'grade_scale.*.min' => 'required|numeric|between:0,100',
            'grade_scale.*.label' => 'nullable|string|max:12',
            'grade_scale.*.desc' => 'nullable|string|max:40',
            'grade_scale_show' => 'required|in:'.implode(',', array_keys(self::SHOW)),
        ], [], ['grade_scale.*.min' => 'lowest score', 'grade_scale.*.label' => 'shown as', 'grade_scale.*.desc' => 'description']);
        $rows = collect($d['grade_scale'])->map(fn ($x) => ['min' => round((float) $x['min'], 2), 'label' => trim((string) ($x['label'] ?? '')), 'desc' => trim((string) ($x['desc'] ?? ''))])
            ->sortByDesc('min')->values();
        if ($rows->pluck('min')->unique()->count() !== $rows->count()) {
            throw ValidationException::withMessages(['grade_scale' => 'Two rows start at the same score. Each row needs its own lowest score.']);
        }
        if ((float) $rows->last()['min'] !== 0.0) {
            throw ValidationException::withMessages(['grade_scale' => 'The last row must start at 0 so every grade has a place on the scale.']);
        }
        Setting::put('grade_scale', $rows->toJson());
        Setting::put('grade_scale_show', $d['grade_scale_show']);
    }
}
