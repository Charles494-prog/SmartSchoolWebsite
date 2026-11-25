<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Award;
use App\Models\AwardNomination;
use App\Models\ClassSubject;
use App\Models\QuizAttempt;
use App\Models\Score;
use App\Models\User;
use App\Services\Quiz;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAndHonorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_gift_parser_understands_every_question_type(): void
    {
        $q = Quiz::parseGift(<<<'GIFT'
// a comment
::Capital:: What is the capital? { =Manila ~Cebu#Not this one ~Davao }

Grass is green. {TRUE}

Pick the even numbers. { ~%50%2 ~%50%4 ~%-100%3 }

Pi to two decimals? {#3.14:0.01}

Between 1 and 5? {#1..5}

Who wrote Noli? { =Rizal =Jose Rizal }

Match. { =cat -> meow =dog -> woof }

Write an essay. {}

Escaped \{braces\} and 50\% sure? { =yes ~no }
GIFT);
        $this->assertSame(['mc', 'tf', 'multi', 'numeric', 'numeric', 'short', 'matching', 'essay', 'mc'], array_column($q, 'type'));
        $this->assertSame('Capital', $q[0]['title']);
        $this->assertSame('Not this one', $q[0]['data']['options'][1]['feedback']);
        $this->assertTrue($q[1]['data']['answer']);
        $this->assertEquals(-100, $q[2]['data']['options'][2]['weight']);
        $this->assertEquals(3, $q[4]['data']['answers'][0]['value']);
        $this->assertSame('woof', $q[6]['data']['pairs'][1]['right']);
        $this->assertStringContainsString('{braces}', $q[8]['text']);
    }

    public function test_teacher_builds_a_quiz_and_student_takes_it_auto_graded_into_the_gradebook(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        $student = User::where('email', 'student@school.test')->first();
        $class = ClassSubject::where('teacher_id', $teacher->id)->where('section_id', $student->student->currentEnrollment()->section_id)->first();
        $this->actingAs($teacher);
        $this->post('/assignments', ['class_subject_id' => $class->id, 'type' => 'quiz', 'title' => 'Pop quiz', 'due_at' => now()->addDay()->format('Y-m-d H:i'), 'max_score' => 10, 'component' => 'ww', 'term' => 3])
            ->assertRedirect();
        $quiz = Assignment::where('title', 'Pop quiz')->first();
        $this->post("/assignments/{$quiz->id}/quiz/import", ['gift' => "1+1? {#2}\n\nSky is blue. {T}"])->assertRedirect();
        $this->get("/assignments/{$quiz->id}/quiz")->assertOk()->assertSee('Quiz builder');
        $gift = $this->get("/assignments/{$quiz->id}/quiz/export")->assertOk()->getContent();
        $this->assertStringContainsString('{ #=2 }', str_replace('{#', '{ #', $gift) === $gift ? str_replace('{ #', '{ #', $gift) : $gift);

        $this->actingAs($student);
        $this->get("/portal/quiz/{$quiz->id}")->assertOk()->assertSee('Start quiz');
        $this->post("/portal/quiz/{$quiz->id}/start")->assertRedirect();
        $attempt = QuizAttempt::where('assignment_id', $quiz->id)->first();
        [$q1, $q2] = $quiz->questions()->get()->all();
        $this->get("/portal/quiz/{$quiz->id}")->assertOk()->assertSee('Turn in');
        $this->post("/portal/quiz-attempts/{$attempt->id}", ['answers' => [$q1->id => '2', $q2->id => '0']])->assertRedirect();
        $attempt->refresh();
        $this->assertEquals(5, $attempt->score, 'one of two right = 5 of 10');
        $this->assertEquals(5, Score::where('assessment_id', $quiz->assessment_id)->where('student_id', $student->student->id)->value('score'));
        $this->get("/portal/quiz/{$quiz->id}")->assertOk()->assertSee('Correct: True');
    }

    public function test_honors_hide_failing_students_and_support_grade_level_and_awards(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $section = \App\Models\Section::whereIn('id', $teacher->teacherSectionIds())->first();
        $r = $this->get("/honors?section_id={$section->id}&term=1")->assertOk()->assertSee('Top 10')->assertSee('Best in')->assertSee('Perfect attendance');
        // student index 5 in each section is the weak demo student: must not be on the public list
        $weak = $section->students()->get()[5];
        $rows = \App\Services\Honors::rows(collect([$section]), 1);
        $this->assertTrue($rows->where('failed', true)->isNotEmpty(), 'demo has a failing student');
        $failedIds = $rows->where('failed', true)->map(fn ($x) => $x['enrollment']->student_id);
        $this->assertTrue($r->viewData('top')->pluck('enrollment.student_id')->intersect($failedIds)->isEmpty(), 'failing students are not on the public list');
        $this->assertTrue($r->viewData('bestSubject')->flatten(1)->pluck('enrollment.student_id')->intersect($failedIds)->isEmpty());
        $this->get("/honors?section_id={$section->id}&scope=level&term=1")->assertOk()->assertSee('all sections');

        $student = $section->students()->first();
        $this->post('/honors/nominations', ['award_id' => Award::first()->id, 'student_id' => $student->id, 'reason' => 'Great'])->assertRedirect();
        $n = AwardNomination::latest('id')->first();
        $this->assertSame('pending', $n->status);
        $this->actingAs(User::where('role', 'admin')->first());
        $this->post("/honors/nominations/{$n->id}", ['action' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $n->fresh()->status);
        \App\Models\Setting::put('honors_top_n', '3');
        $this->get("/honors?section_id={$section->id}&term=1")->assertOk()->assertSee('Top 3');
    }
}
