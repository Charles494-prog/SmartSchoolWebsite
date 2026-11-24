<?php

namespace App\Services;

use App\Models\FeeItem;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Setting;

/**
 * Tuition setup from School settings: public school (no tuition) or private school
 * (one tuition for every grade level, or a different tuition per level).
 * Writes the "Tuition fee" rows of the current school year; other fees stay on the Fees page.
 */
class Tuition
{
    public const NAME = 'Tuition fee';

    /** @return array<int, float> grade_level_id => amount for the current school year */
    public static function current(): array
    {
        $sy = SchoolYear::current();
        if (! $sy) {
            return [];
        }

        return FeeItem::where('school_year_id', $sy->id)->where('category', 'tuition')->whereNotNull('grade_level_id')
            ->pluck('amount', 'grade_level_id')->map(fn ($a) => (float) $a)->all();
    }

    /** @param array<int|string, mixed> $perLevel */
    public static function save(string $type, string $mode, ?float $all, array $perLevel): void
    {
        Setting::put('school_type', $type);
        Setting::put('tuition_mode', $mode);
        $sy = SchoolYear::current();
        if (! $sy) {
            return;
        }
        foreach (GradeLevel::orderBy('sort_order')->pluck('id') as $levelId) {
            $amount = match (true) {
                $type === 'public' => 0.0,
                $mode === 'same' => (float) $all,
                default => (float) ($perLevel[$levelId] ?? 0),
            };
            $row = FeeItem::where(['school_year_id' => $sy->id, 'grade_level_id' => $levelId, 'category' => 'tuition'])->first();
            if ($row) {
                $row->update(['amount' => $amount]);
            } elseif ($amount > 0) {
                FeeItem::create(['school_year_id' => $sy->id, 'grade_level_id' => $levelId, 'category' => 'tuition', 'name' => self::NAME, 'amount' => $amount]);
            }
        }
    }
}
