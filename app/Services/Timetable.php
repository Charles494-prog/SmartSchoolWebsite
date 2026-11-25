<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\ScheduleSlot;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use App\Support\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rooms and timetable rules.
 * - Each grade level owns a block of rooms: Grade N → Room N01 … N10 (Kindergarten → Room 001 …).
 * - A room, a teacher and a section can never be in two places at the same time (checked on every save).
 */
class Timetable
{
    /** School day: 8 one-hour periods with a morning break and a lunch break (11:45 – 1:00). */
    public const PERIODS = [['07:30', '08:30'], ['08:30', '09:30'], ['09:45', '10:45'], ['10:45', '11:45'], ['13:00', '14:00'], ['14:00', '15:00'], ['15:00', '16:00'], ['16:00', '17:00']];

    public const LUNCH = ['11:45', '13:00'];

    public const MAX_ROOMS_PER_GRADE = 10;

    /** "Grade 7" → 7, Kindergarten → 0. */
    public static function gradeNumber(GradeLevel $g): int
    {
        return preg_match('/(\d+)/', $g->name, $m) ? (int) $m[1] : 0;
    }

    public static function roomName(GradeLevel $g, int $k): string
    {
        return 'Room '.(self::gradeNumber($g) === 0 ? '0'.str_pad((string) $k, 2, '0', STR_PAD_LEFT) : self::gradeNumber($g) * 100 + $k);
    }

    /**
     * Create the grade's rooms: one per section this school year (or $count), up to 10. Existing rooms are kept.
     *
     * @return array<string> names of rooms created
     */
    public static function generateRooms(GradeLevel $g, ?int $count = null): array
    {
        $sy = SchoolYear::current();
        $count ??= max(1, Section::where('school_year_id', $sy?->id)->where('grade_level_id', $g->id)->count());
        $count = min(self::MAX_ROOMS_PER_GRADE, max(1, $count));
        $made = [];
        for ($k = 1; $k <= $count; $k++) {
            $room = Room::firstOrNew(['name' => self::roomName($g, $k)]);
            if (! $room->exists) {
                $room->fill(['building' => 'Main building', 'capacity' => 40, 'is_active' => true, 'grade_level_id' => $g->id])->save();
                $made[] = $room->name;
            } elseif (! $room->grade_level_id) {
                $room->update(['grade_level_id' => $g->id]);
            }
        }

        return $made;
    }

    /** Give every section of the year its own home room from its grade's block (creating rooms as needed). */
    public static function assignHomeRooms(SchoolYear $sy): void
    {
        $sections = Section::where('school_year_id', $sy->id)->with('gradeLevel')->orderBy('name')->get()->groupBy('grade_level_id');
        foreach ($sections as $group) {
            $g = $group->first()->gradeLevel;
            self::generateRooms($g, $group->count());
            foreach ($group->values() as $k => $s) {
                $name = self::roomName($g, min($k + 1, self::MAX_ROOMS_PER_GRADE));
                if ($s->room !== $name) {
                    $s->update(['room' => $name]);
                }
            }
        }
    }

    // ------------------------------------------------------------------ conflicts

    /**
     * Every double-booking in the year: same room, same teacher or same section at overlapping times.
     *
     * @return Collection<int, array{type: string, day: int, start: string, end: string, text: string, a: int, b: int}>
     */
    public static function conflicts(?SchoolYear $sy = null): Collection
    {
        $sy ??= SchoolYear::current();
        $slots = ScheduleSlot::with('classSubject.section.gradeLevel', 'classSubject.subject', 'classSubject.teacher')
            ->whereHas('classSubject.section', fn ($q) => $q->where('school_year_id', $sy?->id))->get();
        $out = collect();
        foreach ($slots->groupBy('day_of_week') as $day => $daySlots) {
            $daySlots = $daySlots->sortBy('starts_at')->values();
            $n = $daySlots->count();
            for ($i = 0; $i < $n; $i++) {
                $a = $daySlots[$i];
                for ($j = $i + 1; $j < $n && $daySlots[$j]->starts_at < $a->ends_at; $j++) {
                    $b = $daySlots[$j];
                    $ca = $a->classSubject;
                    $cb = $b->classSubject;
                    $when = ['day' => (int) $day, 'start' => substr($b->starts_at, 0, 5), 'end' => substr(min($a->ends_at, $b->ends_at), 0, 5), 'a' => $a->id, 'b' => $b->id];
                    if ($a->room && $a->room === $b->room) {
                        $out->push($when + ['type' => 'room', 'text' => "{$a->room}: {$ca->section->label()} ({$ca->subject->name}) and {$cb->section->label()} ({$cb->subject->name})"]);
                    }
                    if ($ca->teacher_id && $ca->teacher_id === $cb->teacher_id) {
                        $out->push($when + ['type' => 'teacher', 'text' => "{$ca->teacher->name}: {$ca->section->label()} and {$cb->section->label()}"]);
                    }
                    if ($ca->section_id === $cb->section_id) {
                        $out->push($when + ['type' => 'section', 'text' => "{$ca->section->label()}: {$ca->subject->name} and {$cb->subject->name}"]);
                    }
                }
            }
        }

        return $out;
    }

