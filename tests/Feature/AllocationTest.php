<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\SectionAllocation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Allocator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_randomize_is_reproducible_balanced_and_respects_rules(): void
    {
        $students = [];
        for ($i = 1; $i <= 40; $i++) {
            $students[$i] = ['sex' => $i % 2 ? 'Male' : 'Female', 'ga' => 70 + $i % 30, 'family' => $i <= 2 ? 'g1' : null];
        }
        $cap = [10 => 20, 11 => 20];
        $opts = ['gender' => true, 'snake' => true, 'siblings' => true, 'apart' => [[5, 6]], 'together' => [[7, 8]]];
        $a = Allocator::randomize($students, $cap, [9 => 11], $opts, 42);
        $b = Allocator::randomize($students, $cap, [9 => 11], $opts, 42);
        $this->assertSame($a['plan'], $b['plan']);
        $this->assertCount(40, $a['plan']);
        $counts = array_count_values($a['plan']);
        $this->assertSame([10 => 20, 11 => 20], [10 => $counts[10], 11 => $counts[11]]);
        $this->assertSame($a['plan'][1], $a['plan'][2]);   // siblings
        $this->assertSame($a['plan'][7], $a['plan'][8]);   // together
        $this->assertNotSame($a['plan'][5], $a['plan'][6]); // apart
        $this->assertSame(11, $a['plan'][9]);              // locked
        $stats = Allocator::stats($a['plan'], $students, [10, 11]);
        $this->assertLessThanOrEqual(2, abs($stats[10]['male'] - $stats[10]['female']));
        $this->assertEmpty($a['warnings']);
    }

    public function test_wizard_random_commit_and_undo(): void
    {
        $g7 = GradeLevel::where('name', 'Grade 7')->first();
        $sy = SchoolYear::current();
        $before = Enrollment::where('grade_level_id', $g7->id)->where('school_year_id', $sy->id)->pluck('section_id', 'id')->all();
        $this->actingAs(User::where('role', 'registrar')->first());
        $this->get('/allocation')->assertOk()->assertSee('Which grade level?');
        $this->post('/allocation', ['school_year_id' => $sy->id, 'grade_level_id' => $g7->id])->assertRedirect();
        $a = SectionAllocation::first();
        $this->get("/allocation/{$a->id}/step/2")->assertOk()->assertSee('Add a section');
        $this->get("/allocation/{$a->id}/step/3")->assertOk()->assertSee('Randomize');
        $this->post("/allocation/{$a->id}/method", ['method' => 'random', 'gender' => 1, 'snake' => 1, 'siblings' => 1])->assertRedirect(route('allocation.step', [$a, 4]));
        $this->get("/allocation/{$a->id}/step/4")->assertOk()->assertSee('Reshuffle');
        $this->get("/allocation/{$a->id}/step/5")->assertOk()->assertSee('Confirm allocation');
        $this->post("/allocation/{$a->id}/commit")->assertRedirect();
        $this->assertSame('committed', $a->fresh()->status);
        $this->post("/allocation/{$a->id}/undo")->assertSessionHas('ok');
        $this->assertSame($before, Enrollment::where('grade_level_id', $g7->id)->where('school_year_id', $sy->id)->pluck('section_id', 'id')->all());
    }

    public function test_student_choice_is_online_only_and_first_come_first_served(): void
    {
        $g7 = GradeLevel::where('name', 'Grade 7')->first();
        $sy = SchoolYear::current();
        $this->actingAs(User::where('role', 'registrar')->first());
        $this->post('/allocation', ['school_year_id' => $sy->id, 'grade_level_id' => $g7->id]);
        $a = SectionAllocation::first();

        Setting::put('deployment_mode', 'lan');
        $this->post("/allocation/{$a->id}/method", ['method' => 'choice', 'deadline' => now()->addDay()->format('Y-m-d H:i')])->assertStatus(422);

        Setting::put('deployment_mode', 'hosted');
        $this->post("/allocation/{$a->id}/method", ['method' => 'choice', 'deadline' => now()->addDay()->format('Y-m-d H:i')])->assertSessionHas('ok');
        $this->assertSame('open', $a->fresh()->status);

        $e = Enrollment::where('grade_level_id', $g7->id)->whereHas('student', fn ($q) => $q->whereNotNull('user_id'))->first();
        $full = $a->sections()->first();
        $full->update(['capacity' => $full->enrollments()->count()]);
        $other = $a->sections()->where('id', '!=', $full->id)->first();
        $this->actingAs($e->student->user)->get('/portal/section-choice')->assertOk()->assertSee('Choose your section');
        if ($e->section_id !== $full->id) {
            $this->post('/portal/section-choice', ['section_id' => $full->id])->assertSessionHas('error');
        }
        $this->post('/portal/section-choice', ['section_id' => $other->id])->assertSessionHas('ok');
        $this->assertSame($other->id, $e->fresh()->section_id);

        $this->actingAs(User::where('role', 'registrar')->first())->post("/allocation/{$a->id}/close")->assertRedirect();
        $this->assertSame('draft', $a->fresh()->status);
        $this->assertSame($other->id, (int) $a->fresh()->plan[$e->id]);
    }
}
