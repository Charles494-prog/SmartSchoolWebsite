<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionAllocation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Services\Allocator;
use App\Services\Timetable;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Section allocation wizard: 1 grade + year → 2 sections → 3 method → 4 preview & adjust → 5 confirm.
 * Every commit remembers the previous sections so the last allocation can be undone.
 */
class AllocationController extends Controller
{
    public const STEPS = [1 => 'Grade & year', 2 => 'Sections', 3 => 'Method', 4 => 'Preview', 5 => 'Confirm'];

    public function index()
    {
        $years = SchoolYear::orderByDesc('starts_on')->get();

        return view('allocation.wizard', [
            'step' => 1, 'a' => null, 'years' => $years,
            'levels' => GradeLevel::orderBy('sort_order')->get(),
            'recent' => SectionAllocation::with('gradeLevel', 'schoolYear')->latest()->take(10)->get(),
            'waiting' => Enrollment::whereIn('school_year_id', $years->pluck('id'))->where('status', 'enrolled')->whereNull('section_id')
                ->selectRaw('school_year_id, grade_level_id, count(*) n')->groupBy('school_year_id', 'grade_level_id')->get(),
        ]);
    }

    public function start(Request $request)
    {
        $d = $request->validate(['school_year_id' => 'required|exists:school_years,id', 'grade_level_id' => 'required|exists:grade_levels,id']);
        $a = SectionAllocation::where($d)->whereIn('status', ['draft', 'open'])->latest()->first()
            ?? SectionAllocation::create($d + ['created_by' => $request->user()->id, 'seed' => random_int(1, 999999)]);

        return redirect()->route('allocation.step', [$a, 2]);
    }

    public function step(SectionAllocation $a, int $step)
    {
        abort_unless(isset(self::STEPS[$step]) && $step > 1, 404);
        $sections = $a->sections()->with('adviser')->get();
        $enrollments = $a->enrollments()->get();
        $students = Allocator::studentsFor($a, $enrollments);
        $plan = array_map('intval', $a->plan ?? $enrollments->whereNotNull('section_id')->pluck('section_id', 'id')->all());
        $plan = array_intersect_key($plan, $students);
        $prevStatus = Enrollment::whereIn('student_id', $enrollments->pluck('student_id'))->where('school_year_id', '!=', $a->school_year_id)->latest('enrolled_on')->get()->unique('student_id')->pluck('status', 'student_id');
        $apps = Application::whereIn('student_id', $enrollments->pluck('student_id'))->pluck('reference_no', 'student_id');

        return view('allocation.wizard', [
            'step' => $step, 'a' => $a, 'sections' => $sections, 'enrollments' => $enrollments, 'students' => $students, 'plan' => $plan,
            'stats' => Allocator::stats($plan, $students, $sections->pluck('id')->all()),
            'tags' => $enrollments->mapWithKeys(fn ($e) => [$e->id => array_values(array_filter([
                ($prevStatus[$e->student_id] ?? null) === 'retained' ? 'Retained' : null,
                isset($apps[$e->student_id]) ? 'New · '.$apps[$e->student_id] : null,
                $e->student->transfers()->where('direction', 'in')->exists() ? 'Transferee' : null,
            ]))]),
            'teachers' => User::where('role', Role::TEACHER)->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'rooms' => Room::where('is_active', true)->where(fn ($q) => $q->where('grade_level_id', $a->grade_level_id)->orWhereNull('grade_level_id'))->orderBy('name')->get(),
            'choiceAllowed' => School::allows('student_section_choice'),
        ]);
    }

    // ---- step 2: sections

    public function saveSection(Request $request, SectionAllocation $a, ?Section $section = null)
    {
        abort_if($section && ($section->school_year_id !== $a->school_year_id || $section->grade_level_id !== $a->grade_level_id), 404);
        $d = $request->validate([
            'name' => ['required', 'max:60', Rule::unique('sections')->where('school_year_id', $a->school_year_id)->ignore($section?->id)],
            'capacity' => 'required|integer|min:1|max:'.($request->filled('room') ? (Room::where('name', $request->room)->value('capacity') ?: 500) : 500),
            'adviser_id' => 'nullable|exists:users,id',
            'room' => ['nullable', Rule::exists('rooms', 'name'), Rule::unique('sections', 'room')->where('school_year_id', $a->school_year_id)->ignore($section?->id)],
        ], ['capacity.max' => 'This room only has :max seats.', 'room.unique' => 'Another section already uses this home room.']);
        if ($section) {
            $section->update($d);
        } else {
            $section = Section::create($d + ['school_year_id' => $a->school_year_id, 'grade_level_id' => $a->grade_level_id]);
            foreach (Subject::where('grade_level_id', $a->grade_level_id)->where('is_active', true)->get() as $sub) {
                ClassSubject::firstOrCreate(['section_id' => $section->id, 'subject_id' => $sub->id]);
            }
        }

        return back()->with('ok', 'Section saved.');
    }