    /** Why this slot can't be saved, or null when it fits. */
    public static function clashFor(ScheduleSlot $slot, ?int $teacherId = null): ?string
    {
        $class = $slot->classSubject()->with('section')->first();
        if (! $class) {
            return null;
        }
        $teacherId ??= $class->teacher_id;
        $hit = ScheduleSlot::with('classSubject.section.gradeLevel', 'classSubject.subject', 'classSubject.teacher')
            ->where('day_of_week', $slot->day_of_week)->where('starts_at', '<', $slot->ends_at)->where('ends_at', '>', $slot->starts_at)
            ->when($slot->exists, fn ($q) => $q->where('id', '!=', $slot->id))
            ->whereHas('classSubject.section', fn ($q) => $q->where('school_year_id', $class->section->school_year_id))
            ->where(fn ($q) => $q->when($slot->room, fn ($w) => $w->orWhere('room', $slot->room))
                ->orWhereHas('classSubject', fn ($c) => $c->where('section_id', $class->section_id)->when($teacherId, fn ($t) => $t->orWhere('teacher_id', $teacherId))))
            ->first();
        if (! $hit) {
            return null;
        }
        $hc = $hit->classSubject;
        $what = match (true) {
            $hc->section_id === $class->section_id => 'the section already has '.$hc->subject->name,
            $teacherId && $hc->teacher_id === $teacherId => ($hc->teacher?->name ?? 'The teacher').' already teaches '.$hc->section->label(),
            default => $hit->room.' is used by '.$hc->section->label(),
        };

        return (ScheduleSlot::DAYS[$slot->day_of_week] ?? '').' '.ampm($hit->starts_at).'–'.ampm($hit->ends_at).': '.$what.'.';
    }

    /** Throws when the slot would double-book a room, teacher or section. */
    public static function guard(ScheduleSlot $slot): void
    {
        if ($why = self::clashFor($slot)) {
            throw ValidationException::withMessages(['schedule' => 'Not saved – '.$why]);
        }
    }

    // ------------------------------------------------------------------ scheduler

    /**
     * Weekly 1-hour sessions per subject, based on the DepEd MATATAG time allotment
     * (e.g. Grades 4–10: English, Filipino, Math, Science 4 h; Araling Panlipunan 3 h; GMRC/Values 2 h).
     */
    public static function depedHours(int $grade, string $subject): int
    {
        $s = mb_strtolower($subject);
        $band = $grade <= 3 ? 'early' : ($grade <= 10 ? 'mid' : 'shs');
        $table = [
            'early' => ['english' => 3, 'filipino' => 4, 'math' => 4, 'science' => 3, 'social' => 3, 'araling' => 3, 'values' => 2, 'gmrc' => 2, 'mapeh' => 3, 'computer' => 1, 'epp' => 1, 'tle' => 1],
            'mid' => ['english' => 4, 'filipino' => 4, 'math' => 4, 'science' => 4, 'social' => 3, 'araling' => 3, 'values' => 2, 'gmrc' => 2, 'mapeh' => 4, 'computer' => 4, 'epp' => 4, 'tle' => 4],
            'shs' => ['english' => 4, 'filipino' => 4, 'math' => 4, 'science' => 4, 'social' => 3, 'araling' => 3, 'values' => 2, 'gmrc' => 2, 'mapeh' => 2, 'computer' => 4, 'epp' => 4, 'tle' => 4],
        ][$band];
        foreach ($table as $k => $h) {
            if (str_contains($s, $k)) {
                return $h;
            }
        }

        return 3;
    }

