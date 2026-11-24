<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\SchoolYear;
use App\Models\Submission;
use App\Notifications\GeneralNotice;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    private function classes()
    {
        $u = auth()->user();

        return ClassSubject::with('subject', 'section.gradeLevel')->whereHas('section', fn ($s) => $s->where('school_year_id', SchoolYear::current()?->id))
            ->when($u->role === Role::TEACHER, fn ($q) => $q->where('teacher_id', $u->id))->get();
    }

    private function own(Assignment $a): void
    {
        $u = auth()->user();
        abort_unless($u->hasRole(Role::ADMIN, Role::REGISTRAR) || $a->classSubject->teacher_id === $u->id, 403);
    }

    public function index()
    {
        $classes = $this->classes();

        return view('assignments.index', [
            'classes' => $classes->mapWithKeys(fn ($c) => [$c->id => $c->subject->name.' · '.$c->section->label()]),
            'items' => Assignment::with('classSubject.subject', 'classSubject.section')->withCount('submissions')->whereIn('class_subject_id', $classes->pluck('id'))->latest('due_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_subject_id' => 'required|exists:class_subjects,id', 'title' => 'required|max:150', 'instructions' => 'nullable|max:10000',
            'due_at' => 'required|date', 'max_score' => 'required|numeric|min:1', 'attachment' => 'nullable|file|max:10240',
        ]);
        abort_unless($this->classes()->contains('id', (int) $data['class_subject_id']), 403);
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('assignments', 'local');
        }
        unset($data['attachment']);
        $a = Assignment::create($data);
        $students = $a->classSubject->section->students()->with('user', 'guardians.user')->get();
        Notification::send($students->pluck('user')->filter(), new GeneralNotice('New assignment: '.$a->title, $a->classSubject->subject->name.' · due '.$a->due_at->format('M d g:i A'), '/portal/assignments'));

        return back()->with('ok', 'Assignment posted.');
    }

    public function show(Assignment $assignment)
    {
        $this->own($assignment);

        return view('assignments.show', [
            'a' => $assignment->load('classSubject.subject', 'classSubject.section'),
            'students' => $assignment->classSubject->section->students()->get(),
            'subs' => $assignment->submissions()->get()->keyBy('student_id'),
        ]);
    }

    public function grade(Request $request, Submission $submission)
    {
        $this->own($submission->assignment);
        $data = $request->validate(['score' => 'required|numeric|min:0|max:'.$submission->assignment->max_score, 'feedback' => 'nullable|max:2000']);
        $submission->update($data);
        $submission->student->user?->notify(new GeneralNotice('Assignment graded', $submission->assignment->title.': '.$data['score'].'/'.$submission->assignment->max_score, '/portal/assignments'));

        return back()->with('ok', 'Graded.');
    }

    public function attachment(Assignment $assignment)
    {
        $u = auth()->user();
        $student = $u->role === 'student' ? $u->student : null;
        abort_unless($u->isStaff() || $student?->currentEnrollment()?->section_id === $assignment->classSubject->section_id
            || ($u->role === 'parent' && $u->children()->get()->contains(fn ($c) => $c->currentEnrollment()?->section_id === $assignment->classSubject->section_id)), 403);

        return Storage::disk('local')->download($assignment->attachment_path);
    }

    public function file(Submission $submission)
    {
        $this->own($submission->assignment);

        return Storage::disk('local')->download($submission->file_path);
    }
}
