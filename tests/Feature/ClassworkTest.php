<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_teacher_sees_class_cards_and_posts_a_project_online(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $class = ClassSubject::where('teacher_id', $teacher->id)->first();

        $this->get('/assignments')->assertOk()->assertSee('Class cards')->assertSee($class->subject->name);
        $this->get('/assignments/class/'.$class->id)->assertOk()->assertSee('Classwork')->assertSee('Quarter project');
        $this->assertTrue(Assignment::where('class_subject_id', $class->id)->whereHas('submissions')->exists(), 'demo data has student work');

        $this->post('/assignments', ['class_subject_id' => $class->id, 'type' => 'project', 'title' => 'Science fair', 'due_at' => now()->addWeek()->format('Y-m-d H:i'), 'max_score' => 50])
            ->assertRedirect('/assignments/class/'.$class->id);
        $this->assertSame('project', Assignment::where('title', 'Science fair')->value('type'));

        $other = ClassSubject::where('teacher_id', '!=', $teacher->id)->first();
        $this->get('/assignments/class/'.$other->id)->assertForbidden();
    }

    public function test_lan_mode_has_no_class_cards_only_the_gradebook(): void
    {
        Setting::put('deployment_mode', 'lan');
        $this->actingAs(User::where('email', 'teacher@school.test')->first());

        $this->get('/assignments')->assertRedirect(route('gradebook.index'));
        $this->get('/assignments/class/'.ClassSubject::first()->id)->assertNotFound();
        $this->get('/dashboard')->assertOk()->assertDontSee('Class cards');
    }

    public function test_switching_lan_and_online_back_and_forth_keeps_every_page_working(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        foreach (['lan', 'hosted', 'lan', 'hosted'] as $mode) {
            Setting::put('deployment_mode', $mode);
            $this->actingAs($teacher);
            foreach (['/dashboard', '/gradebook', '/gradebook/'.ClassSubject::where('teacher_id', $teacher->id)->value('id')] as $url) {
                $this->get($url)->assertOk();
            }
            $this->get('/assignments')->assertStatus($mode === 'lan' ? 302 : 200);
            $this->actingAs(User::where('email', 'student@school.test')->first());
            $this->get('/portal/grades')->assertOk();
            $this->get('/portal/assignments')->assertStatus($mode === 'lan' ? 404 : 200);
        }
        $this->assertSame(Assignment::count() > 0, true, 'classwork made in Online mode is kept while in LAN mode');
    }

    public function test_dev_mode_switches_mode_and_signs_in_as_demo_accounts(): void
    {
        $this->actingAs(User::where('email', 'teacher@school.test')->first());
        $this->post('/dev/mode', ['mode' => 'lan'])->assertNotFound();

        config(['smartschool.dev' => true]);
        $this->get('/dashboard')->assertSee('DEV ·');
        $this->post('/dev/mode', ['mode' => 'lan'])->assertRedirect();
        $this->assertTrue(\App\Support\School::isLan());
        $student = User::where('email', 'student@school.test')->first();
        $this->post('/dev/login', ['user_id' => $student->id])->assertRedirect('/');
        $this->assertAuthenticatedAs($student);
        $this->post('/dev/login', ['user_id' => User::where('email', 'not like', '%@school.test')->value('id') ?? 999999])->assertNotFound();
    }
}
