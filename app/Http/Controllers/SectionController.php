<?php

namespace App\Http\Controllers;

use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\ScheduleSlot;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\User;
use App\Services\Enrollment as EnrollmentService;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $sy = $request->filled('school_year_id') ? SchoolYear::find($request->school_year_id) : SchoolYear::current();
        $all = Section::where('school_year_id', $sy?->id)->with(['gradeLevel', 'adviser'])
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled'), 'classSubjects'])
            ->get()->sortBy(fn ($s) => [$s->gradeLevel->sort_order, $s->name]);

        return view('sections.index', [
            'levelTabs' => $all->groupBy('grade_level_id')->map(fn ($g) => ['name' => $g->first()->gradeLevel->name, 'count' => $g->count()]),
            'level' => $request->query('level'),
            'sy' => $sy,
            'years' => SchoolYear::orderByDesc('starts_on')->pluck('name', 'id'),
            'sections' => $all->when($request->filled('level'), fn ($c) => $c->where('grade_level_id', (int) $request->level)),
            'levels' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'teachers' => User::where('role', Role::TEACHER)->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'rooms' => Room::options(),
            'capacities' => Room::capacities(),
            'unsectioned' => $sy ? Enrollment::where('school_year_id', $sy->id)->whereNull('section_id')->where('status', 'enrolled')->count() : 0,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $section = Section::create($data);
        if ($request->boolean('add_subjects')) {
            foreach (Subject::where('grade_level_id', $section->grade_level_id)->where('is_active', true)->get() as $sub) {
                ClassSubject::firstOrCreate(['section_id' => $section->id, 'subject_id' => $sub->id]);
            }
        }

        return redirect()->route('sections.show', $section)->with('ok', 'Section created. Now assign teachers to each subject and add students.');
    }

    private function validated(Request $request, ?Section $s = null): array
    {
        return $request->validate([
            'name' => ['required', 'max:60', Rule::unique('sections')->where('school_year_id', $request->school_year_id)->ignore($s?->id)],
            'grade_level_id' => 'required|exists:grade_levels,id',
            'school_year_id' => 'required|exists:school_years,id',
            'adviser_id' => 'nullable|exists:users,id',
            'room' => ['nullable', Rule::exists('rooms', 'name')],
            'capacity' => ['required', 'integer', 'min:1', 'max:'.($request->filled('room') ? (Room::where('name', $request->room)->value('capacity') ?: 500) : 500)],
        ], ['capacity.max' => 'This room only has :max seats.', 'room.exists' => 'Choose a room from the list (add rooms in Rooms).']);
    }

    public function update(Request $request, Section $section)
    {
        $section->update($this->validated($request, $section));

        return back()->with('ok', 'Section updated.');
    }

    public function destroy(Section $section)
    {
        if ($section->enrollments()->exists()) {
            return back()->with('error', 'Move the students to another section first.');
        }
        $section->delete();

        return redirect()->route('sections.index')->with('ok', 'Section deleted.');
    }

    public function show(Section $section)
    {
        $section->load(['gradeLevel', 'schoolYear', 'adviser', 'classSubjects.subject', 'classSubjects.teacher', 'classSubjects.slots']);

        $sy = $section->school_year_id;
        $teacherIds = $section->classSubjects->pluck('teacher_id')->filter()->unique();
        $busy = ScheduleSlot::with('classSubject.subject', 'classSubject.section.gradeLevel')
            ->whereHas('classSubject.section', fn ($q) => $q->where('school_year_id', $sy)->where('id', '!=', $section->id))
            ->where(fn ($q) => $q->whereHas('classSubject', fn ($c) => $c->whereIn('teacher_id', $teacherIds))->when($section->room, fn ($w) => $w->orWhere('room', $section->room)))
            ->get()->map(fn ($s) => ['day' => $s->day_of_week, 'start' => substr($s->starts_at, 0, 5), 'end' => substr($s->ends_at, 0, 5),
                'teacher_id' => $s->classSubject->teacher_id, 'room' => $s->room, 'label' => $s->classSubject->subject->name.' · '.$s->classSubject->section->label()]);

        return view('sections.show', [
            'busy' => $busy->values(),
            'section' => $section,
            'students' => $section->students()->get(),
            'waiting' => Enrollment::with('student')->where('school_year_id', $section->school_year_id)->where('grade_level_id', $section->grade_level_id)
                ->whereNull('section_id')->where('status', 'enrolled')->get()->sortBy('student.last_name'),
            'subjects' => Subject::where('is_active', true)->where(fn ($q) => $q->where('grade_level_id', $section->grade_level_id)->orWhereNull('grade_level_id'))
                ->whereNotIn('id', $section->classSubjects->pluck('subject_id'))->orderBy('name')->pluck('name', 'id'),
            'teachers' => User::where('role', Role::TEACHER)->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'levels' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'years' => SchoolYear::orderByDesc('starts_on')->pluck('name', 'id'),
            'rooms' => Room::options(),
            'capacities' => Room::capacities(),
        ]);
    }

    public function assignStudents(Request $request, Section $section)
    {
        $data = $request->validate(['enrollment_ids' => 'required|array', 'enrollment_ids.*' => 'exists:enrollments,id'], ['enrollment_ids.required' => 'Tick at least one student.']);
        $now = $section->enrollments()->where('status', 'enrolled')->count();
        if ($now + count($data['enrollment_ids']) > $section->capacity) {
            return back()->with('error', "{$section->name} has room for ".max(0, $section->capacity - $now)." more student(s) only (capacity {$section->capacity}).");
        }
        $n = Enrollment::whereIn('id', $data['enrollment_ids'])->where('school_year_id', $section->school_year_id)
            ->where('grade_level_id', $section->grade_level_id)->get()->each->update(['section_id' => $section->id])->count();

        return back()->with('ok', "$n student(s) added to {$section->name}.");
    }

    public function removeStudent(Section $section, Enrollment $enrollment)
    {
        abort_unless($enrollment->section_id === $section->id, 404);
        $enrollment->update(['section_id' => null]);

        return back()->with('ok', 'Student removed from the section (still enrolled, waiting for a section).');
    }

    public function addClass(Request $request, Section $section)
    {
        $data = $request->validate(['subject_id' => 'required|exists:subjects,id', 'teacher_id' => 'nullable|exists:users,id']);
        ClassSubject::firstOrCreate(['section_id' => $section->id, 'subject_id' => $data['subject_id']], ['teacher_id' => $data['teacher_id'] ?? null]);

        return back()->with('ok', 'Subject added.');
    }

    public function updateClass(Request $request, ClassSubject $class)
    {
        $data = $request->validate(['teacher_id' => 'nullable|exists:users,id']);
        // The new teacher must be free at every time this subject is already scheduled.
        $probe = (clone $class)->setAttribute('teacher_id', $data['teacher_id'] ?? null);
        $probe->setRelation('teacher', User::find($data['teacher_id'] ?? 0));
        foreach ($class->slots as $slot) {
            if ($probe->teacher_id && ($why = $this->clash($probe, $slot->day_of_week, substr($slot->starts_at, 0, 5), substr($slot->ends_at, 0, 5), null, $slot->id))) {
                return back()->with('error', 'Teacher not changed – '.$why);
            }
        }
        $class->update($data);

        return back()->with('ok', 'Teacher assigned to '.$class->subject->name.'.');
    }

    public function removeClass(ClassSubject $class)
    {
        if ($class->assessments()->exists()) {
            return back()->with('error', 'This class already has grades recorded, so it cannot be removed.');
        }
        $class->delete();

        return back()->with('ok', 'Subject removed from the section.');
    }

    /** Why this time can't be used (teacher, section or room already busy), or null if it's free. */
    private function clash(ClassSubject $class, int $day, string $start, string $end, ?string $room, ?int $ignore = null): ?string
    {
        $sy = $class->section->school_year_id;
        $hit = ScheduleSlot::with('classSubject.subject', 'classSubject.section.gradeLevel', 'classSubject.teacher')
            ->where('day_of_week', $day)->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->whereHas('classSubject.section', fn ($q) => $q->where('school_year_id', $sy))
            ->where(fn ($q) => $q->whereHas('classSubject', fn ($c) => $c->where('section_id', $class->section_id)->orWhere(fn ($w) => $w->whereNotNull('teacher_id')->where('teacher_id', $class->teacher_id)))
                ->when($room, fn ($w) => $w->orWhere('room', $room)))
            ->first();
        if (! $hit) {
            return null;
        }
        $what = match (true) {
            $hit->classSubject->section_id === $class->section_id => 'this section already has '.$hit->classSubject->subject->name,
            $class->teacher_id && $hit->classSubject->teacher_id === $class->teacher_id => ($class->teacher?->name ?? 'the teacher').' already teaches '.$hit->classSubject->subject->name.' in '.$hit->classSubject->section->label(),
            default => $hit->room.' is used by '.$hit->classSubject->section->label().' ('.$hit->classSubject->subject->name.')',
        };

        return ScheduleSlot::DAYS[$day].' '.ampm($hit->starts_at).'–'.ampm($hit->ends_at).': '.$what.'.';
    }

    public function addSlot(Request $request, ClassSubject $class)
    {
        $data = $request->validate([
            'days' => 'required|array|min:1',
            'days.*' => 'integer|between:1,7',
            'starts_at' => 'required|date_format:H:i',
            'ends_at' => 'required|date_format:H:i|after:starts_at',
            'room' => ['nullable', Rule::exists('rooms', 'name')],
        ], ['days.required' => 'Tick at least one day.']);
        $room = $data['room'] ?? $class->section->room;
        $conflicts = [];
        foreach ($data['days'] as $day) {
            if ($why = $this->clash($class, (int) $day, $data['starts_at'], $data['ends_at'], $room)) {
                $conflicts[] = $why;

                continue;
            }
            $class->slots()->create(['day_of_week' => $day, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'room' => $room]);
        }

        return back()->with($conflicts ? 'error' : 'ok', $conflicts ? 'Some times were not added – '.implode('; ', $conflicts) : 'Schedule added.');
    }

    private function slotJson(ScheduleSlot $s): array
    {
        $s->loadMissing('classSubject.subject', 'classSubject.teacher');

        return ['id' => $s->id, 'class_id' => $s->class_subject_id, 'day' => $s->day_of_week, 'start' => substr($s->starts_at, 0, 5), 'end' => substr($s->ends_at, 0, 5),
            'room' => $s->room, 'subject' => $s->classSubject->subject->name, 'teacher' => $s->classSubject->teacher?->name];
    }

    /** Drag & drop: place a subject on the grid. */
    public function placeSlot(Request $request, ClassSubject $class)
    {
        $d = $request->validate(['day' => 'required|integer|between:1,7', 'start' => 'required|date_format:H:i', 'minutes' => 'required|integer|min:15|max:480', 'room' => ['nullable', Rule::exists('rooms', 'name')]]);
        $end = Carbon::createFromFormat('H:i', $d['start'])->addMinutes((int) $d['minutes'])->format('H:i');
        $room = $d['room'] ?? $class->section->room;
        if ($why = $this->clash($class, (int) $d['day'], $d['start'], $end, $room)) {
            return response()->json(['message' => $why], 422);
        }

        return response()->json($this->slotJson($class->slots()->create(['day_of_week' => $d['day'], 'starts_at' => $d['start'], 'ends_at' => $end, 'room' => $room])));
    }

    /** Drag & drop: move or resize an existing block. */
    public function moveSlot(Request $request, ScheduleSlot $slot)
    {
        $d = $request->validate(['day' => 'required|integer|between:1,7', 'start' => 'required|date_format:H:i', 'minutes' => 'nullable|integer|min:15|max:480']);
        $mins = $d['minutes'] ?? Carbon::parse($slot->starts_at)->diffInMinutes(Carbon::parse($slot->ends_at));
        $end = Carbon::createFromFormat('H:i', $d['start'])->addMinutes((int) $mins)->format('H:i');
        if ($why = $this->clash($slot->classSubject, (int) $d['day'], $d['start'], $end, $slot->room, $slot->id)) {
            return response()->json(['message' => $why], 422);
        }
        $slot->update(['day_of_week' => $d['day'], 'starts_at' => $d['start'], 'ends_at' => $end]);

        return response()->json($this->slotJson($slot->fresh()));
    }

    public function removeSlot(ScheduleSlot $slot)
    {
        $slot->delete();
        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Schedule removed.');
    }

    /** Weekly timetable of a section, a teacher, or "my" schedule. */
    public function timetable(Request $request)
    {
        $u = $request->user();
        $sy = SchoolYear::current();
        $sections = Section::where('school_year_id', $sy?->id)->with('gradeLevel')->get();
        $q = ScheduleSlot::with('classSubject.subject', 'classSubject.section.gradeLevel', 'classSubject.teacher')->whereHas('classSubject.section', fn ($s) => $s->where('school_year_id', $sy?->id));
        $title = '';
        $rooms = Room::orderBy('name')->pluck('name');
        if ($request->query('room') === '__all') {
            $all = $q->whereNotNull('room')->get();
            $day = (int) $request->query('day', min(6, now()->dayOfWeekIso));

            return view('sections.rooms', [
                'rooms' => $rooms->merge($all->pluck('room'))->unique()->values(),
                'day' => $day,
                'slots' => $all,
                'print' => $request->boolean('print'),
            ]);
        }
        if ($request->filled('subject')) {
            $q->whereHas('classSubject.subject', fn ($sub) => $sub->where('name', $request->subject));
            $title = $request->subject.' – all sections';
        } elseif ($request->filled('room')) {
            $q->where('room', $request->room);
            $title = 'Room '.preg_replace('/^Room\s*/i', '', $request->room);
        } elseif ($request->filled('section_id')) {
            $q->whereHas('classSubject', fn ($c) => $c->where('section_id', $request->section_id));
            $title = $sections->firstWhere('id', $request->section_id)?->label();
        } else {
            $teacher = $request->filled('teacher_id') && $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? $request->teacher_id : $u->id;
            $q->whereHas('classSubject', fn ($c) => $c->where('teacher_id', $teacher));
            $title = User::find($teacher)?->name;
        }

        return view('sections.timetable', [
            'slots' => $q->get()->sortBy('starts_at')->groupBy('day_of_week'),
            'title' => $title,
            'sections' => $sections->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'rooms' => $rooms,
            'subjects' => Subject::where('is_active', true)->orderBy('name')->pluck('name')->unique()->values(),
            'mode' => $request->filled('subject') ? 'subject' : ($request->filled('room') ? 'room' : ($request->filled('section_id') ? 'section' : 'teacher')),
            'teachers' => $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? User::where('role', Role::TEACHER)->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function promotion()
    {
        return view('sections.promotion', [
            'years' => SchoolYear::orderByDesc('starts_on')->get(),
            'passing' => Setting::get('passing_grade'),
        ]);
    }

    public function promote(Request $request)
    {
        $data = $request->validate([
            'from_year_id' => 'required|exists:school_years,id',
            'to_year_id' => 'required|exists:school_years,id|different:from_year_id',
            'confirm' => 'accepted',
        ], ['confirm.accepted' => 'Tick the box to confirm.']);
        $r = EnrollmentService::promote((int) $data['from_year_id'], (int) $data['to_year_id'], (float) Setting::get('passing_grade'));

        return back()->with('ok', "Done: {$r['promoted']} promoted, {$r['retained']} retained, {$r['graduated']} graduated. Promoted/retained students are now enrolled in the new school year without a section – assign sections next.");
    }
}
