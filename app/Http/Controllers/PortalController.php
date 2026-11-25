<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\Event;
use App\Models\ScheduleSlot;
use App\Models\Student;
use App\Models\Submission;
use App\Services\Grading;
use App\Support\Classwork;
use App\Support\ParentAccess;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    /** Which portal page needs which permission a student gave their parent. */
    private const ACCESS = ['portal.grades' => 'grades', 'portal.attendance' => 'attendance', 'portal.schedule' => 'schedule', 'portal.account' => 'pay',
        'portal.assignments' => 'classes', 'portal.classes' => 'classes', 'portal.class' => 'classes'];

    private function view(Request $request, string $view, callable $data)
    {
        $child = $this->child($request);
        $need = self::ACCESS[$view] ?? null;
        if ($child && $need && $request->user()->role === 'parent' && ! in_array($need, ParentAccess::for($request->user(), $child), true)) {
            return view('portal.no-access', ['children' => $this->children(), 'child' => $child, 'enrollment' => $child->currentEnrollment(), 'what' => ParentAccess::LABELS[$need]]);
        }

        return view($view, ['children' => $this->children(), 'child' => $child, 'enrollment' => $child?->currentEnrollment()] + ($child ? $data($child, $child->currentEnrollment()) : []));
    }

    public function home(Request $request)
    {
        return $this->view($request, 'portal.home', fn ($c, $e) => [
            // Student's to-do list: every piece of classwork not yet turned in (stays until it is).
            'todo' => ($request->user()->role === 'student' && $request->user()->pref('todo_home', true) && School::allows('classwork') && $e?->section_id)
                ? Assignment::with('classSubject.subject')->whereHas('classSubject', fn ($q) => $q->where('section_id', $e->section_id))->whereNotNull('due_at')
                    ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $c->id))->orderBy('due_at')->get() : collect(),
            'report' => $e ? Grading::reportCard($e) : null,
            'attendance' => AttendanceRecord::where('student_id', $c->id)->whereNull('class_subject_id')->whereMonth('date', now()->month)->whereYear('date', now()->year)->get(),
            'balance' => $e?->account?->balance(),
            'announcements' => Announcement::with('author', 'section.gradeLevel')->visibleTo($request->user())->orderByDesc('is_pinned')->latest('published_at')->limit(10)->get(),
            'events' => Event::whereDate('starts_on', '>=', now()->startOfMonth())->whereDate('starts_on', '<=', now()->addMonth()->endOfMonth())->orderBy('starts_on')->get(),
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
        abort_unless(School::allows('classwork'), 404);

        return $this->view($request, 'portal.assignments', fn ($c, $e) => [
            'assignments' => $e?->section_id ? Assignment::with('classSubject.subject')->whereNotNull('due_at')->whereHas('classSubject', fn ($q) => $q->where('section_id', $e->section_id))->latest('due_at')->get() : collect(),
            'submissions' => Submission::where('student_id', $c->id)->get()->keyBy('assignment_id'),
        ]);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        abort_unless(School::allows('classwork'), 404);
        $u = $request->user();
        abort_unless($u->role === 'student', 403, 'Only the student can submit.');
        abort_unless(Classwork::gradable($assignment->type), 422, 'Nothing to turn in for this post.');
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

        return back()->with('ok', 'Turned in'.($assignment->due_at?->isPast() ? ' (late)' : '').'.');
    }

    /** Online mode: the student's class cards. */
    public function classes(Request $request)
    {
        abort_unless(School::allows('classwork'), 404);

        return $this->view($request, 'portal.classes', fn ($c, $e) => [
            'classes' => $e?->section_id ? ClassSubject::with('subject', 'teacher', 'slots')->where('section_id', $e->section_id)->get() : collect(),
            'todo' => $e?->section_id ? Assignment::whereHas('classSubject', fn ($q) => $q->where('section_id', $e->section_id))->whereNotNull('due_at')->where('due_at', '>=', now())
                ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $c->id))->get()->groupBy('class_subject_id') : collect(),
        ]);
    }

    public function classroom(Request $request, ClassSubject $class)
    {
        abort_unless(School::allows('classwork'), 404);

        return $this->view($request, 'portal.class', function ($c, $e) use ($class) {
            abort_unless($e?->section_id === $class->section_id, 403);

            return [
                'class' => $class->load('subject', 'teacher', 'section.gradeLevel', 'slots'),
                'posts' => $class->assignments()->with('classSubject.section.gradeLevel', 'classSubject.subject')->latest()->get(),
                'submissions' => Submission::where('student_id', $c->id)->whereIn('assignment_id', $class->assignments()->select('id'))->get()->keyBy('assignment_id'),
            ];
        });
    }

    /** A student (or parent) can recolour a class card for themselves only. */
    public function color(Request $request, ClassSubject $class)
    {
        abort_unless(School::allows('classwork'), 404);
        $color = $request->validate(['color' => ['nullable', Rule::in(array_keys(Classwork::COLORS))]])['color'] ?? null;
        DB::table('class_card_prefs')->updateOrInsert(['user_id' => $request->user()->id, 'class_subject_id' => $class->id], ['color' => $color, 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('ok', $color ? 'Card colour changed (only you see this).' : 'Back to your teacher\'s colour.');
    }

    /** Students and their family can print the student's own QR ID card. */
    public function idCard(Request $request)
    {
        $child = $this->child($request);
        abort_unless($child, 404);

        return redirect()->route('students.id', $child);
    }
}
