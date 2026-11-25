<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Nav;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_each_role_gets_its_own_setup_and_cannot_hide_required_features(): void
    {
        $student = User::where('email', 'student@school.test')->first();
        $this->actingAs($student)->get('/welcome')->assertOk()->assertSee('Your parents or guardian')->assertSee('to-do list')->assertDontSee('School year');
        $this->actingAs(User::where('email', 'teacher@school.test')->first())->get('/welcome')->assertOk()->assertSee('Tell me when students turn in work')->assertDontSee('Your parents or guardian');
        $this->actingAs(User::where('role', 'admin')->first())->get('/welcome')->assertNotFound();

        $guard = User::where('role', 'guard')->first();
        $this->actingAs($guard)->post('/welcome', ['hidden' => ['Gate scanner']])->assertRedirect();
        $this->assertContains('Gate scanner', collect(Nav::for($guard->fresh()))->flatten(1)->pluck('label')->all(), 'required features stay');
        $this->assertNotNull($guard->fresh()->onboarded_at);
    }

    public function test_student_hides_a_feature_turns_on_live_reports_and_invites_a_parent(): void
    {
        $student = User::where('email', 'student@school.test')->first();
        $this->actingAs($student)->post('/welcome', ['hidden' => ['Attendance'], 'live_reports' => 1, 'todo_home' => 1, 'parent_name' => 'Lorna Santos', 'parent_email' => 'lorna@example.com', 'parent_relationship' => 'Mother'])
            ->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, 'temporary password'));
        $student->refresh();
        $this->assertNotContains('Attendance', collect(Nav::for($student))->flatten(1)->pluck('label')->all());
        $this->assertTrue($student->pref('live_reports'));
        $parent = User::where('email', 'lorna@example.com')->first();
        $this->assertSame('parent', $parent->role);
        $this->assertTrue($parent->children()->get()->contains('id', $student->student->id));

        \App\Support\LiveReports::send($student->student, 'Quiz 9', '18/20');
        $this->assertTrue($parent->notifications()->exists(), 'parent got the live score');
    }
}
