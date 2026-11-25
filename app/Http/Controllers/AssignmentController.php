<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\Submission;
use App\Notifications\GeneralNotice;
use App\Support\Classwork;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Class cards (Online mode): one card per class. Teachers post announcements, documents and classwork;
 * classwork can count toward a gradebook component, so graded work shows up in the gradebook by itself.
 */
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

    private function onlineOnly(): void
    {
        abort_unless(School::allows('classwork'), 404);
    }

    public function index()
    {
        if (! School::allows('classwork')) {
            return redirect()->route('gradebook.index')->with('ok', 'Class cards and assignments are for Online mode. In Offline (LAN) mode, enter scores here and submit each grading period to the admin.');
        }
        $classes = $this->classes()->load('teacher', 'slots')->loadCount(['assignments' => fn ($q) => $q->whereNotIn('type', ['announcement', 'material'])]);
        $ids = $classes->pluck('id');
        $toReview = Submission::whereNull('submissions.score')->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')->whereIn('assignments.class_subject_id', $ids)
            ->selectRaw('assignments.class_subject_id, count(*) as n')->groupBy('assignments.class_subject_id')->pluck('n', 'class_subject_id');
        $next = Assignment::whereIn('class_subject_id', $ids)->whereNotNull('due_at')->where('due_at', '>=', now())->orderBy('due_at')->get()->groupBy('class_subject_id')->map->first();

        return view('assignments.index', compact('classes', 'toReview', 'next'));
    }

    public function classroom(Request $request, ClassSubject $class)
    {
        $this->onlineOnly();
        abort_unless($this->classes()->contains('id', $class->id), 403);
        $class->load('subject', 'section.gradeLevel', 'teacher', 'slots');
        $students = $class->section->students()->get();
        $posts = $class->assignments()->withCount(['submissions', 'submissions as graded_count' => fn ($q) => $q->whereNotNull('score')])->latest()->get();
        $terms = $class->section->gradeLevel->terms ?: 4;

        return view('assignments.class', compact('class', 'students', 'posts', 'terms'));
    }

    public function store(Request $request)
    {
        $this->onlineOnly();
        $data = $request->validate([
            'class_subject_id' => 'required|exists:class_subjects,id',
            'type' => ['required', Rule::in(array_keys(Classwork::TYPES))],
            'title' => 'required|max:150', 'instructions' => 'nullable|max:10000',
            'due_at' => 'nullable|date', 'max_score' => 'nullable|numeric|min:1|max:1000',
            'component' => 'nullable|in:ww,pt,qa', 'term' => 'nullable|required_with:component|integer|min:1|max:6',
            'body_html' => 'nullable|max:200000',
            'attachment' => 'nullable|file|max:20480',
        ]);
        $class = ClassSubject::with('subject', 'section.gradeLevel')->findOrFail($data['class_subject_id']);
        abort_unless($this->classes()->contains('id', $class->id), 403);
        if (Classwork::gradable($data['type'])) {
            $request->validate(['due_at' => 'required|date', 'max_score' => 'required|numeric|min:1']);
        } else {
            $data['due_at'] = $data['max_score'] = $data['component'] = $data['term'] = null;
        }
        if (empty($data['component'])) {
            $data['component'] = $data['term'] = null;
        } elseif ($class->isLocked((int) $data['term'])) {
            return back()->withInput()->with('error', School::term((int) $data['term'], $class->section->gradeLevel->terms).' grades are already submitted, so new work can\'t count toward it. Pick another grading period or "Not included".');
        }
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('assignments', 'local');
        }
        unset($data['attachment']);

        $a = DB::transaction(function () use ($data, $class) {
            $a = Assignment::create($data);
            if ($a->component) {
                $assessment = Assessment::create(['class_subject_id' => $class->id, 'term' => $a->term, 'component' => $a->component, 'title' => $a->title, 'max_score' => $a->max_score, 'given_on' => ($a->due_at ?? now())->toDateString()]);
                $a->update(['assessment_id' => $assessment->id]);
            }

            return $a;
        });

        $label = Classwork::TYPES[$a->type][0];
        $students = $class->section->students()->with('user')->get();
        Notification::send($students->pluck('user')->filter(), new GeneralNotice('New '.strtolower($label).': '.$a->title, $class->subject->name.($a->due_at ? ' · due '.$a->due_at->format('M d g:i A') : ''), route('portal.class', $class, false)));

        if ($a->type === 'quiz') {
            return redirect()->route('quiz.edit', $a)->with('ok', 'Quiz created – now add the questions (or import GIFT).');
        }

        return redirect()->route('assignments.class', $class)->with('ok', $label.' posted'.($a->component ? ' – it is now a column in the gradebook.' : '.'));
    }

    public function destroy(Assignment $assignment)
    {
        $this->onlineOnly();
        $this->own($assignment);
        if ($assignment->assessment_id && $assignment->classSubject->isLocked((int) $assignment->term)) {
            return back()->with('error', 'This work is part of grades that were already submitted, so it can\'t be deleted.');
        }
        $class = $assignment->class_subject_id;
        DB::transaction(function () use ($assignment) {
            Assessment::whereKey($assignment->assessment_id)->delete();
            $assignment->delete();
        });

        return redirect()->route('assignments.class', $class)->with('ok', 'Deleted.');
    }

    public function show(Assignment $assignment)
    {
        $this->onlineOnly();
        $this->own($assignment);

        return view('assignments.show', [
            'a' => $assignment->load('classSubject.subject', 'classSubject.section.gradeLevel'),
            'students' => $assignment->classSubject->section->students()->get(),
            'subs' => $assignment->submissions()->get()->keyBy('student_id'),
        ]);
    }

    public function grade(Request $request, Submission $submission)
    {
        $this->onlineOnly();
        $a = $submission->assignment;
        $this->own($a);
        $data = $request->validate(['score' => 'required|numeric|min:0|max:'.($a->max_score ?? 1000), 'feedback' => 'nullable|max:2000']);
        if ($a->assessment_id && $a->classSubject->isLocked((int) $a->term)) {
            return back()->with('error', 'These grades were already submitted – ask the registrar to return them first.');
        }
        DB::transaction(function () use ($submission, $a, $data) {
            $submission->update($data);
            if ($a->assessment_id) {
                Score::updateOrCreate(['assessment_id' => $a->assessment_id, 'student_id' => $submission->student_id], ['score' => $data['score']]);
            }
        });
        $submission->student->user?->notify(new GeneralNotice('Work graded', $a->title.': '.$data['score'].'/'.$a->max_score, route('portal.class', $a->class_subject_id, false)));

        return back()->with('ok', 'Graded'.($a->assessment_id ? ' and saved to the gradebook.' : '.'));
    }

    /** Teacher's default card colour for the class. */
    public function color(Request $request, ClassSubject $class)
    {
        $this->onlineOnly();
        abort_unless($this->classes()->contains('id', $class->id), 403);
        $class->update(['card_color' => $request->validate(['color' => ['required', Rule::in(array_keys(Classwork::COLORS))]])['color']]);

        return back()->with('ok', 'Card colour saved. Students see it unless they picked their own.');
    }

    /**
     * Teacher-designed content (HTML/CSS/JS) as its own page, shown in a sandboxed frame:
     * it can animate and run scripts, but cannot touch the rest of SmartSchool (see SecurityHeaders).
     */
    public function content(Assignment $assignment)
    {
        $this->onlineOnly();
        $u = auth()->user();
        $section = $assignment->classSubject->section_id;
        abort_unless($u->isStaff()
            || ($u->role === 'student' && $u->student?->currentEnrollment()?->section_id === $section)
            || ($u->role === 'parent' && $u->children()->get()->contains(fn ($c) => $c->currentEnrollment()?->section_id === $section)), 403);

        $resize = 'const s=()=>parent.postMessage({ssFrame:'.$assignment->id.',h:document.documentElement.scrollHeight},"*");addEventListener("load",s);new ResizeObserver(s).observe(document.documentElement);';
        $code = (string) $assignment->body_html;
        if (preg_match('/<html[\s>]/i', $code)) {
            // A full page was pasted: keep it as is, just add the auto-height script.
            $html = stripos($code, '</body>') !== false ? str_ireplace('</body>', '<script>'.$resize.'</script></body>', $code) : $code.'<script>'.$resize.'</script>';
        } else {
            $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                .'<style>html,body{margin:0;font-family:system-ui,sans-serif;color:#0f172a}body{padding:4px}img,video,iframe{max-width:100%}</style>'
                .($assignment->body_css ? '<style>'.$assignment->body_css.'</style>' : '').'</head><body>'.$code
                .($assignment->body_js ? '<script>'.$assignment->body_js.'</script>' : '').'<script>'.$resize.'</script></body></html>';
        }

        return response($html)->header('Content-Type', 'text/html; charset=utf-8')->header('X-SmartSchool-Sandbox', '1');
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
