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
}