    public static function hoursFor(ClassSubject $cs): int
    {
        return max(1, min(5, (int) ($cs->subject->hours_per_week ?: self::depedHours(self::gradeNumber($cs->section->gradeLevel), $cs->subject->name))));
    }

    /**
     * Build a conflict-free Mon–Fri timetable for every section of the year.
     * Each subject meets its weekly hours on different days (Math 4×, Values 2×…), at the same period when possible.
     * Sections are staggered so one teacher isn't needed by every section at the same time.
     * With $reassign (demo data only) a class whose teacher has no free slot moves to the least-loaded free teacher.
     *
     * @return array{0: int, 1: array<string>}
     */
    public static function schedule(SchoolYear $sy, bool $reassign = false): array
    {
        $periods = count(self::PERIODS);
        $days = [1, 2, 3, 4, 5];
        $sections = Section::where('school_year_id', $sy->id)->with('gradeLevel', 'classSubjects.subject')->get()
            ->sortBy(fn ($s) => [$s->gradeLevel->sort_order, $s->name])->values();
        $busy = [];      // teacher_id => ["d-p" => true]
        $load = [];      // teacher_id => hours
        $placed = 0;
        $failed = [];
        $teachers = User::where('role', Role::TEACHER)->where('is_active', true)->orderBy('id')->pluck('id')->all();

        DB::transaction(function () use ($sections, $periods, $days, &$busy, &$load, &$placed, &$failed, $teachers, $reassign) {
            ScheduleSlot::whereIn('class_subject_id', ClassSubject::whereIn('section_id', $sections->pluck('id'))->pluck('id'))->delete();
            foreach ($sections as $si => $section) {
                $used = [];   // "d-p" => true for this section
                $classes = $section->classSubjects->each(fn ($c) => $c->setRelation('section', $section))
                    ->sortByDesc(fn ($c) => self::hoursFor($c))->values();
                foreach ($classes as $i => $cs) {
                    $need = self::hoursFor($cs);
                    $pick = function (?int $t) use ($need, $i, $si, $periods, $days, &$used, &$busy) {
                        // Same period on $need different days first; then any free slots on different days.
                        foreach (range(0, $periods - 1) as $k) {
                            $p = ($i + $si + $k) % $periods;
                            $free = array_values(array_filter($days, fn ($d) => empty($used["$d-$p"]) && (! $t || empty($busy[$t]["$d-$p"]))));
                            if (count($free) >= $need) {
                                $start = ($si + $i) % count($free);
                                $free = array_merge(array_slice($free, $start), array_slice($free, 0, $start));

                                return array_map(fn ($d) => [$d, $p], array_slice($free, 0, $need));
                            }
                        }
                        $out = [];
                        foreach ($days as $d) {
                            foreach (range(0, $periods - 1) as $k) {
                                $p = ($i + $si + $k) % $periods;
                                if (empty($used["$d-$p"]) && (! $t || empty($busy[$t]["$d-$p"]))) {
                                    $out[] = [$d, $p];
                                    break;
                                }
                            }
                            if (count($out) === $need) {
                                return $out;
                            }
                        }

                        return null;
                    };
                    $slots = $pick($cs->teacher_id);
                    if ($slots === null && $reassign) {
                        foreach (collect($teachers)->sortBy(fn ($t) => $load[$t] ?? 0) as $t) {
                            if (($load[$t] ?? 0) + $need <= 30 && ($slots = $pick($t))) {
                                $cs->update(['teacher_id' => $t]);
                                break;
                            }
                        }
                    }
                    if (! $slots) {
                        $failed[] = $section->label().' – '.$cs->subject->name;

                        continue;
                    }
                    foreach ($slots as [$d, $p]) {
                        $used["$d-$p"] = true;
                        if ($cs->teacher_id) {
                            $busy[$cs->teacher_id]["$d-$p"] = true;
                        }
                        ScheduleSlot::withoutEvents(fn () => $cs->slots()->create(['day_of_week' => $d, 'starts_at' => self::PERIODS[$p][0], 'ends_at' => self::PERIODS[$p][1], 'room' => $section->room]));
                    }
                    $load[$cs->teacher_id] = ($load[$cs->teacher_id] ?? 0) + $need;
                    $placed++;
                }
            }
        });

        return [$placed, $failed];
    }
}
