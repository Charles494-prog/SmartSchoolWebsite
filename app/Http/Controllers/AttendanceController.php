<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Services\Attendance;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private function sections()
    {
        $u = auth()->user();
        $sy = SchoolYear::current();

        return Section::where('school_year_id', $sy?->id)->with('gradeLevel')
            ->when($u->role === Role::TEACHER, fn ($q) => $q->where('adviser_id', $u->id))
            ->get()->sortBy(fn ($s) => [$s->gradeLevel->sort_order, $s->name]);
    }

    public function scan()
    {
        $u = auth()->user();
        if ($u->role === Role::GUARD) {
            return view('attendance.scan', ['lateAfter' => Setting::get('late_after'), 'classes' => collect(), 'selected' => 0, 'gate' => true, 'canGate' => true]);
        }
        $classes = ClassSubject::with('subject', 'section.gradeLevel', 'slots')
            ->whereHas('section', fn ($s) => $s->where('school_year_id', SchoolYear::current()?->id))
            ->when($u->role === Role::TEACHER, fn ($q) => $q->where('teacher_id', $u->id))->get()
            ->sortBy(fn ($c) => $c->slots->firstWhere('day_of_week', now()->dayOfWeekIso)?->starts_at ?? '99');
        $today = $classes->filter(fn ($c) => $c->slots->contains('day_of_week', now()->dayOfWeekIso));

        return view('attendance.scan', [
            'lateAfter' => Setting::get('late_after'),
            'classes' => ($today->isNotEmpty() ? $today : $classes)->mapWithKeys(fn ($c) => [$c->id => $c->subject->name.' · '.$c->section->label().(($sl = $c->slots->firstWhere('day_of_week', now()->dayOfWeekIso)) ? ' · '.ampm($sl->starts_at) : '')]),
            'selected' => (int) request('class') ?: ($u->role === Role::TEACHER ? (int) ($today->first()?->id ?? $classes->first()?->id) : 0),
            'gate' => false,
            'canGate' => $u->hasRole(Role::ADMIN, Role::REGISTRAR), // teachers scan only their own classes
        ]);
    }

    public function checkIn(Request $request)
    {
        $request->validate(['code' => 'required|string|max:100', 'class_id' => 'nullable|exists:class_subjects,id']);
        $u = auth()->user();
        $class = $request->filled('class_id') ? ClassSubject::find($request->class_id) : null;
        abort_if($u->role === Role::GUARD && $class, 403, 'The gate scanner only records arrivals and departures.');
        abort_if($u->role === Role::TEACHER && (! $class || $class->teacher_id !== $u->id), 403, 'Teachers scan only for their own classes.');
        $r = Attendance::checkIn($request->code, null, $class);

        return response()->json([
            'ok' => $r['ok'],
            'status' => $r['status'],
            'message' => $r['message'],
            'name' => $r['student']?->first_name.' '.$r['student']?->last_name,
            'section' => $r['student']?->currentEnrollment()?->section?->label(),
        ]);
    }

    public function sheet(Request $request)
    {
        $sections = $this->sections();
        $section = $sections->firstWhere('id', (int) $request->query('section_id')) ?? $sections->first();
        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $records = $section ? AttendanceRecord::where('section_id', $section->id)->whereNull('class_subject_id')->whereDate('date', $date)->get()->keyBy('student_id') : collect();

        // School-year totals per student, without the day being edited (that one is added live in the page).
        $sy = SchoolYear::current();
        $stats = $section ? AttendanceRecord::whereNull('class_subject_id')->whereIn('student_id', $section->students()->pluck('students.id'))
            ->when($sy, fn ($q) => $q->whereBetween('date', [$sy->starts_on, $sy->ends_on]))->whereDate('date', '!=', $date)
            ->selectRaw('student_id, status, count(*) c')->groupBy('student_id', 'status')->get()
            ->groupBy('student_id')->map(fn ($g) => $g->pluck('c', 'status')->map(fn ($v) => (int) $v)) : collect();

        // Full history for the optional "show every day" view.
        $hist = $section ? AttendanceRecord::whereNull('class_subject_id')->where('section_id', $section->id)
            ->when($sy, fn ($q) => $q->whereBetween('date', [$sy->starts_on, $sy->ends_on]))->orderBy('date')->get(['student_id', 'date', 'status']) : collect();
        $history = $hist->groupBy('student_id')->map(fn ($g) => $g->mapWithKeys(fn ($r) => [$r->date->toDateString() => $r->status]));
        $days = $hist->toBase()->map(fn ($r) => $r->date->toDateString())->push($date->toDateString())->unique()->sort()->values();

        return view('attendance.sheet', [
            'history' => $history,
            'days' => $days,
            'stats' => $stats,
            'sections' => $sections->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'section' => $section,
            'date' => $date,
            'students' => $section?->students()->get() ?? collect(),
            'records' => $records,
        ]);
    }

    public function saveSheet(Request $request)
    {
        $data = $request->validate([
            'section_id' => 'required|exists:sections,id',
            'date' => 'required|date|before_or_equal:today',
            'status' => 'array',
            'status.*' => 'in:present,late,absent,excused',
            'remarks' => 'array',
        ]);
        $section = Section::findOrFail($data['section_id']);
        abort_unless($this->sections()->contains('id', $section->id), 403);
        $ids = $section->students()->pluck('students.id')->all();
        $notified = 0;
        foreach ($data['status'] ?? [] as $sid => $status) {
            if (! in_array((int) $sid, $ids, true)) {
                continue;
            }
            $rec = AttendanceRecord::firstOrNew(['student_id' => $sid, 'date' => $data['date'], 'class_subject_id' => null]);
            $changed = $rec->status !== $status;
            $rec->fill(['section_id' => $section->id, 'status' => $status, 'remarks' => $data['remarks'][$sid] ?? null, 'recorded_by' => auth()->id()]);
            if (in_array($status, ['present', 'late']) && ! $rec->time_in) {
                $rec->time_in = now()->format('H:i:s');
            }
            $rec->save();
            if ($changed && in_array($status, ['absent', 'late'])) {
                Attendance::notifyParents($rec);
                $notified++;
            }
        }

        return back()->with('ok', 'Attendance saved.'.($notified ? " Parents of $notified student(s) were notified." : ''));
    }

    public function report(Request $request)
    {
        $sections = $this->sections();
        $section = $sections->firstWhere('id', (int) $request->query('section_id')) ?? $sections->first();
        $month = Carbon::parse(($request->query('month') ?: now()->format('Y-m')).'-01');
        $days = collect();
        for ($d = $month->copy(); $d->month === $month->month; $d->addDay()) {
            if (! $d->isWeekend()) {
                $days->push($d->copy());
            }
        }
        $records = $section ? AttendanceRecord::where('section_id', $section->id)->whereNull('class_subject_id')
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->get()
            ->groupBy('student_id')->map(fn ($r) => $r->keyBy(fn ($x) => $x->date->format('Y-m-d'))) : collect();

        return view('attendance.report', [
            'sections' => $sections->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'section' => $section,
            'month' => $month,
            'days' => $days,
            'students' => $section?->students()->get() ?? collect(),
            'records' => $records,
        ]);
    }
}