    public function generateRooms(SectionAllocation $a)
    {
        $made = Timetable::generateRooms($a->gradeLevel, max(1, $a->sections()->count()));

        return back()->with('ok', $made ? 'Created '.implode(', ', $made).'.' : 'This grade already has a room for each section.');
    }

    // ---- step 3: method

    public function method(Request $request, SectionAllocation $a)
    {
        $d = $request->validate([
            'method' => 'required|in:random,choice,manual',
            'gender' => 'boolean', 'snake' => 'boolean', 'siblings' => 'boolean',
            'together' => 'nullable|string|max:2000', 'apart' => 'nullable|string|max:2000',
            'deadline' => 'nullable|required_if:method,choice|date|after:now',
        ], ['deadline.required_if' => 'Set when choosing closes.', 'deadline.after' => 'The deadline must be in the future.']);
        abort_if($d['method'] === 'choice' && ! School::allows('student_section_choice'), 422, 'Students can choose sections only in online mode.');
        if ($a->sections()->count() === 0) {
            return back()->with('error', 'Add at least one section first.');
        }
        $opts = ['gender' => $request->boolean('gender'), 'snake' => $request->boolean('snake'), 'siblings' => $request->boolean('siblings'),
            'together' => $this->groups($d['together'] ?? '', $a), 'apart' => $this->groups($d['apart'] ?? '', $a),
            'together_text' => $d['together'] ?? '', 'apart_text' => $d['apart'] ?? ''];
        $a->update(['method' => $d['method'], 'options' => $opts, 'deadline' => $d['method'] === 'choice' ? $d['deadline'] : null]);

        if ($d['method'] === 'random') {
            $this->shuffle($a, $a->seed);

            return redirect()->route('allocation.step', [$a, 4]);
        }
        if ($d['method'] === 'manual') {
            $a->update(['plan' => $a->enrollments()->whereNotNull('section_id')->pluck('section_id', 'id')->all()]);

            return redirect()->route('allocation.step', [$a, 4]);
        }
        // Student choice: open the window now; students pick while seats remain.
        $a->update(['status' => 'open', 'previous' => $a->enrollments()->pluck('section_id', 'id')->all()]);
        $users = User::whereIn('id', $a->enrollments()->get()->pluck('student.user_id')->filter())->get();
        Notification::send($users, new GeneralNotice('Choose your section', 'Pick your '.$a->gradeLevel->name.' section before '.$a->deadline->format('M j, Y g:i A').'. First come, first served.', route('portal.section-choice', [], false)));
        AuditLog::record('allocation_open', $a, [], ['deadline' => $a->deadline], 'Section choice window opened');

        return redirect()->route('allocation.step', [$a, 4])->with('ok', 'Students can now choose. Those who don’t choose by the deadline are placed automatically.');
    }

    /** "Ana Cruz, Mark Reyes" per line → enrollment id groups. */
    private function groups(string $text, SectionAllocation $a): array
    {
        $byName = $a->enrollments()->get()->mapWithKeys(fn ($e) => [mb_strtolower($e->student->first_name.' '.$e->student->last_name) => $e->id, mb_strtolower($e->student->full_name) => $e->id]);
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $ids = collect(preg_split('/\s*[,;]\s*/', trim($line)))->map(fn ($n) => $byName[mb_strtolower(trim($n))] ?? null)->filter()->unique()->values()->all();
            if (count($ids) > 1) {
                $out[] = $ids;
            }
        }

