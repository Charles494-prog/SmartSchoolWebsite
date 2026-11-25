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
        $topStudent = $r->viewData('top')->first()['enrollment']->student;
        $this->actingAs($teacher)->get("/honors/student/{$topStudent->id}?term=1")->assertOk()->assertSee('Year so far')->assertSee('Quiz 1');
        $this->actingAs(User::where('email', 'student@school.test')->first())->get("/honors/student/{$topStudent->id}")->assertForbidden();
        $this->actingAs(User::where('role', 'admin')->first());
        \App\Models\Setting::put('honors_top_n', '3');
        $this->get("/honors?section_id={$section->id}&term=1")->assertOk()->assertSee('Top 3');
    }

    public function test_teacher_sees_only_their_advisory_and_can_add_awards(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $advisory = \App\Models\Section::where('adviser_id', $teacher->id)->first();
        $other = \App\Models\Section::where('adviser_id', '!=', $teacher->id)->first();
        $r = $this->get("/honors?section_id={$other->id}")->assertOk();
        $this->assertSame($advisory->id, $r->viewData('section')->id, 'falls back to the advisory class');
        $this->assertSame([$advisory->id], array_keys($r->viewData('sections')->all()));
        $this->post('/honors/awards', ['name' => 'Kindness Award'])->assertRedirect();
        $this->assertTrue(Award::where('name', 'Kindness Award')->exists());
        $this->post('/honors/nominations', ['award_id' => Award::first()->id, 'student_id' => $other->students()->first()->id])->assertForbidden();
    }

    public function test_whole_year_is_live_and_gradebook_explains_each_grade(): void
    {
        $this->assertSame(99.0, \App\Services\Grading::runningFinal([1 => 100, 2 => 98, 3 => null, 4 => null]));
        $this->assertNull(\App\Services\Grading::finalGrade([1 => 100, 2 => 98, 3 => null, 4 => null]), 'official final still waits for every period');
        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $section = \App\Models\Section::where('adviser_id', $teacher->id)->first();
        $r = $this->get("/honors?section_id={$section->id}&term=final")->assertOk()->assertSee('so far');
        $this->assertNotEmpty($r->viewData('top'), 'whole-year list shows before Q3/Q4 exist');
        $cs = ClassSubject::where('teacher_id', $teacher->id)->first();
        $this->get("/gradebook/{$cs->id}?term=1")->assertOk()->assertSee('grade is computed')->assertSee('Initial grade')->assertSee('Consultation mode');
    }

    public function test_play_awardees_perfect_attendance_and_honor_title_settings(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $section = \App\Models\Section::where('adviser_id', $teacher->id)->first();
        $r = $this->get("/honors?section_id={$section->id}&term=1")->assertOk()->assertSee('Play awardees')->assertSee('1st place only')->assertSee('And the Top 1 is')->assertSee('Awarding Ceremony')->assertSee('Plug into HDMI');
        $this->get("/honors?section_id={$section->id}&scope=level&term=1")->assertOk()->assertDontSee('▶ Play awardees');
        $this->post("/honors/sections/{$section->id}/top", ['n' => 5])->assertRedirect();
        $this->assertSame(5, $this->get("/honors?section_id={$section->id}&term=1")->viewData('n'));
        $other = \App\Models\Section::where('adviser_id', '!=', $teacher->id)->first();
        $this->post("/honors/sections/{$other->id}/top", ['n' => 5])->assertForbidden();
        $this->assertGreaterThanOrEqual(3, $r->viewData('perfect')->count(), 'demo has perfect attendance');
        $this->assertNotNull($r->viewData('top')->first()['honor']);

        \App\Models\Setting::put('honors_titles_when', 'year_end');
        $this->assertNull($this->get("/honors?section_id={$section->id}&term=1")->viewData('top')->first()['honor'], 'no titles on a quarter list');
        \App\Models\Setting::put('honors_titles_when', 'each_period');
        \App\Models\Setting::put('honors_titles_levels', (string) \App\Models\GradeLevel::where('name', 'Grade 12')->value('id'));
        $this->assertNull($this->get("/honors?section_id={$section->id}&term=1")->viewData('top')->first()['honor'], 'only Grade 12 gets titles');
    }
}
