<?php

namespace Tests\Feature;

use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\User;
use App\Services\Grading;
use App\Support\GradeScale;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeScaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@school.test')->firstOrFail());
    }

    private function scale(string $preset, string $show = 'both'): array
    {
        return ['grade_scale' => GradeScale::preset($preset), 'grade_scale_show' => $show];
    }

    public function test_default_is_deped_numbers(): void
    {
        $this->assertSame('95', GradeScale::format(95));
        $this->assertSame('Outstanding', Grading::descriptor(95));
        $this->assertSame('Did Not Meet Expectations', Grading::descriptor(70));
    }

    public function test_college_scale_with_1_as_highest(): void
    {
        $this->post('/setup-wizard/subjects', ['ww' => 30, 'pt' => 50, 'qa' => 20, 'passing_grade' => 75] + $this->scale('college'))->assertSessionHasNoErrors();
        $this->assertSame('1.00 · 98', GradeScale::format(98));
        $this->assertSame('1.25', GradeScale::label(95));
        $this->assertSame('5.00', GradeScale::label(60));
        $this->assertSame('Failed', Grading::descriptor(60));
    }

    public function test_gpa_scale_scale_only_and_custom_rows(): void
    {
        $this->put(route('settings.update'), $this->settingsBase() + $this->scale('gpa4', 'scale'))->assertSessionHasNoErrors();
        $this->assertSame('4.0', GradeScale::format(96));
        $this->assertSame('3.0', GradeScale::format(84.5));

        $custom = ['grade_scale' => [['min' => 95, 'label' => '1.0', 'desc' => 'Top'], ['min' => 0, 'label' => '3.0', 'desc' => 'Rest']], 'grade_scale_show' => 'scale'];
        $this->put(route('settings.update'), $this->settingsBase() + $custom)->assertSessionHasNoErrors();
        $this->assertSame('1.0', GradeScale::format(95));
        $this->assertSame('3.0', GradeScale::format(94.99));
    }

    public function test_scale_must_start_at_zero_and_have_unique_rows(): void
    {
        $bad = ['grade_scale' => [['min' => 90, 'label' => 'A'], ['min' => 50, 'label' => 'B']], 'grade_scale_show' => 'scale'];
        $this->post('/setup-wizard/subjects', ['ww' => 30, 'pt' => 50, 'qa' => 20] + $bad)->assertSessionHasErrors('grade_scale');
        $dup = ['grade_scale' => [['min' => 0, 'label' => 'A'], ['min' => 0, 'label' => 'B']], 'grade_scale_show' => 'scale'];
        $this->post('/setup-wizard/subjects', ['ww' => 30, 'pt' => 50, 'qa' => 20] + $dup)->assertSessionHasErrors('grade_scale');
    }

    public function test_editor_shows_in_wizard_and_settings(): void
    {
        $this->get('/setup-wizard/subjects')->assertOk()->assertSee('Grading scale')->assertSee('4.0 GPA');
        $this->get(route('settings.edit'))->assertOk()->assertSee('Grading scale and passing score');
    }

    public function test_sections_step_allows_same_name_in_different_grades(): void
    {
        $this->get('/setup-wizard/sections')->assertOk()->assertSee('Quick add');
        [$a, $b] = GradeLevel::orderBy('sort_order')->take(2)->pluck('id')->all();
        $this->post('/setup-wizard/sections', ['sections' => [['name' => 'Section Z', 'grade_level_id' => $a], ['name' => 'Section Z', 'grade_level_id' => $b]]]);
        $this->assertSame(2, Section::where('name', 'Section Z')->where('school_year_id', SchoolYear::current()->id)->count());
    }

    public function test_students_step_explains_registrar_computer_in_both_modes(): void
    {
        foreach (['lan', 'hosted'] as $mode) {
            Setting::put('deployment_mode', $mode);
            $this->get('/setup-wizard/students')->assertOk()->assertSee('At school, near the Registrar')->assertSee('/apply');
        }
    }

    public function test_levels_step_renames_adds_levels_and_college_programs(): void
    {
        $levels = GradeLevel::orderBy('sort_order')->get();
        $g1 = $levels->firstWhere('name', 'Grade 1');
        $this->post('/setup-wizard/levels', [
            'offered' => $levels->pluck('id')->all(),
            'names' => [$g1->id => 'Grade One'],
            'new_levels' => [['name' => 'Nursery', 'department' => 'kinder']],
            'programs' => [['name' => 'BS Information Technology', 'code' => 'BSIT', 'years' => 4], ['name' => 'BS Nursing', 'code' => 'BSN', 'years' => 2]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Grade One', $g1->fresh()->name);
        $this->assertTrue(GradeLevel::where('name', 'Nursery')->exists());
        $y1 = GradeLevel::where('name', 'BSIT – 1st Year')->firstOrFail();
        $y4 = GradeLevel::where('name', 'BSIT – 4th Year')->firstOrFail();
        $this->assertSame('college', $y1->department);
        $this->assertSame('BSIT – 2nd Year', $y1->next->name);
        $this->assertNull($y4->next_grade_level_id); // never promoted into another program
        $this->assertNull(GradeLevel::where('name', 'BSN – 2nd Year')->value('next_grade_level_id'));
        $this->get('/setup-wizard/levels')->assertOk()->assertSee('College');
    }

    public function test_each_level_can_have_its_own_scale_and_passing_score(): void
    {
        $level = GradeLevel::where('name', 'Grade 12')->firstOrFail();
        $this->get('/setup-wizard/subjects?scale_level='.$level->id)->assertOk()->assertSee('Passing score for Grade 12');
        $this->post('/setup-wizard/subjects', ['ww' => 30, 'pt' => 50, 'qa' => 20, 'grade_scale_level' => $level->id, 'level_passing_grade' => 80] + $this->scale('college', 'scale'))->assertSessionHasNoErrors();
        $this->assertSame('1.25', GradeScale::format(95, $level->id));
        $this->assertSame('95', GradeScale::label(95)); // rest of the school unchanged
        $this->assertSame(80.0, GradeScale::passing($level->id));
        $this->assertSame(75.0, GradeScale::passing());
        $this->post('/setup-wizard/subjects', ['ww' => 30, 'pt' => 50, 'qa' => 20, 'grade_scale_level' => $level->id, 'grade_scale_reset' => 1])->assertSessionHasNoErrors();
        $this->assertSame('95', GradeScale::label(95, $level->id));
        $this->assertSame(75.0, GradeScale::passing($level->id));
    }

    public function test_section_can_add_several_subjects_or_create_a_new_one(): void
    {
        $section = Section::where('school_year_id', SchoolYear::current()->id)->firstOrFail();
        $section->classSubjects()->delete();
        $ids = Subject::where('grade_level_id', $section->grade_level_id)->take(2)->pluck('id')->all();
        $this->post(route('classes.store', $section), ['mode' => 'pick', 'subject_ids' => $ids])->assertSessionHas('ok', '2 subjects added.');
        $this->assertSame(2, $section->classSubjects()->count());
        $this->post(route('classes.store', $section), ['mode' => 'new', 'new_name' => 'Robotics', 'new_hours' => 2])->assertSessionHas('ok');
        $this->assertTrue($section->classSubjects()->whereHas('subject', fn ($q) => $q->where('name', 'Robotics')->where('grade_level_id', $section->grade_level_id))->exists());
        $this->post(route('classes.store', $section), ['mode' => 'new'])->assertSessionHasErrors('new_name');
        $this->get(route('sections.show', $section))->assertOk()->assertSee('Add subjects to this section');
    }

    private function settingsBase(): array
    {
        return collect(['school_name' => 'X', 'passing_grade' => 75, 'late_after' => '07:30', 'honors_with_highest' => 98, 'honors_with_high' => 95, 'honors_with' => 90,
            'library_loan_days' => 7, 'library_fine_per_day' => 5, 'privacy_notice' => 'p', 'tuition_mode' => 'per_level'])->all();
    }
}