        return $out;
    }

    private function shuffle(SectionAllocation $a, int $seed): void
    {
        $enrollments = $a->enrollments()->get();
        $capacity = $a->sections()->pluck('capacity', 'id')->map(fn ($c) => (int) $c)->all();
        $locked = array_intersect_key($a->plan ?? [], array_flip($a->locked ?? []));
        $r = Allocator::randomize(Allocator::studentsFor($a, $enrollments), $capacity, array_map('intval', $locked), $a->options ?? [], $seed);
        $a->update(['plan' => $r['plan'], 'seed' => $seed]);
    }

    // ---- step 4: preview & adjust

    public function reshuffle(SectionAllocation $a)
    {
        abort_unless($a->status === 'draft', 409);
        $this->shuffle($a, random_int(1, 999999));

        return back()->with('ok', 'Reshuffled. Locked students stayed where they were.');
    }

    public function savePlan(Request $request, SectionAllocation $a)
    {
        abort_unless(in_array($a->status, ['draft', 'open'], true), 409);
        $d = $request->validate(['plan' => 'array', 'plan.*' => 'nullable|integer', 'locked' => 'array', 'locked.*' => 'integer']);
        $valid = $a->sections()->pluck('id')->all();
        $ids = $a->enrollments()->pluck('id')->all();
        $plan = collect($d['plan'] ?? [])->only($ids)->filter(fn ($s) => in_array((int) $s, $valid, true))->map(fn ($s) => (int) $s)->all();
        $a->update(['plan' => $plan, 'locked' => array_values(array_intersect(array_map('intval', $d['locked'] ?? []), $ids))]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : redirect()->route('allocation.step', [$a, $request->input('next') ? 5 : 4])->with('ok', 'Saved.');
    }

    // ---- step 5: confirm / undo

    public function commit(SectionAllocation $a)
    {
        abort_unless(in_array($a->status, ['draft', 'open'], true), 409, 'Already done.');
        $plan = $a->plan ?? [];
        if (! $plan) {
            return back()->with('error', 'Nobody is placed yet.');
        }
        DB::transaction(function () use ($a, $plan) {
            $enrollments = $a->enrollments()->lockForUpdate()->get()->keyBy('id');
            if ($a->previous === null) {   // a choice window saved it when it opened
                $a->update(['previous' => $enrollments->map(fn ($e) => $e->section_id)->all()]);
            }
            foreach ($plan as $eid => $sid) {
                if (($e = $enrollments[$eid] ?? null) && $e->section_id !== (int) $sid) {
                    $e->update(['section_id' => (int) $sid]);
                }
            }
            $a->update(['status' => 'committed', 'committed_at' => now()]);
        });
        AuditLog::record('allocation_commit', $a, [], ['method' => $a->method, 'placed' => count($plan), 'seed' => $a->seed], $a->gradeLevel->name.' sections allocated');

        return redirect()->route('allocation.index')->with('ok', count($plan).' students placed in '.$a->gradeLevel->name.' sections.');
    }

    public function undo(SectionAllocation $a)
    {
        abort_unless($a->status === 'committed', 409);
        $latest = SectionAllocation::where('school_year_id', $a->school_year_id)->where('grade_level_id', $a->grade_level_id)->where('status', 'committed')->latest('committed_at')->first();
        abort_unless($latest?->is($a), 409, 'Only the most recent allocation for this grade can be undone.');
        DB::transaction(function () use ($a) {
            foreach (Enrollment::whereIn('id', array_keys($a->previous ?? []))->get() as $e) {
                $old = $a->previous[$e->id] ?? null;
                if ($e->section_id !== $old) {
                    $e->update(['section_id' => $old]);
                }
            }
            $a->update(['status' => 'undone', 'undone_at' => now()]);
        });
        AuditLog::record('allocation_undo', $a, [], [], $a->gradeLevel->name.' allocation undone');

        return back()->with('ok', 'Undone. Students are back in their previous sections.');
    }

    /** Close a student-choice window: anyone who didn't choose is placed automatically (balanced by seats left). */
    public function close(SectionAllocation $a)
    {
        abort_unless($a->status === 'open', 409);
        self::closeWindow($a);

        return redirect()->route('allocation.step', [$a, 4])->with('ok', 'Choosing is closed. Everyone left was placed – check the preview, then confirm.');
    }

    public static function closeWindow(SectionAllocation $a): void
    {
        $enrollments = $a->enrollments()->get();
        $chosen = $enrollments->whereNotNull('section_id')->pluck('section_id', 'id')->map(fn ($s) => (int) $s)->all();
        $capacity = $a->sections()->pluck('capacity', 'id')->map(fn ($c) => (int) $c)->all();
        $r = Allocator::randomize(Allocator::studentsFor($a, $enrollments), $capacity, $chosen, ($a->options ?? []) + ['gender' => true], $a->seed);
        $a->update(['plan' => $r['plan'], 'locked' => array_keys($chosen), 'status' => 'draft']);
        AuditLog::record('allocation_close', $a, [], ['chosen' => count($chosen), 'auto' => count($r['plan']) - count($chosen)], 'Section choice window closed');
    }

    /** After promotion: place every grade of the new year automatically (random, committed + undoable) or open a 7-day choice window. */
    public static function autoAllocate(int $yearId, string $how, int $userId): array
    {
        $out = ['placed' => 0, 'grades' => 0, 'missing' => []];
        $levels = Enrollment::where('school_year_id', $yearId)->where('status', 'enrolled')->whereNull('section_id')->distinct()->pluck('grade_level_id');
        foreach (GradeLevel::whereIn('id', $levels)->orderBy('sort_order')->get() as $g) {
            $a = SectionAllocation::create(['school_year_id' => $yearId, 'grade_level_id' => $g->id, 'method' => $how, 'created_by' => $userId, 'seed' => random_int(1, 999999),
                'options' => ['gender' => true, 'snake' => true, 'siblings' => true]]);
            if ($a->sections()->count() === 0) {
                $out['missing'][] = $g->name;
                $a->delete();

                continue;
            }
            $enrollments = $a->enrollments()->get();
            $a->update(['previous' => $enrollments->pluck('section_id', 'id')->all()]);
            if ($how === 'choice') {
                $a->update(['status' => 'open', 'deadline' => now()->addDays(7)]);
                Notification::send(User::whereIn('id', $enrollments->pluck('student.user_id')->filter())->get(),
                    new GeneralNotice('Choose your section', 'Pick your '.$g->name.' section within 7 days. First come, first served.', route('portal.section-choice', [], false)));
            } else {
                $locked = $enrollments->whereNotNull('section_id')->pluck('section_id', 'id')->map(fn ($x) => (int) $x)->all();
                $r = Allocator::randomize(Allocator::studentsFor($a, $enrollments), $a->sections()->pluck('capacity', 'id')->map(fn ($c) => (int) $c)->all(), $locked, $a->options, $a->seed);
                DB::transaction(function () use ($a, $r, $enrollments) {
                    foreach ($enrollments as $e) {
                        if (isset($r['plan'][$e->id]) && $e->section_id !== $r['plan'][$e->id]) {
                            $e->update(['section_id' => $r['plan'][$e->id]]);
                        }
                    }
                    $a->update(['plan' => $r['plan'], 'status' => 'committed', 'committed_at' => now()]);
                });
                $out['placed'] += count($r['plan']);
            }
            AuditLog::record('allocation_auto', $a, [], ['method' => $how], $g->name.' auto-sectioned after promotion');
            $out['grades']++;
        }

        return $out;
    }

    // ---- students (online only)

    public function choice(Request $request)
    {
        abort_unless(School::allows('student_section_choice'), 404);
        [$e, $a] = $this->myWindow($request);

        return view('allocation.choose', ['e' => $e, 'a' => $a]);
    }

    public function choose(Request $request)
    {
        abort_unless(School::allows('student_section_choice'), 404);
        [$e, $a] = $this->myWindow($request);
        abort_unless($e && $a, 404);
        $sid = (int) $request->validate(['section_id' => 'required|integer'])['section_id'];
        $ok = DB::transaction(function () use ($e, $a, $sid) {
            $s = $a->sections()->whereKey($sid)->lockForUpdate()->first();
            if (! $s || $s->enrollments()->where('status', 'enrolled')->count() >= $s->capacity) {
                return false;
            }
            $e->update(['section_id' => $s->id]);

            return true;
        });

        return back()->with($ok ? 'ok' : 'error', $ok ? 'You’re in! You can still switch while seats remain until the deadline.' : 'That section just filled up. Please pick another.');
    }

    /** The signed-in student's enrollment and its open choice window. */
    private function myWindow(Request $request): array
    {
        $student = Student::where('user_id', $request->user()->id)->first();
        $e = $student?->currentEnrollment();
        $a = $e ? SectionAllocation::where('school_year_id', $e->school_year_id)->where('grade_level_id', $e->grade_level_id)->where('status', 'open')->where('deadline', '>', now())->latest()->first() : null;

        return [$e, $a];
    }
}
