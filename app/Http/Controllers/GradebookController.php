<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\GradeSubmission;
use App\Models\GradingRequest;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\Section;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Services\Grading;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class GradebookController extends Controller
{
    private function authorizeClass(ClassSubject $cs): void
    {
        $u = auth()->user();
        abort_unless($u->hasRole(Role::ADMIN, Role::REGISTRAR) || $cs->teacher_id === $u->id, 403, 'Only the subject teacher can open this gradebook.');
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $sy = SchoolYear::current();
        $q = ClassSubject::with(['subject', 'section.gradeLevel', 'teacher', 'submissions'])->withCount(['assessments as t1' => fn ($a) => $a->where('term', 1), 'assessments as t2' => fn ($a) => $a->where('term', 2), 'assessments as t3' => fn ($a) => $a->where('term', 3), 'assessments as t4' => fn ($a) => $a->where('term', 4)])->whereHas('section', fn ($s) => $s->where('school_year_id', $sy?->id));
        if ($u->role === Role::TEACHER) {
            $q->where('teacher_id', $u->id);
        }
        $all = $q->get()->sortBy(fn ($c) => [$c->section->gradeLevel->sort_order, $c->section->name, $c->subject->name]);
        $levels = $all->groupBy(fn ($c) => $c->section->grade_level_id)->map(fn ($g) => $g->first()->section->gradeLevel->name);
        $classes = $all->when($request->filled('level'), fn ($c) => $c->filter(fn ($x) => $x->section->grade_level_id == $request->level))
            ->when($request->filled('section'), fn ($c) => $c->where('section_id', (int) $request->section));

        return view('gradebook.index', [
            'classes' => $classes,
            'levels' => $levels,
            'sectionsList' => $all->when($request->filled('level'), fn ($c) => $c->filter(fn ($x) => $x->section->grade_level_id == $request->level))->mapWithKeys(fn ($c) => [$c->section_id => $c->section->label()]),
            'weightRequests' => $u->isAdmin() ? GradingRequest::with('classSubject.subject', 'classSubject.section.gradeLevel', 'requester')->where('status', 'pending')->latest()->get() : collect(),
            'pending' => $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? GradeSubmission::with('classSubject.subject', 'classSubject.section', 'classSubject.teacher')->where('status', 'submitted')->get() : collect(),
        ]);
    }

    public function show(Request $request, ClassSubject $class)
    {
        $this->authorizeClass($class);
        $class->load('subject', 'section.gradeLevel', 'teacher');
        $terms = $class->section->gradeLevel->terms ?: 4;
        $term = min(max((int) $request->query('term', 1), 1), $terms);

        return view('gradebook.show', [
            'cs' => $class,
            'term' => $term,
            'terms' => $terms,
            'assessments' => $class->assessments()->where('term', $term)->orderBy('component')->orderBy('id')->get(),
            'scores' => Score::whereIn('assessment_id', $class->assessments()->where('term', $term)->select('id'))->get()->groupBy('student_id')->map->keyBy('assessment_id'),
            'sheet' => Grading::sheet($class, $term),
            'submission' => $class->submissions()->where('term', $term)->first(),
            'weightRequest' => $class->gradingRequests()->latest()->first(),
        ]);
    }

    public function addAssessment(Request $request, ClassSubject $class)
    {
        $this->authorizeClass($class);
        $data = $request->validate([
            'term' => 'required|integer|min:1|max:6',
            'component' => 'required|in:ww,pt,qa',
            'title' => 'required|max:120',
            'max_score' => 'required|numeric|min:1|max:1000',
            'given_on' => 'nullable|date',
        ]);
        abort_if($class->isLocked((int) $data['term']), 403, 'Grades for this term were already submitted.');
        $class->assessments()->create($data);

        return redirect()->route('gradebook.show', [$class, 'term' => $data['term']])->with('ok', 'Added "'.$data['title'].'". Enter the scores below.');
    }

    public function deleteAssessment(Assessment $assessment)
    {
        $this->authorizeClass($assessment->classSubject);
        abort_if($assessment->classSubject->isLocked($assessment->term), 403);
        $term = $assessment->term;
        $cs = $assessment->classSubject;
        $assessment->scores()->get()->each->delete(); // audited one by one
        $assessment->delete();

        return redirect()->route('gradebook.show', [$cs, 'term' => $term])->with('ok', 'Assessment deleted.');
    }

    public function saveScores(Request $request, ClassSubject $class)
    {
        $this->authorizeClass($class);
        $term = (int) $request->input('term', 1);
        abort_if($class->isLocked($term), 403, 'Grades for this term were already submitted.');
        $assessments = $class->assessments()->where('term', $term)->get()->keyBy('id');
        $studentIds = $class->section->students()->pluck('students.id')->all();
        $errors = [];
        DB::transaction(function () use ($request, $assessments, $studentIds, &$errors) {
            foreach ((array) $request->input('scores', []) as $aid => $byStudent) {
                $a = $assessments[$aid] ?? null;
                if (! $a) {
                    continue;
                }
                foreach ((array) $byStudent as $sid => $val) {
                    if (! in_array((int) $sid, $studentIds, true)) {
                        continue;
                    }
                    $val = trim((string) $val);
                    if ($val !== '' && (! is_numeric($val) || $val < 0 || $val > $a->max_score)) {
                        $errors["scores.$aid.$sid"] = "Score must be 0–{$a->max_score}.";

                        continue;
                    }
                    $score = Score::firstOrNew(['assessment_id' => $a->id, 'student_id' => $sid]);
                    $new = $val === '' ? null : round((float) $val, 2);
                    if (! $score->exists && $new === null) {
                        continue;
                    }
                    if ($score->exists && (float) $score->score === (float) $new && ($score->score === null) === ($new === null)) {
                        continue;
                    }
                    $score->score = $new;
                    $score->save(); // every change is written to the audit log
                }
            }
        });
        if ($errors) {
            return back()->withInput()->withErrors($errors)->with('error', count($errors).' score(s) were out of range and not saved.');
        }

        return back()->with('ok', 'Scores saved.');
    }

    public function submit(Request $request, ClassSubject $class)
    {
        $this->authorizeClass($class);
        $term = (int) $request->input('term');
        GradeSubmission::updateOrCreate(['class_subject_id' => $class->id, 'term' => $term], ['status' => 'submitted', 'submitted_by' => auth()->id(), 'remarks' => null]);
        Notification::send(User::whereIn('role', [Role::REGISTRAR, Role::ADMIN])->where('is_active', true)->get(),
            new GeneralNotice('Grades submitted', "{$class->subject->name} – {$class->section->label()} (term $term) by ".auth()->user()->name, route('gradebook.index', [], false)));

        return back()->with('ok', 'Grades submitted for approval. Editing is locked unless the registrar returns them.');
    }

    public function review(Request $request, GradeSubmission $submission)
    {
        abort_unless(auth()->user()->hasRole(Role::ADMIN, Role::REGISTRAR), 403);
        $data = $request->validate(['action' => 'required|in:approve,return', 'remarks' => 'nullable|required_if:action,return|max:500']);
        $submission->update(['status' => $data['action'] === 'approve' ? 'approved' : 'returned', 'approved_by' => auth()->id(), 'remarks' => $data['remarks'] ?? null]);
        $cs = $submission->classSubject;
        if ($cs->teacher) {
            $cs->teacher->notify(new GeneralNotice(
                $data['action'] === 'approve' ? 'Grades approved' : 'Grades returned for correction',
                "{$cs->subject->name} – {$cs->section->label()} term {$submission->term}".($data['remarks'] ?? '' ? ': '.$data['remarks'] : ''),
                route('gradebook.show', [$cs, 'term' => $submission->term], false)));
        }

        return back()->with('ok', $data['action'] === 'approve' ? 'Approved.' : 'Returned to the teacher (editing unlocked).');
    }

    /**
     * Change how this class is graded. The admin applies it at once; a teacher's change waits for the admin's approval.
     */
    public function requestWeights(Request $request, ClassSubject $class)
    {
        $this->authorizeClass($class);
        $u = $request->user();
        $data = $request->validate([
            'ww' => 'required|integer|min:0|max:100', 'pt' => 'required|integer|min:0|max:100',
            'qa' => 'required|integer|min:0|max:100', 'at' => 'required|integer|min:0|max:100',
            'reason' => [$u->isAdmin() ? 'nullable' : 'required', 'max:1000'],
        ], ['reason.required' => 'Tell the admin why you want this change.']);
        if ($data['ww'] + $data['pt'] + $data['qa'] + $data['at'] !== 100) {
            return back()->withInput()->withErrors(['ww' => 'The parts must add up to 100%. Right now they add up to '.($data['ww'] + $data['pt'] + $data['qa'] + $data['at']).'%.']);
        }
        $w = ['ww_weight' => $data['ww'], 'pt_weight' => $data['pt'], 'qa_weight' => $data['qa'], 'at_weight' => $data['at']];
        if ($u->isAdmin()) {
            $class->update($w);

            return back()->with('ok', 'Grading changed for this class. Grades are recomputed with the new weights.');
        }
        $class->gradingRequests()->where('status', 'pending')->update(['status' => 'rejected', 'review_note' => 'Replaced by a newer request']);
        $req = $class->gradingRequests()->create($w + ['requested_by' => $u->id, 'reason' => $data['reason']]);
        Notification::send(User::where('role', Role::ADMIN)->where('is_active', true)->get(), new GeneralNotice(
            'Grading change request', "{$u->name} wants to change grading for {$class->subject->name} – {$class->section->label()}.", route('gradebook.index', [], false)));

        return back()->with('ok', 'Request sent to the admin. The current weights stay in use until it is approved.');
    }

    public function reviewWeights(Request $request, GradingRequest $gradingRequest)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only the admin can approve grading changes.');
        $data = $request->validate(['action' => 'required|in:approve,reject', 'note' => 'nullable|max:500']);
        abort_unless($gradingRequest->status === 'pending', 409);
        $gradingRequest->update(['status' => $data['action'] === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note'] ?? null]);
        $cs = $gradingRequest->classSubject;
        if ($data['action'] === 'approve') {
            $cs->update(collect($gradingRequest->only(['ww_weight', 'pt_weight', 'qa_weight', 'at_weight']))->all());
        }
        $gradingRequest->requester->notify(new GeneralNotice(
            $data['action'] === 'approve' ? 'Grading change approved' : 'Grading change not approved',
            "{$cs->subject->name} – {$cs->section->label()}".(($data['note'] ?? '') ? ': '.$data['note'] : ''), route('gradebook.show', $cs, false)));

        return back()->with('ok', $data['action'] === 'approve' ? 'Approved – the new weights are now used for this class.' : 'Request declined.');
    }

    public function resetWeights(ClassSubject $class)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $class->update(['ww_weight' => null, 'pt_weight' => null, 'qa_weight' => null, 'at_weight' => null]);

        return back()->with('ok', 'This class uses the subject\'s standard weights again.');
    }

    public function honors(Request $request)
    {
        $u = $request->user();
        $sy = SchoolYear::current();
        $all = Section::where('school_year_id', $sy?->id)->with('gradeLevel', 'schoolYear')->get()->sortBy(fn ($s) => [$s->gradeLevel->sort_order, $s->name])->values();
        // Teachers see their advisory class; the admin and registrar supervise every section.
        $mine = $u->role === Role::TEACHER ? $all->where('adviser_id', $u->id)->values() : $all;
        $section = $mine->firstWhere('id', (int) $request->query('section_id')) ?? $mine->first();
        $scope = $request->query('scope') === 'level' ? 'level' : 'section';
        // Whole grade level: every section of that level (results are public honors, so teachers may see them).
        $sections = $section ? ($scope === 'level' ? $all->where('grade_level_id', $section->grade_level_id)->values() : collect([$section])) : collect();
        $terms = $section?->gradeLevel->terms ?: 4;
        if ($request->query('term') === 'final') {
            $term = null;
        } elseif ($request->filled('term')) {
            $term = min(max((int) $request->query('term'), 1), $terms);
        } else {
            // Default: the latest grading period every class in the section already submitted, else Q1.
            $done = $section ? collect(range($terms, 1))->first(fn ($t) => $section->classSubjects()->get()->every(fn ($cs) => $cs->isLocked($t))) : null;
            $term = $done ?? 1;
        }
        $rows = \App\Services\Honors::rows($sections, $term);
        $own = $section ? collect([$section]) : collect();   // perfect attendance and special awards: the section only

        return view('gradebook.honors', [
            'sections' => $mine->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'section' => $section, 'scope' => $scope, 'terms' => $terms, 'term' => $term,
            'top' => \App\Services\Honors::top($rows),
            'ranked' => $rows->whereNotNull('average')->where('failed', false)->count(),
            'private' => $rows->where('failed', true)->count(),
            'bestSubject' => \App\Services\Honors::bestPerSubject($rows),
            'perfect' => \App\Services\Honors::perfectAttendance($own, $term),
            'approved' => \App\Services\Honors::nominations($own, $term, 'approved'),
            'pending' => $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? \App\Services\Honors::nominations($all, null, 'pending') : \App\Models\AwardNomination::with('award', 'student')->where('nominated_by', $u->id)->where('status', 'pending')->get(),
            'awards' => \App\Models\Award::orderBy('name')->get(),
            'nominees' => $section ? $section->students()->get() : collect(),
        ]);
    }

    /** A student's "journey to the top": every graded item and grade, per subject, for a period. Staff only. */
    public function journey(Request $request, \App\Models\Student $student)
    {
        abort_unless($request->user()->hasRole(Role::ADMIN, Role::REGISTRAR, Role::TEACHER), 403);
        $e = $student->currentEnrollment();
        abort_unless($e?->section, 404);
        $terms = $e->section->gradeLevel->terms ?: 4;
        $term = $request->query('term') === 'final' ? null : min(max((int) $request->query('term', 1), 1), $terms);
        $classes = $e->section->classSubjects()->with('subject', 'section.gradeLevel', 'assessments', 'teacher')->get()->sortBy('subject.name');
        $rc = Grading::reportCard($e, $classes);
        abort_if(collect($rc['subjects'])->contains(fn ($s) => collect($term ? [$s['terms'][$term] ?? null] : [Grading::runningFinal($s['terms'])])->filter(fn ($g) => $g !== null && $g < (float) (\App\Models\Setting::get('passing_grade') ?: 75))->isNotEmpty())
            && ! $request->user()->canViewStudent($student), 403, 'This student\'s grades are private.');
        $scores = Score::where('student_id', $student->id)->whereIn('assessment_id', $classes->flatMap->assessments->pluck('id'))->get()->keyBy('assessment_id');
        $subjects = $classes->map(fn ($cs) => [
            'cs' => $cs,
            'terms' => Grading::studentTerms($cs, $student),
            'items' => $cs->assessments->when($term, fn ($c) => $c->where('term', $term))->sortBy('given_on')->map(fn ($a) => ['a' => $a, 'score' => $scores[$a->id]->score ?? null])->values(),
        ]);
        $lockedTerms = collect(range(1, $terms))->filter(fn ($t) => $classes->every(fn ($cs) => $cs->isLocked($t)))->values();
        $avgByTerm = collect(range(1, $terms))->mapWithKeys(function ($t) use ($subjects) {
            $g = $subjects->map(fn ($s) => $s['terms'][$t] ?? null);

            return [$t => $g->contains(null) ? null : round($g->avg(), 2)];
        });

        return view('gradebook.journey', compact('student', 'e', 'terms', 'term', 'subjects', 'avgByTerm', 'rc', 'lockedTerms'));
    }

    /** Admins and advisers can add awards to the list (e.g. "Best in Science Fair"). */
    public function saveAward(Request $request)
    {
        abort_unless($request->user()->hasRole(Role::ADMIN, Role::REGISTRAR, Role::TEACHER), 403);
        $d = $request->validate(['name' => 'required|max:100', 'description' => 'nullable|max:255']);
        \App\Models\Award::create($d);

        return back()->with('ok', 'Award added.');
    }

    public function toggleAward(Request $request, \App\Models\Award $award)
    {
        abort_unless($request->user()->hasRole(Role::ADMIN, Role::REGISTRAR), 403);
        $award->update(['is_active' => ! $award->is_active]);

        return back()->with('ok', $award->is_active ? 'Award turned on.' : 'Award turned off.');
    }

    /** Teachers suggest an award for a student; the admin approves it. */
    public function nominate(Request $request)
    {
        $u = $request->user();
        $d = $request->validate(['award_id' => 'required|exists:awards,id', 'student_id' => 'required|exists:students,id', 'term' => 'nullable|integer|min:1|max:6', 'reason' => 'nullable|max:1000']);
        $student = \App\Models\Student::findOrFail($d['student_id']);
        abort_unless($u->hasRole(Role::ADMIN, Role::REGISTRAR) || $student->currentEnrollment()?->section?->adviser_id === $u->id, 403, 'Only the adviser can suggest awards for this student.');
        $isAdmin = $u->hasRole(Role::ADMIN, Role::REGISTRAR);
        \App\Models\AwardNomination::create($d + ['school_year_id' => SchoolYear::current()->id, 'nominated_by' => $u->id, 'status' => $isAdmin ? 'approved' : 'pending', 'reviewed_by' => $isAdmin ? $u->id : null]);
        if (! $isAdmin) {
            Notification::send(User::whereIn('role', [Role::ADMIN, Role::REGISTRAR])->where('is_active', true)->get(), new GeneralNotice('Award suggestion', $u->name.' suggested '.$student->full_name.' for an award.', route('honors', ['tab' => 'awards'], false)));
        }

        return back()->with('ok', $isAdmin ? 'Award given.' : 'Suggestion sent to the admin for approval.');
    }

    public function reviewNomination(Request $request, \App\Models\AwardNomination $nomination)
    {
        abort_unless($request->user()->hasRole(Role::ADMIN, Role::REGISTRAR), 403);
        $ok = $request->validate(['action' => 'required|in:approve,reject'])['action'] === 'approve';
        $nomination->update(['status' => $ok ? 'approved' : 'rejected', 'reviewed_by' => $request->user()->id]);

        return back()->with('ok', $ok ? 'Award approved.' : 'Suggestion declined.');
    }
}
