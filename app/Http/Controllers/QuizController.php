<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Student;
use App\Services\Quiz;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Quiz builder (teacher) and quiz taking (student). Online mode only. */
class QuizController extends Controller
{
    private function teacherOwns(Assignment $a): void
    {
        abort_unless(School::allows('classwork'), 404);
        $u = auth()->user();
        abort_unless($u->hasRole(Role::ADMIN, Role::REGISTRAR) || $a->classSubject->teacher_id === $u->id, 403);
    }

    // ---------------------------------------------------------------- teacher

    public function edit(Assignment $assignment)
    {
        $this->teacherOwns($assignment);

        return view('quiz.edit', ['a' => $assignment->load('classSubject.subject', 'classSubject.section'), 'questions' => $assignment->questions()->get(), 'attemptCount' => $assignment->attempts()->count()]);
    }

    public function save(Request $request, Assignment $assignment)
    {
        $this->teacherOwns($assignment);
        $data = $request->validate([
            'time_limit' => 'nullable|integer|min:1|max:600', 'attempts_allowed' => 'required|integer|min:0|max:20',
            'shuffle_questions' => 'boolean', 'shuffle_answers' => 'boolean', 'show_results' => 'required|in:after_submit,after_due,never',
            'questions' => 'required|json',
        ]);
        $questions = json_decode($data['questions'], true) ?: [];
        foreach ($questions as $i => $q) {
            validator($q, ['type' => ['required', Rule::in(array_keys(QuizQuestion::TYPES))], 'text' => 'required|string|max:5000', 'points' => 'required|numeric|min:0|max:100', 'data' => 'array'],
                ['text.required' => 'Question '.($i + 1).' has no text.'])->validate();
        }
        DB::transaction(function () use ($assignment, $data, $questions) {
            $assignment->update(collect($data)->except('questions')->all() + ['shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false), 'shuffle_answers' => (bool) ($data['shuffle_answers'] ?? false)]);
            $assignment->questions()->delete();
            foreach (array_values($questions) as $i => $q) {
                $assignment->questions()->create(['position' => $i, 'type' => $q['type'], 'title' => $q['title'] ?? null, 'text' => $q['text'], 'data' => $q['data'] ?? [], 'points' => $q['points'], 'feedback' => $q['feedback'] ?? null]);
            }
        });

        return back()->with('ok', 'Quiz saved – '.count($questions).' '.str('question')->plural(count($questions)).'.');
    }

    public function import(Request $request, Assignment $assignment)
    {
        $this->teacherOwns($assignment);
        $request->validate(['gift' => 'nullable|string|max:500000|required_without:file', 'file' => 'nullable|file|max:2048']);
        $text = $request->hasFile('file') ? file_get_contents($request->file('file')->getRealPath()) : $request->input('gift');
        $parsed = Quiz::parseGift((string) $text);
        if (! $parsed) {
            return back()->with('error', 'No questions found. Check that it is GIFT format (questions separated by a blank line, answers in { }).');
        }
        $start = (int) $assignment->questions()->max('position') + 1;
        foreach ($parsed as $i => $q) {
            $assignment->questions()->create($q + ['position' => $start + $i, 'points' => 1]);
        }

        return back()->with('ok', count($parsed).' '.str('question')->plural(count($parsed)).' imported from GIFT.');
    }

    public function export(Assignment $assignment)
    {
        $this->teacherOwns($assignment);

        return response(Quiz::toGift($assignment->load('questions')))->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="'.str($assignment->title)->slug().'.gift.txt"');
    }

    public function results(Assignment $assignment)
    {
        $this->teacherOwns($assignment);
        $students = $assignment->classSubject->section->students()->get();
        $attempts = $assignment->attempts()->whereNotNull('finished_at')->get()->groupBy('student_id');
        $questions = $assignment->questions()->get();

        return view('quiz.results', ['a' => $assignment->load('classSubject.subject', 'classSubject.section'), 'students' => $students, 'attempts' => $attempts, 'questions' => $questions]);
    }

    /** Teacher marks essays (and can override any question) on one attempt. */
    public function review(Request $request, QuizAttempt $attempt)
    {
        $a = $attempt->assignment;
        $this->teacherOwns($a);
        $questions = $a->questions()->get()->keyBy('id');
        $marks = $attempt->marks ?? [];
        foreach ((array) $request->input('marks', []) as $qid => $v) {
            if ($v === null || $v === '' || ! isset($questions[$qid])) {
                continue;
            }
            $marks[$qid] = max(0, min((float) $v, (float) $questions[$qid]->points));
        }
        $review = collect($questions->keys())->contains(fn ($id) => ! isset($marks[$id]) || $marks[$id] === null);
        $attempt->update(['marks' => $marks, 'needs_review' => $review, 'score' => Quiz::scale($a, array_sum(array_filter($marks)))]);
        Quiz::syncBest($a, $attempt->student_id);

        return back()->with('ok', $review ? 'Saved. Some answers still need points.' : 'Checked – score saved'.($a->assessment_id ? ' to the gradebook.' : '.'));
    }

    // ---------------------------------------------------------------- student

    private function student(Request $request, Assignment $a): Student
    {
        abort_unless(School::allows('classwork'), 404);
        $u = $request->user();
        abort_unless($u->role === 'student', 403, 'Only the student can take the quiz.');
        $s = Student::where('user_id', $u->id)->firstOrFail();
        abort_unless($s->currentEnrollment()?->section_id === $a->classSubject->section_id, 403);

        return $s;
    }

    public function take(Request $request, Assignment $assignment)
    {
        $s = $this->student($request, $assignment);
        $attempts = $assignment->attempts()->where('student_id', $s->id)->orderBy('attempt_no')->get();
        $open = $attempts->first(fn ($t) => ! $t->finished_at);
        if ($open && ($d = $open->deadline()) && $d->isPast()) {
            Quiz::finish($open);     // time ran out while away
            $open = null;
            $attempts = $attempts->fresh();
        }
        $questions = $assignment->questions()->get()->keyBy('id');
        if ($open) {
            $ordered = collect($open->order ?? $questions->keys())->map(fn ($id) => $questions[$id] ?? null)->filter()->values();
            if ($assignment->shuffle_answers) {
                mt_srand($open->id);
                $ordered = $ordered->map(function ($q) {
                    $d = $q->data;
                    if (in_array($q->type, ['mc', 'multi'], true)) {
                        $keys = array_keys($d['options'] ?? []);
                        shuffle($keys);
                        $q->setAttribute('optionOrder', $keys);
                    }

                    return $q;
                });
                mt_srand();
            }

            return view('quiz.take', ['a' => $assignment, 'attempt' => $open, 'questions' => $ordered]);
        }
        $canRetry = $questions->isNotEmpty() && ($assignment->attempts_allowed === 0 || $attempts->count() < $assignment->attempts_allowed);
        $showResults = $assignment->show_results === 'after_submit' || ($assignment->show_results === 'after_due' && $assignment->due_at?->isPast());

        return view('quiz.start', ['a' => $assignment->load('classSubject.subject'), 'attempts' => $attempts, 'canRetry' => $canRetry, 'questions' => $questions, 'showResults' => $showResults]);
    }

    public function start(Request $request, Assignment $assignment)
    {
        $s = $this->student($request, $assignment);
        $count = $assignment->attempts()->where('student_id', $s->id)->count();
        abort_if($assignment->attempts()->where('student_id', $s->id)->whereNull('finished_at')->exists(), 409, 'You already have a quiz open.');
        abort_unless($assignment->attempts_allowed === 0 || $count < $assignment->attempts_allowed, 403, 'No attempts left.');
        $ids = $assignment->questions()->pluck('id');
        abort_if($ids->isEmpty(), 422, 'This quiz has no questions yet.');
        $assignment->attempts()->create(['student_id' => $s->id, 'attempt_no' => $count + 1, 'order' => ($assignment->shuffle_questions ? $ids->shuffle() : $ids)->values()->all(), 'answers' => [], 'started_at' => now()]);

        return redirect()->route('quiz.take', $assignment);
    }

    public function submit(Request $request, QuizAttempt $attempt)
    {
        $s = $this->student($request, $attempt->assignment);
        abort_unless($attempt->student_id === $s->id && ! $attempt->finished_at, 403);
        $answers = (array) $request->input('answers', []);
        $late = ($d = $attempt->deadline()) && now()->greaterThan($d->copy()->addSeconds(30)); // small grace for slow networks
        $attempt->update(['answers' => $answers]);
        if ($request->boolean('save_only') && ! $late) {
            return response()->noContent();
        }
        Quiz::finish($attempt);

        return redirect()->route('quiz.take', $attempt->assignment)->with('ok', $late ? 'Time was up – your answers were turned in.' : 'Quiz turned in!');
    }
}
