<?php

namespace App\Http\Controllers;

use App\Models\ClassSubject;
use App\Models\GradeSubmission;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\Setting;
use App\Services\Grading;
use Illuminate\Http\Request;

/** Approval queue: teachers submit a grading period's grades; the registrar/admin approves or returns each class. */
class GradeApprovalController extends Controller
{
    public const TABS = ['submitted' => 'Needs approval', 'all' => 'All', 'returned' => 'Returned'];

    public function index(Request $request)
    {
        $sy = SchoolYear::current();
        $classes = ClassSubject::with(['subject', 'section.gradeLevel', 'teacher', 'submissions'])
            ->withCount('assessments')
            ->whereHas('section', fn ($s) => $s->where('school_year_id', $sy?->id))->get();
        $maxTerms = (int) ($classes->max(fn ($c) => $c->section->gradeLevel->terms ?: 4) ?: 4);
        // Current period = the first one not fully approved yet.
        $current = collect(range(1, $maxTerms))->first(fn ($t) => $classes->contains(fn ($c) => $c->submissions->firstWhere('term', $t)?->status !== 'approved')) ?? $maxTerms;
        $term = min(max((int) $request->query('term', $current), 1), $maxTerms);
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'submitted';

        $state = function ($c) use ($term) {
            $s = $c->submissions->firstWhere('term', $term);

            return $s?->status ?? ($c->assessments_count > 0 ? 'in_progress' : 'not_started');
        };
        $counts = $classes->countBy($state);
        $rows = $classes->map(fn ($c) => ['class' => $c, 'state' => $state($c), 'submission' => $c->submissions->firstWhere('term', $term)])
            ->filter(fn ($r) => $tab === 'all' || $r['state'] === $tab)
            ->sortBy(fn ($r) => [$r['class']->section->gradeLevel->sort_order, $r['class']->section->name, $r['class']->subject->name]);
        $sections = $rows->groupBy(fn ($r) => $r['class']->section_id)->map(function ($g) use ($classes, $state) {
            $sec = $g->first()['class']->section;
            $mine = $classes->where('section_id', $sec->id);

            return ['section' => $sec, 'rows' => $g->values(), 'approved' => $mine->filter(fn ($c) => $state($c) === 'approved')->count(), 'total' => $mine->count()];
        })->values();

        return view('gradebook.approvals', compact('sections', 'counts', 'term', 'tab', 'maxTerms', 'current'));
    }

    /** Review one submitted class: stats, then approve or return with a comment. */
    public function show(GradeSubmission $submission)
    {
        $cs = $submission->classSubject()->with('subject', 'section.gradeLevel', 'teacher')->firstOrFail();
        $sheet = collect(Grading::sheet($cs, $submission->term));
        $grades = $sheet->pluck('quarterly')->filter(fn ($g) => $g !== null);
        $assessmentIds = $cs->assessments()->where('term', $submission->term)->pluck('id');
        $scored = Score::whereIn('assessment_id', $assessmentIds)->whereNotNull('score')->selectRaw('student_id, count(*) n')->groupBy('student_id')->pluck('n', 'student_id');
        $missingBy = $sheet->mapWithKeys(fn ($r, $id) => [$id => $assessmentIds->count() - (int) ($scored[$id] ?? 0)]);
        $missing = $missingBy->sum();
        $stats = ['students' => $sheet->count(), 'average' => $grades->avg(), 'highest' => $grades->max(), 'lowest' => $grades->min(), 'missing' => $missing,
            'failing' => $grades->filter(fn ($g) => $g < (float) Setting::get('passing_grade', '75'))->count(), 'assessments' => $assessmentIds->count()];

        return view('gradebook.review', ['s' => $submission, 'cs' => $cs, 'sheet' => $sheet, 'stats' => $stats, 'missingBy' => $missingBy]);
    }

    /** Approve several submitted classes at once (same rule as approving one by one). */
    public function bulk(Request $request, GradebookController $gradebook)
    {
        $ids = $request->validate(['ids' => 'required|array|max:500', 'ids.*' => 'integer'], ['ids.required' => 'Tick at least one class.'])['ids'];
        $subs = GradeSubmission::whereIn('id', $ids)->where('status', 'submitted')->get();
        foreach ($subs as $s) {
            $gradebook->decide($s, 'approve', null);
        }

        return back()->with('ok', 'Approved '.$subs->count().' '.str('class')->plural($subs->count()).'.');
    }
}
