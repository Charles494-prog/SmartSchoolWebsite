<?php

namespace Tests\Feature;

use App\Http\Controllers\SetupWizardController;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\Score;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\Classwork;
use App\Support\School;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        $this->get('/assignments')->assertOk()->assertSee('My classes')->assertSee($class->subject->name);
        $this->get('/assignments/class/'.$class->id)->assertOk()->assertSee('Stream')->assertSee('Quarter project')->assertSee('Welcome to class!');
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

        config(['smartschool.dev' => true, 'smartschool.dev_password_hash' => Hash::make('secret-pass')]);
        $this->get('/dashboard')->assertDontSee('Developer mode');   // only the Superadmin sees dev tools
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get('/dashboard')->assertSee('Developer mode');
        $this->post('/dev/mode', ['mode' => 'lan'])->assertNotFound();   // locked until the password is given
        $this->post('/dev/unlock', ['password' => 'wrong'])->assertSessionHas('error');
        $this->post('/dev/unlock', ['password' => 'secret-pass'])->assertSessionHas('ok');
        $this->post('/dev/mode', ['mode' => 'lan'])->assertRedirect();
        $this->assertTrue(School::isLan());
        $student = User::where('email', 'student@school.test')->first();
        $this->post('/dev/login', ['user_id' => $student->id])->assertRedirect('/');
        $this->assertAuthenticatedAs($student);
        $this->post('/dev/login', ['user_id' => User::where('email', 'not like', '%@school.test')->where('role', '!=', 'admin')->value('id') ?? 999999])->assertNotFound();
        $this->post('/dev/setup')->assertRedirect(route('setup.wizard', 'welcome'));
        $this->assertSame('admin', auth()->user()->role, 'wizard runs as the Superadmin');
        $this->get(route('setup.wizard', 'subjects'))->assertOk()->assertSee('Top achievers to list');
        foreach (array_keys(SetupWizardController::steps()) as $step) {
            $this->get(route('setup.wizard', $step))->assertOk();
        }
    }

    public function test_classwork_feeds_the_gradebook_only_when_it_counts(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $class = ClassSubject::where('teacher_id', $teacher->id)->first();
        $base = ['class_subject_id' => $class->id, 'due_at' => now()->addDay()->format('Y-m-d H:i'), 'max_score' => 20];

        $this->post('/assignments', $base + ['type' => 'quiz', 'title' => 'Counted quiz', 'component' => 'ww', 'term' => 3])->assertRedirect();
        $this->post('/assignments', $base + ['type' => 'quiz', 'title' => 'Practice quiz', 'component' => ''])->assertRedirect();
        $counted = Assignment::where('title', 'Counted quiz')->first();
        $this->assertNotNull($counted->assessment_id);
        $this->assertSame(3, (int) Assessment::find($counted->assessment_id)->term);
        $this->assertNull(Assignment::where('title', 'Practice quiz')->value('assessment_id'));

        $student = $class->section->students()->first();
        $sub = $counted->submissions()->create(['student_id' => $student->id, 'body' => 'x', 'submitted_at' => now()]);
        $this->post("/submissions/{$sub->id}/grade", ['score' => 17])->assertRedirect();
        $this->assertEquals(17, Score::where('assessment_id', $counted->assessment_id)->where('student_id', $student->id)->value('score'));
        $this->get("/gradebook/{$class->id}?term=3")->assertOk()->assertSee('Counted quiz')->assertSee('Create in class card');

        // Q1 is approved in the demo data: new work can't count toward it
        $this->post('/assignments', $base + ['type' => 'quiz', 'title' => 'Late Q1', 'component' => 'ww', 'term' => 1])->assertSessionHas('error');
        $this->post('/assignments', ['class_subject_id' => $class->id, 'type' => 'announcement', 'title' => 'No class Friday'])->assertRedirect();
        $this->assertNull(Assignment::where('title', 'No class Friday')->value('due_at'));
    }

    public function test_student_class_cards_colour_and_sandboxed_teacher_content(): void
    {
        $student = User::where('email', 'student@school.test')->first();
        $this->actingAs($student);
        $class = ClassSubject::where('section_id', $student->student->currentEnrollment()->section_id)->first();
        $this->get('/portal/classes')->assertOk()->assertSee($class->subject->name);
        $this->get("/portal/classes/{$class->id}")->assertOk()->assertSee('Welcome to class!')->assertSee('Counts toward');
        $teacherColor = Classwork::colorKey($class);
        $pick = $teacherColor === 'rose' ? 'teal' : 'rose';
        $this->post("/portal/classes/{$class->id}/color", ['color' => $pick])->assertRedirect();
        $this->assertSame($pick, Classwork::colorKey($class, $student));
        $this->assertSame($teacherColor, Classwork::colorKey($class), 'teacher default is unchanged');

        $designed = Assignment::where('class_subject_id', $class->id)->whereNotNull('body_html')->first();
        $r = $this->get("/assignments/{$designed->id}/content")->assertOk();
        $this->assertStringContainsString('sandbox allow-scripts', $r->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('allow-same-origin', $r->headers->get('Content-Security-Policy'));
        $other = Assignment::whereHas('classSubject', fn ($q) => $q->where('section_id', '!=', $class->section_id))->first();
        $this->get("/assignments/{$other->id}/content")->assertForbidden();
    }

    public function test_admin_sets_trimesters_or_semesters(): void
    {
        $this->actingAs(User::where('role', 'admin')->first());
        $this->assertSame(4, School::periods());
        Assessment::where('term', '>', 2)->delete();
        $this->put('/settings', $this->settingsPayload(['grading_periods' => 2]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, School::periods());
        $this->assertSame('S2', School::term(2, 2));
        $this->get('/gradebook/'.ClassSubject::first()->id)->assertOk()->assertSee('S2')->assertDontSee('>Q3<', false);
    }

    private function settingsPayload(array $extra): array
    {
        $keys = ['school_name' => 'Test School', 'passing_grade' => 75, 'late_after' => '07:30', 'honors_with_highest' => 98, 'honors_with_high' => 95, 'honors_with' => 90,
            'library_loan_days' => 7, 'library_fine_per_day' => 5, 'privacy_notice' => 'We protect your data.', 'school_type' => 'public', 'tuition_mode' => 'same', 'tuition_all' => 0];

        return $extra + $keys;
    }

    public function test_student_and_parent_print_their_own_id_card(): void
    {
        foreach (['student@school.test', 'parent@school.test'] as $email) {
            $u = User::where('email', $email)->first();
            $this->actingAs($u);
            $kid = $u->role === 'student' ? $u->student : $u->children()->first();
            $this->get('/portal/id-card')->assertRedirect(route('students.id', $kid));
            $this->get(route('students.id', $kid))->assertOk();
            $other = Student::where('id', '!=', $kid->id)->first();
            $this->get(route('students.id', $other))->assertForbidden();
        }
    }

    public function test_teacher_edits_deletes_and_scores_paper_work(): void
    {
        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $class = ClassSubject::where('teacher_id', $teacher->id)->first();
        $this->post('/assignments', ['class_subject_id' => $class->id, 'type' => 'assignment', 'title' => 'Essay', 'due_at' => now()->addDay()->format('Y-m-d H:i'), 'max_score' => 20, 'component' => ''])->assertRedirect();
        $a = Assignment::where('title', 'Essay')->first();
        $this->get("/assignments/{$a->id}")->assertOk()->assertSee('Edit')->assertSee('Delete')->assertSee('turned in');

        $this->put("/assignments/{$a->id}", ['title' => 'Essay v2', 'max_score' => 25, 'due_at' => now()->addDays(2)->format('Y-m-d H:i'), 'component' => 'pt', 'term' => 3])->assertRedirect();
        $a->refresh();
        $this->assertSame('Essay v2', $a->title);
        $this->assertNotNull($a->assessment_id, 'now counts toward Projects – gradebook column created');

        $student = $class->section->students()->first();
        $this->post("/assignments/{$a->id}/students/{$student->id}/grade", ['score' => 22])->assertRedirect();
        $this->assertEquals(22, Score::where('assessment_id', $a->assessment_id)->where('student_id', $student->id)->value('score'));

        $this->put("/assignments/{$a->id}", ['title' => 'Essay v2', 'max_score' => 25, 'due_at' => now()->addDays(2)->format('Y-m-d H:i'), 'component' => ''])->assertRedirect();
        $this->assertNull($a->fresh()->assessment_id, 'not included any more – column removed');

        $this->delete("/assignments/{$a->id}")->assertRedirect();
        $this->assertNull(Assignment::find($a->id));
    }

    public function test_staff_enter_unique_lrns(): void
    {
        $this->actingAs(User::where('email', 'registrar@school.test')->first());
        [$a, $b] = Student::orderBy('id')->take(2)->get()->all();
        $this->get('/students/lrn?show=all')->assertOk()->assertSee('Enter LRNs')->assertSee('Missing LRN');
        $this->getJson('/students/lrn/check?lrn='.$a->lrn)->assertJson(['taken' => $a->full_name]);
        $this->getJson('/students/lrn/check?lrn='.$a->lrn.'&ignore='.$a->id)->assertJson(['taken' => null]);
        $this->post("/students/{$b->id}/lrn", ['lrn' => $a->lrn])->assertSessionHasErrors(['lrn' => 'This LRN already belongs to '.$a->full_name.'.']);
        $this->post("/students/{$b->id}/lrn", ['lrn' => '123456789012'])->assertSessionHasNoErrors();
        $this->assertSame('123456789012', $b->fresh()->lrn);
        $this->actingAs(User::where('email', 'student@school.test')->first())->get('/students/lrn')->assertForbidden();
    }

    public function test_designed_announcements_with_picture_event_and_seen_by(): void
    {
        Storage::fake('local');
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->post('/announcements', ['title' => 'Family Day', 'body' => 'Join us!', 'audience' => 'all', 'event_at' => '2026-12-12 09:00',
            'image' => UploadedFile::fake()->image('fam.jpg'), 'image_caption' => 'Last year', 'body_html' => '<b class="x">Hi</b>'])->assertRedirect();
        $a = Announcement::where('title', 'Family Day')->first();
        $this->assertNotNull($a->image_path);
        $r = $this->get("/announcements/{$a->id}/content")->assertOk();
        $this->assertStringContainsString('sandbox allow-scripts', $r->headers->get('Content-Security-Policy'));

        $teacher = User::where('email', 'teacher@school.test')->first();
        $this->actingAs($teacher);
        $this->post('/announcements', ['title' => 'x', 'body' => 'y', 'audience' => 'all'])->assertForbidden();
        $this->assertGreaterThan(0, Announcement::unreadCount($teacher));
        $this->get('/announcements')->assertOk()->assertSee('Family Day')->assertSee('New')->assertSee('Last year')->assertSee('December 12, 2026');
        $this->assertSame(0, Announcement::unreadCount($teacher), 'opening the page reads them');
        $this->actingAs($admin)->get('/announcements')->assertSee('Seen by 1');
    }
}
