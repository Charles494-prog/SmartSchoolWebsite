<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\ScheduleSlot;
use App\Models\Student;
use App\Models\Submission;
use App\Services\Grading;
use Illuminate\Http\Request;

/** Parent and student portal. A parent only ever sees their own children. */
class PortalController extends Controller
{
    private function children()
    {
        $u = auth()->user();

        return $u->role === 'student' ? Student::where('user_id', $u->id)->get() : $u->children()->get();
    }

    private function child(Request $request): ?Student
    {
        $kids = $this->children();
        if ($request->filled('child')) {
            $request->session()->put('child_id', (int) $request->query('child'));
        }
        $id = $request->session()->get('child_id');

        return $kids->firstWhere('id', $id) ?? $kids->first();
    }

    private function view(Request $request, string $view, callable $data)
    {
        $child = $this->child($request);

        return view($view, ['children' => $this->children(), 'child' => $child, 'enrollment' => $child?->currentEnrollment()] + ($child ? $data($child, $child->currentEnrollment()) : []));
    }

    public function home(Request $request)
    {
        return $this->view($request, 'portal.home', fn ($c, $e) => [
            'report' => $e ? Grading::reportCard($e) : null,
            'attendance' => AttendanceRecord::where('student_id', $c->id)->whereNull('class_subject_id')->whereMonth('date', now()->month)->whereYear('date', now()->year)->get(),
            'balance' => $e?->account?->balance(),
            'announcements' => Announcement::visibleTo($request->user())->orderByDesc('is_pinned')->latest('published_at')->limit(5)->get(),
            'events' => Event::whereDate('starts_on', '>=', today())->orderBy('starts_on')->limit(5)->get(),
            'notifications' => $request->user()->notifications()->limit(5)->get(),
        ]);
    }

    public function grades(Request $request)
    {
        return $this->view($request, 'portal.grades', fn ($c, $e) => [
            'report' => $e ? Grading::reportCard($e) : null,
            'history' => $c->enrollments()->with('schoolYear', 'gradeLevel')->get()->sortByDesc('school_year_id'),
        ]);
    }

    public function attendance(Request $request)
    {
        return $this->view($request, 'portal.attendance', fn ($c) => [
            'records' => AttendanceRecord::where('student_id', $c->id)->whereNull('class_subject_id')->latest('date')->limit(120)->get(),
        ]);
    }

    public function schedule(Request $request)
    {
        return $this->view($request, 'portal.schedule', fn ($c, $e) => [
            'slots' => $e?->section_id ? ScheduleSlot::with('classSubject.subject', 'classSubject.teacher')->whereHas('classSubject', fn ($q) => $q->where('section_id', $e->section_id))->get()->sortBy('starts_at')->groupBy('day_of_week') : collect(),
        ]);
    }

    public function account(Request $request)
    {
        return $this->view($request, 'portal.account', fn ($c, $e) => ['a' => $e?->account?->load('installments', 'payments')]);
    }

    public function assignments(Request $request)
    {
        return $this->view($request, 'portal.assignments', fn ($c, $e) => [
            'assignments' => $e?->section_id ? Assignment::with('classSubject.subject')->whereHas('classSubject', fn ($q) => $q->where('section_id', $e->section_id))->latest('due_at')->get() : collect(),
            'submissions' => Submission::where('student_id', $c->id)->get()->keyBy('assignment_id'),
        ]);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $u = $request->user();
        abort_unless($u->role === 'student', 403, 'Only the student can submit.');
        $student = Student::where('user_id', $u->id)->firstOrFail();
        abort_unless($student->currentEnrollment()?->section_id === $assignment->classSubject->section_id, 403);
        $data = $request->validate(['body' => 'nullable|max:20000|required_without:file', 'file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,zip,txt']);
        $sub = Submission::firstOrNew(['assignment_id' => $assignment->id, 'student_id' => $student->id]);
        if ($sub->score !== null) {
            return back()->with('error', 'This work was already graded.');
        }
        $sub->body = $data['body'] ?? $sub->body;
        if ($request->hasFile('file')) {
            $sub->file_path = $request->file('file')->store('submissions/'.$assignment->id, 'local');
        }
        $sub->submitted_at = now();
        $sub->save();

        return back()->with('ok', 'Submitted'.($assignment->due_at->isPast() ? ' (late)' : '').'.');
    }
}
