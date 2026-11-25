<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\SetupWizardController;
use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Discount;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\FeeItem;
use App\Models\GradeLevel;
use App\Models\GradingRequest;
use App\Models\GuidanceRecord;
use App\Models\PaymentPlan;
use App\Models\Room;
use App\Models\ScheduleSlot;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Transfer;
use App\Models\User;
use App\Notifications\AttendanceAlert;
use App\Services\Attendance;
use App\Services\Billing;
use App\Services\Chat;
use App\Services\Grading;
use App\Support\School;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function as(string $email): User
    {
        $u = User::where('email', $email)->firstOrFail();
        $this->actingAs($u);

        return $u;
    }

    public function test_every_page_loads_for_every_role(): void
    {
        $student = Student::first();
        $enrollment = Enrollment::first();
        $class = ClassSubject::first();
        $pages = [
            'admin@school.test' => ['/dashboard', '/students', "/students/{$student->id}", '/students/create', '/applications', '/applications/'.Application::first()->id, '/sections', '/sections/1', '/promotion', '/timetable', '/timetable?section_id=1', '/timetable?room=Room%20101', '/timetable?room=__all', '/timetable?subject=English', '/timetable?teacher_id=2', '/timetable?room=__all&day=2', '/timetable?room=__all&print=1', '/sections?level=8', '/manage/transfers/1/edit', '/gradebook', "/gradebook/{$class->id}", '/honors', '/attendance/scan', '/attendance', '/attendance?section_id=1&date=2026-11-20', '/attendance/report', '/assignments', '/finance', "/finance/accounts/{$enrollment->id}", '/finance/payments', '/finance/overdue', '/announcements', '/calendar', '/messages', '/notifications', '/library', '/payroll', '/grade-approvals', '/allocation', '/sections/unsectioned', '/finance/bookstore', '/bookstore', '/manage/rooms', '/timetable?subject=English&print=1', '/settings', '/audit', '/backups', '/how-it-works', '/qr-cards',
                '/manage/rooms', '/manage/rooms/create', '/manage/school-years', '/manage/grade-levels', '/manage/subjects', '/manage/users', '/manage/fee-items', '/manage/discounts', '/manage/payment-plans', '/manage/payment-plans/create', '/manage/events', '/manage/transfers', '/manage/clinic-visits', '/manage/employees', '/manage/subjects/create', '/manage/users/1/edit',
                "/report-card/{$enrollment->id}", "/students/{$student->id}/sf10", "/students/{$student->id}/id-card", '/sections/1/id-cards', "/finance/soa/{$enrollment->id}"],
            'teacher@school.test' => ['/how-it-works', '/dashboard', '/gradebook', '/attendance', '/attendance/scan', '/attendance/report', '/honors', '/timetable', '/assignments', '/announcements', '/messages'],
            'parent@school.test' => ['/how-it-works', '/portal', '/portal/grades', '/portal/attendance', '/portal/schedule', '/portal/account', '/portal/assignments', '/messages', '/calendar', '/announcements'],
            'student@school.test' => ['/portal', '/portal/grades', '/portal/assignments'],
            'cashier@school.test' => ['/dashboard', '/finance', '/finance/overdue', '/finance/payments'],
            'guidance@school.test' => ['/manage/guidance-records', '/manage/guidance-records/create', "/students/{$student->id}"],
            'nurse@school.test' => ['/manage/clinic-visits/create'],
            'librarian@school.test' => ['/library'],
            'hr@school.test' => ['/payroll', '/manage/employees'],
        ];
        foreach ($pages as $email => $urls) {
            $this->as($email);
            foreach ($urls as $url) {
                $r = $this->get($url);
                $this->assertSame(200, $r->status(), $url.' -> '.$r->headers->get('Location'));
            }
        }
        foreach (['/login', '/apply', '/apply/status', '/privacy'] as $url) {
            auth()->logout();
            $this->get($url)->assertOk();
        }
    }

    public function test_parent_sees_only_own_child(): void
    {
        $parent = $this->as('parent@school.test');
        $own = $parent->children()->first();
        $other = Student::where('id', '!=', $own->id)->first();
        $this->get("/students/{$own->id}")->assertOk();
        $this->get("/students/{$other->id}")->assertForbidden();
        $this->get('/report-card/'.$other->enrollments()->first()->id)->assertForbidden();
        $this->get('/students')->assertForbidden();
        $this->get('/finance')->assertForbidden();
    }

    public function test_teacher_cannot_open_another_teachers_gradebook(): void
    {
        $t = $this->as('teacher@school.test');
        $other = ClassSubject::where('teacher_id', '!=', $t->id)->first();
        $this->get("/gradebook/{$other->id}")->assertForbidden();
    }

    public function test_guidance_records_hidden_from_admin(): void
    {
        GuidanceRecord::create(['student_id' => Student::first()->id, 'recorded_on' => today(), 'type' => 'counseling', 'title' => 'x', 'notes' => 'secret']);
        $this->as('admin@school.test');
        $this->get('/manage/guidance-records')->assertForbidden();
        $this->as('guidance@school.test');
        $this->get('/manage/guidance-records')->assertOk()->assertSee('x');
    }

    public function test_grade_changes_are_audited_and_locked_after_submission(): void
    {
        $t = $this->as('teacher@school.test');
        $cs = ClassSubject::where('teacher_id', $t->id)->first();
        $a = $cs->assessments()->where('term', 2)->first();
        $student = $cs->section->students()->first();
        $before = Score::where('assessment_id', $a->id)->where('student_id', $student->id)->value('score');
        $this->post("/gradebook/{$cs->id}/scores", ['term' => 2, 'scores' => [$a->id => [$student->id => 7]]])->assertRedirect();
        $log = AuditLog::where('auditable_type', 'Score')->where('action', 'updated')->latest('id')->first();
        $this->assertSame($t->id, $log->user_id);
        $this->assertEquals($before, $log->old_values['score']);
        $this->assertEquals(7, $log->new_values['score']);

        // Out of range score rejected
        $this->post("/gradebook/{$cs->id}/scores", ['term' => 2, 'scores' => [$a->id => [$student->id => 999]]])->assertSessionHasErrors();

        // Term 1 is approved → locked
        $a1 = $cs->assessments()->where('term', 1)->first();
        $this->post("/gradebook/{$cs->id}/scores", ['term' => 1, 'scores' => [$a1->id => [$student->id => 1]]])->assertForbidden();
    }

    public function test_online_application_to_enrollment(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->post('/apply', [
            'grade_level_id' => GradeLevel::where('name', 'Grade 8')->value('id'), 'applicant_type' => 'new',
            'first_name' => 'Lea', 'last_name' => 'Salonga', 'sex' => 'Female', 'birthdate' => '2012-02-22', 'address' => 'Manila',
            'guardian_name' => 'Mama Salonga', 'guardian_relationship' => 'Mother', 'guardian_phone' => '09171234567', 'guardian_email' => 'mama.salonga@example.com',
            'documents' => ['birth_certificate' => UploadedFile::fake()->create('psa.pdf', 100, 'application/pdf')], 'consent' => '1',
        ])->assertRedirect();
        $app = Application::where('last_name', 'Salonga')->firstOrFail();
        $this->assertCount(1, $app->documents);

        $this->as('registrar@school.test');
        $this->post("/applications/{$app->id}/decide", ['decision' => 'approve'])->assertRedirect();
        $student = Student::where('last_name', 'Salonga')->firstOrFail();
        $this->assertTrue($student->guardians()->first()->user->hasRole('parent'));
        $this->assertSame('approved', $app->fresh()->status);
        $this->assertNotNull($student->currentEnrollment());
    }

    public function test_application_requires_consent_and_valid_phone(): void
    {
        $this->post('/apply', ['first_name' => 'X', 'guardian_phone' => '123'])->assertSessionHasErrors(['consent', 'guardian_phone', 'last_name']);
    }

    public function test_billing_discount_installments_and_payment(): void
    {
        $e = Enrollment::first();
        $sib = Discount::where('name', 'Sibling discount')->first();
        $e->account?->payments()->delete();
        $acc = Billing::assess($e, 'quarterly', [$sib->id]);
        // 25,000 tuition + 4,500 misc + 350 ID = 29,850; 10% of tuition = 2,500
        $this->assertSame(29850.0, $acc->gross_amount);
        $this->assertSame(2500.0, $acc->discount_amount);
        $this->assertSame(27350.0, $acc->net_amount);
        $this->assertCount(4, $acc->installments);
        $this->assertEqualsWithDelta(27350, $acc->installments->sum('amount'), 0.001);

        $this->as('cashier@school.test');
        $this->post("/finance/accounts/{$e->id}/pay", ['amount' => 999999, 'method' => 'cash', 'paid_on' => today()->toDateString()])->assertSessionHasErrors('amount');
        $paidBefore = $acc->paidAmount();
        $this->post("/finance/accounts/{$e->id}/pay", ['amount' => 1000, 'method' => 'gcash', 'reference' => 'GC123', 'paid_on' => today()->toDateString()])->assertRedirect();
        $this->assertEqualsWithDelta($paidBefore + 1000, $acc->fresh()->paidAmount(), 0.001);
    }

    public function test_qr_check_in_marks_late_and_notifies_parent(): void
    {
        Notification::fake();
        $this->as('teacher@school.test');
        Setting::put('late_after', '07:30');
        $s = Student::first();
        $r = Attendance::checkIn($s->qr_token, Carbon::today()->setTime(7, 50));
        $this->assertSame('late', $r['status']);
        Notification::assertSentTo($s->guardians()->first()->user, AttendanceAlert::class);
        $again = Attendance::checkIn($s->qr_token, Carbon::today()->setTime(7, 51));
        $this->assertSame('already', $again['status']);
        $this->assertFalse(Attendance::checkIn('nonsense')['ok']);
    }

    public function test_rooms_limit_capacity_and_block_double_booking(): void
    {
        $this->as('registrar@school.test');
        Room::create(['name' => 'Room 901', 'capacity' => 30, 'is_active' => true]);
        Room::create(['name' => 'Room 902', 'capacity' => 40, 'is_active' => true]);
        $sy = SchoolYear::current();
        $level = GradeLevel::where('name', 'Grade 9')->first();
        $this->post('/sections', ['name' => 'Luna', 'grade_level_id' => $level->id, 'school_year_id' => $sy->id, 'room' => 'Room 901', 'capacity' => 45])->assertSessionHasErrors('capacity');
        $this->post('/sections', ['name' => 'Luna', 'grade_level_id' => $level->id, 'school_year_id' => $sy->id, 'room' => 'Typed room', 'capacity' => 20])->assertSessionHasErrors('room');
        $this->post('/sections', ['name' => 'Luna', 'grade_level_id' => $level->id, 'school_year_id' => $sy->id, 'room' => 'Room 901', 'capacity' => 30, 'add_subjects' => 1])->assertRedirect();
        $luna = Section::where('name', 'Luna')->first();
        $this->assertSame('Room 901', $luna->room);

        // Rizal's home room is busy at its first class time; Luna cannot book it then.
        $rizal = Section::where('name', 'Rizal')->first();
        $busy = ScheduleSlot::whereHas('classSubject', fn ($q) => $q->where('section_id', $rizal->id))->where('day_of_week', 1)->orderBy('starts_at')->first();
        $start = substr($busy->starts_at, 0, 5);
        $end = substr($busy->ends_at, 0, 5);
        $cs = $luna->classSubjects()->whereNull('teacher_id')->first() ?? tap($luna->classSubjects()->first())->update(['teacher_id' => null]);
        $this->post("/classes/{$cs->id}/slots", ['days' => [1], 'starts_at' => $start, 'ends_at' => $end, 'room' => $rizal->room])->assertSessionHas('error');
        $this->assertSame(0, $cs->slots()->count());
        $this->post("/classes/{$cs->id}/slots", ['days' => [1], 'starts_at' => $start, 'ends_at' => $end, 'room' => 'Room 902'])->assertSessionHas('ok');
        $this->assertSame(1, $cs->slots()->count());
    }

    public function test_settings_toggles_save(): void
    {
        $this->as('admin@school.test');
        $base = collect(AdminController::SETTINGS)->flatMap(fn ($g) => $g)->map(fn ($v, $k) => Setting::get($k))->all();
        $this->put('/settings', array_merge($base, ['privacy_notice' => 'x', 'notify_late' => '0', 'use_transmutation' => '1', 'late_after' => '08:00']))->assertSessionHasNoErrors();
        $this->assertSame('0', Setting::get('notify_late'));
        $this->assertSame('1', Setting::get('use_transmutation'));
        $this->assertSame('08:00', Setting::get('late_after'));
    }

    public function test_chat_permissions(): void
    {
        $student = User::where('email', 'student@school.test')->first();
        $otherStudent = User::factory()->create(['role' => 'student'])->fresh();
        $teacher = User::where('role', 'teacher')->get()->first(fn ($t) => Chat::allowed($student, $t));
        $this->assertNotNull($teacher, 'student should be able to chat with one of their teachers');
        $this->assertFalse(Chat::allowed($student, $otherStudent));
        $this->assertTrue(Chat::allowed($student, User::where('role', 'cashier')->first()));
        $this->assertTrue(Chat::allowed($student, User::where('role', 'librarian')->first()));
        $this->assertTrue(Chat::allowed($student, User::where('role', 'nurse')->first()));
        $this->assertFalse(Chat::allowed($student, User::where('role', 'hr')->first()));
        $this->assertTrue(Chat::allowed($teacher, User::where('role', 'nurse')->first()));

        $this->actingAs($student);
        $this->postJson('/chat/start', ['to' => $otherStudent->id])->assertForbidden();
        $id = $this->postJson('/chat/start', ['to' => $teacher->id])->assertOk()->json('id');
        $this->postJson("/chat/$id", ['body' => 'Hello po'])->assertOk();
        $this->assertSame($id, $this->postJson('/chat/start', ['to' => $teacher->id])->json('id'), 'reuses the same chat');
        $this->assertFalse(collect($this->getJson('/chat/contacts')->json())->contains('id', $otherStudent->id));

        $this->actingAs($teacher);
        $this->getJson('/chat')->assertOk()->assertJsonPath('unread', 1);
        $this->getJson("/chat/$id")->assertOk()->assertJsonPath('0.body', 'Hello po');
        $this->actingAs($otherStudent);
        $this->getJson("/chat/$id")->assertForbidden();
    }

    public function test_transfer_direction_cannot_be_edited(): void
    {
        $this->as('registrar@school.test');
        $t = Transfer::first();
        $this->put("/manage/transfers/{$t->id}", ['direction' => 'out', 'student_id' => 999, 'school_name' => 'San Roque ES (fixed)', 'transfer_date' => '2026-06-16'])->assertRedirect();
        $t->refresh();
        $this->assertSame('in', $t->direction);
        $this->assertSame('San Roque ES (fixed)', $t->school_name);
    }

    public function test_class_qr_scan(): void
    {
        $t = $this->as('teacher@school.test');
        $cs = ClassSubject::where('teacher_id', $t->id)->first();
        $student = $cs->section->students()->first();
        $outsider = Student::whereDoesntHave('enrollments', fn ($e) => $e->where('section_id', $cs->section_id))->first();
        $this->postJson('/attendance/check-in', ['code' => $student->qr_token, 'class_id' => $cs->id])->assertJsonPath('ok', true);
        $this->postJson('/attendance/check-in', ['code' => $student->qr_token, 'class_id' => $cs->id])->assertJsonPath('status', 'already');
        $this->postJson('/attendance/check-in', ['code' => $outsider->qr_token, 'class_id' => $cs->id])->assertJsonPath('status', 'wrong_class');
        $this->assertSame(1, AttendanceRecord::where('class_subject_id', $cs->id)->count());
    }

    public function test_drag_and_drop_schedule_blocks_teacher_and_room_overlaps(): void
    {
        $this->as('registrar@school.test');
        $level = GradeLevel::where('name', 'Grade 7')->first();
        $sec = Section::create(['name' => 'Luna', 'grade_level_id' => $level->id, 'school_year_id' => SchoolYear::current()->id, 'room' => 'Room 106', 'capacity' => 30]);
        $busyTeacherClass = ClassSubject::whereHas('slots', fn ($q) => $q->where('day_of_week', 1)->where('starts_at', '07:30'))->whereNotNull('teacher_id')->first();
        $cs = ClassSubject::create(['section_id' => $sec->id, 'subject_id' => $level->subjects()->first()->id, 'teacher_id' => $busyTeacherClass->teacher_id]);
        // Teacher already teaches Monday 07:30 elsewhere
        $this->postJson("/classes/{$cs->id}/place", ['day' => 1, 'start' => '07:30', 'minutes' => 60])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'already teaches'));
        // Room 101 is busy Monday 07:30 with another section
        $free = ClassSubject::create(['section_id' => $sec->id, 'subject_id' => $level->subjects()->skip(1)->first()->id]);
        $this->postJson("/classes/{$free->id}/place", ['day' => 1, 'start' => '07:30', 'minutes' => 60, 'room' => 'Room 101'])->assertStatus(422);
        // A free time works, then moving it onto the busy time is refused
        $slot = $this->postJson("/classes/{$cs->id}/place", ['day' => 6, 'start' => '09:00', 'minutes' => 60])->assertOk()->json();
        $this->assertSame('10:00', $slot['end']);
        $this->patchJson("/slots/{$slot['id']}", ['day' => 1, 'start' => '07:30'])->assertStatus(422);
        $this->patchJson("/slots/{$slot['id']}", ['day' => 6, 'start' => '10:00', 'minutes' => 90])->assertOk()->assertJsonPath('end', '11:30');
        // Assigning a teacher who is busy at this subject's time is refused
        $other = ScheduleSlot::where('day_of_week', 1)->where('starts_at', '07:30')->first();
        $free->slots()->create(['day_of_week' => 1, 'starts_at' => '07:30', 'ends_at' => '08:30', 'room' => 'Room 106']);
        $this->put("/classes/{$free->id}", ['teacher_id' => $other->classSubject->teacher_id])->assertSessionHas('error');
        $this->assertNull($free->fresh()->teacher_id);
        $this->deleteJson("/slots/{$slot['id']}")->assertOk();
    }

    public function test_payment_plan_with_downpayment_and_manual_schedule(): void
    {
        $this->as('admin@school.test');
        $plan = PaymentPlan::create(['name' => '20% down + 4', 'installments' => 5, 'months_between' => 2, 'downpayment_percent' => 20, 'surcharge_percent' => 0]);
        $e = Enrollment::first();
        $e->account?->payments()->delete();
        $acc = Billing::assess($e, $plan->id);
        $this->assertCount(5, $acc->installments);
        $this->assertEqualsWithDelta($acc->net_amount * 0.2, $acc->installments->first()->amount, 0.01);
        $this->assertEqualsWithDelta($acc->net_amount, $acc->installments->sum('amount'), 0.01);

        // Manual schedule must add up to the total
        $this->post("/finance/accounts/{$e->id}/schedule", ['rows' => [['label' => 'A', 'due_on' => '2026-07-01', 'amount' => 100]]])->assertSessionHas('error');
        $half = round($acc->net_amount / 2, 2);
        $this->post("/finance/accounts/{$e->id}/schedule", ['rows' => [['label' => 'A', 'due_on' => '2026-07-01', 'amount' => $half], ['label' => 'B', 'due_on' => '2026-12-01', 'amount' => round($acc->net_amount - $half, 2)]]])->assertSessionHas('ok');
        $this->assertCount(2, $acc->fresh()->installments);
    }

    public function test_student_photo_upload_and_id_card(): void
    {
        Storage::fake('local');
        $this->as('registrar@school.test');
        $s = Student::first();
        $data = collect($s->only(['first_name', 'last_name', 'sex', 'lrn', 'status']))->put('birthdate', $s->birthdate->toDateString())->all();
        $this->put("/students/{$s->id}", $data + ['photo' => UploadedFile::fake()->image('me.jpg', 800, 1000)])->assertRedirect();
        $s->refresh();
        $this->assertNotNull($s->photo_path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($s->photo_path));
        $this->assertSame([600, 600], [$w, $h]);
        $this->get("/students/{$s->id}/photo")->assertOk();
        $this->get("/students/{$s->id}/id-card")->assertOk();
        $this->as('parent@school.test');
        $other = Student::where('id', '!=', User::where('email', 'parent@school.test')->first()->children()->first()->id)->first();
        $this->get("/students/{$other->id}/photo")->assertForbidden();
    }

    public function test_teacher_grading_change_needs_admin_approval(): void
    {
        $t = $this->as('teacher@school.test');
        $cs = ClassSubject::where('teacher_id', $t->id)->first();
        $before = $cs->weights();
        $this->post("/gradebook/{$cs->id}/weights", ['ww' => 20, 'pt' => 50, 'qa' => 20, 'at' => 5, 'reason' => 'x'])->assertSessionHasErrors('ww'); // 95%
        $this->post("/gradebook/{$cs->id}/weights", ['ww' => 20, 'pt' => 50, 'qa' => 20, 'at' => 10])->assertSessionHasErrors('reason');
        $this->post("/gradebook/{$cs->id}/weights", ['ww' => 20, 'pt' => 50, 'qa' => 20, 'at' => 10, 'reason' => 'Attendance matters'])->assertSessionHas('ok');
        $this->assertSame($before, $cs->fresh()->weights(), 'nothing changes before approval');
        $req = GradingRequest::where('class_subject_id', $cs->id)->firstOrFail();
        $this->post("/grading-requests/{$req->id}", ['action' => 'approve'])->assertForbidden(); // teacher cannot approve

        $this->as('admin@school.test');
        $this->get('/gradebook')->assertSee('Attendance matters');
        $this->post("/grading-requests/{$req->id}", ['action' => 'approve'])->assertSessionHas('ok');
        $this->assertSame(['ww' => 20, 'pt' => 50, 'qa' => 20, 'at' => 10], $cs->fresh()->weights());

        // Attendance now counts: a student with only absences in the period gets a lower grade than before.
        $sheet = Grading::sheet($cs->fresh(), 1);
        $this->assertNotEmpty($sheet);
        $this->get("/gradebook/{$cs->id}")->assertOk()->assertSee('Custom for this class');
        $this->delete("/gradebook/{$cs->id}/weights")->assertSessionHas('ok');
        $this->assertSame($before, $cs->fresh()->weights());
    }

    public function test_gate_in_and_out_and_scanner_permissions(): void
    {
        $guard = $this->as('guard@school.test');
        $s = Student::first();
        $this->get('/dashboard')->assertRedirect(route('attendance.scan'));
        $this->get('/attendance/scan')->assertOk()->assertSee('Gate scanner')->assertDontSee('Where are you scanning');
        $this->get('/students')->assertForbidden();
        $this->get('/gradebook')->assertForbidden();
        $cs = ClassSubject::first();
        $this->postJson('/attendance/check-in', ['code' => $s->qr_token, 'class_id' => $cs->id])->assertForbidden();

        $in = Attendance::checkIn($s->qr_token, Carbon::today()->setTime(7, 0));
        $this->assertSame('present', $in['status']);
        $this->assertSame('already', Attendance::checkIn($s->qr_token, Carbon::today()->setTime(7, 1))['status']); // accidental double scan
        $out = Attendance::checkIn($s->qr_token, Carbon::today()->setTime(16, 30));
        $this->assertSame('out', $out['status']);
        $this->assertSame('16:30:00', AttendanceRecord::where('student_id', $s->id)->whereDate('date', today())->whereNull('class_subject_id')->value('time_out'));
        $this->assertSame('already', Attendance::checkIn($s->qr_token, Carbon::today()->setTime(16, 40))['status']);

        // Teachers: only their own classes, never the gate, daily list only for their advisory section
        $t = $this->as('teacher@school.test');
        $this->postJson('/attendance/check-in', ['code' => $s->qr_token])->assertForbidden();
        $other = ClassSubject::where('teacher_id', '!=', $t->id)->first();
        $this->postJson('/attendance/check-in', ['code' => $s->qr_token, 'class_id' => $other->id])->assertForbidden();
        $advised = Section::where('adviser_id', $t->id)->first();
        $this->get('/attendance?section_id='.$advised->id)->assertOk()->assertSee('Show every day'); // adviser with history records
        $notAdvised = Section::where('adviser_id', '!=', $t->id)->first();
        $this->post('/attendance', ['section_id' => $notAdvised->id, 'date' => today()->toDateString(), 'status' => []])->assertForbidden();
    }

    public function test_employee_form_creates_login_with_role(): void
    {
        $this->as('admin@school.test');
        $base = ['employee_no' => 'EMP-900', 'first_name' => 'Rita', 'last_name' => 'Nurse', 'position' => 'School Nurse', 'employment_type' => 'regular', 'monthly_salary' => 20000, 'status' => 'active'];
        $this->post('/manage/employees', $base + ['login_email' => 'rita@school.test'])->assertSessionHasErrors('login_role');
        $this->post('/manage/employees', $base + ['login_email' => 'rita@school.test', 'login_role' => 'nurse'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'temporary password'));
        $e = Employee::where('employee_no', 'EMP-900')->first();
        $this->assertSame('nurse', $e->user->role);
        $this->assertTrue($e->user->must_change_password);
        $this->put("/manage/employees/{$e->id}", $base + ['login_email' => 'rita.n@school.test', 'login_role' => 'guard'])->assertSessionHas('ok');
        $this->assertSame(['rita.n@school.test', 'guard'], [$e->fresh()->user->email, $e->fresh()->user->role]);
        // HR cannot hand out the Administrator role
        $this->as('hr@school.test');
        $this->post('/manage/employees', array_merge($base, ['employee_no' => 'EMP-901', 'login_email' => 'x@school.test', 'login_role' => 'admin']))->assertSessionHasErrors('login_role');
    }

    public function test_tuition_setup_in_settings(): void
    {
        $this->as('admin@school.test');
        $base = collect(Setting::DEFAULTS)->only(['school_name', 'passing_grade', 'late_after', 'honors_with_highest', 'honors_with_high', 'honors_with', 'library_loan_days', 'library_fine_per_day', 'privacy_notice'])->map(fn ($v) => $v ?: 'x')->all();
        $base['late_after'] = '07:30';
        $levels = GradeLevel::pluck('id');
        $sy = SchoolYear::current();
        $tuition = fn () => FeeItem::where('school_year_id', $sy->id)->where('category', 'tuition')->whereNotNull('grade_level_id')->pluck('amount', 'grade_level_id');

        $this->put('/settings', $base + ['school_type' => 'private', 'tuition_mode' => 'same', 'tuition_all' => 30000])->assertSessionHasNoErrors();
        $this->assertSame([30000.0], $tuition()->unique()->values()->all());
        $this->assertCount($levels->count(), $tuition());

        $this->put('/settings', $base + ['school_type' => 'private', 'tuition_mode' => 'per_level', 'tuition' => [$levels[0] => 18000]])->assertSessionHasNoErrors();
        $this->assertEquals(18000, $tuition()[$levels[0]]);

        $this->put('/settings', $base + ['school_type' => 'public'])->assertSessionHasNoErrors();
        $this->assertSame([0.0], $tuition()->unique()->values()->all());
        $this->assertSame('public', Setting::get('school_type'));
    }

    public function test_first_run_creates_superadmin_only_once(): void
    {
        User::query()->delete();
        $this->get('/login')->assertRedirect('/setup');
        $data = ['school_name' => 'Rizal High', 'name' => 'Ana Cruz', 'email' => 'ana@school.test', 'password' => 'Secret12345', 'password_confirmation' => 'Secret12345'];
        $this->post('/setup', $data)->assertSessionHasErrors('written_down');
        $this->post('/setup', $data + ['written_down' => 1])->assertRedirect(route('setup.wizard'));
        $this->assertSame('admin', User::where('email', 'ana@school.test')->value('role'));
        $this->assertSame('Rizal High', Setting::get('school_name'));
        auth()->logout();
        $this->get('/setup')->assertNotFound();
        $this->get('/login')->assertOk();
    }

    public function test_setup_wizard_steps_save_and_can_be_skipped(): void
    {
        $this->as('admin@school.test');
        foreach (array_keys(SetupWizardController::STEPS) as $step) {
            $this->get("/setup-wizard/{$step}")->assertOk();
        }
        $this->post('/setup-wizard/year', ['name' => '2027-2028', 'enrollment_opens_on' => '2027-05-01', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-10'])->assertRedirect('/setup-wizard/levels');
        $this->assertSame('2027-2028', SchoolYear::current()->name);
        $this->post('/setup-wizard/rooms', ['rooms' => [['name' => 'Lab 1', 'capacity' => 30], ['name' => '', 'capacity' => 40]]]);
        $this->assertSame(30, Room::where('name', 'Lab 1')->value('capacity'));
        $this->post('/setup-wizard/subjects', ['ww' => 40, 'pt' => 40, 'qa' => 10])->assertSessionHasErrors('ww');
        $this->get('/setup-wizard/subjects')->assertSee('Attendance');
        $this->post('/setup-wizard/subjects', ['ww' => 25, 'pt' => 45, 'qa' => 20, 'at' => 10])->assertSessionHasNoErrors();
        $this->assertSame(['ww' => 25, 'pt' => 45, 'qa' => 20, 'at' => 10], Subject::first()->weights());
        $this->post('/setup-wizard/staff', ['people' => [['name' => 'Gina Guard', 'email' => 'gina@school.test', 'role' => 'guard']]])
            ->assertRedirect('/setup-wizard/staff')->assertSessionHas('wizard_accounts');
        $this->get('/setup-wizard/staff')->assertSee('gina@school.test')->assertSee('I wrote them down');
        $this->post('/setup-wizard/staff', ['people' => [['name' => '', 'email' => '', 'role' => 'teacher']]])->assertRedirect('/setup-wizard/sections');
        $gina = User::where('email', 'gina@school.test')->first();
        $this->post("/setup-wizard/staff/{$gina->id}/reset")->assertRedirect('/setup-wizard/staff');
        $this->get('/setup-wizard/staff')->assertSee('New temporary password made');
        $this->assertTrue(User::where('email', 'gina@school.test')->value('must_change_password'));
        $this->post('/setup-wizard/sections', ['sections' => [['name' => 'Zeta Wizard', 'grade_level_id' => GradeLevel::first()->id, 'room' => 'Lab 1']]]);
        $this->assertSame(30, Section::where('name', 'Zeta Wizard')->value('capacity'));
        $this->post('/setup-wizard/finish')->assertRedirect(route('dashboard'));
        $this->assertTrue(School::isSetUp());
        $this->as('teacher@school.test');
        $this->get('/setup-wizard')->assertForbidden();
    }

    public function test_profile_edit_name_bio_photo_and_password(): void
    {
        Storage::fake('local');
        $u = $this->as('teacher@school.test');
        $this->get('/profile')->assertOk();
        $this->put('/profile', ['name' => 'Ana Reyes', 'bio' => 'Grade 7 adviser', 'photo' => UploadedFile::fake()->image('me.jpg', 300, 400)])->assertSessionHas('ok');
        $u->refresh();
        $this->assertSame(['Ana Reyes', 'Grade 7 adviser'], [$u->name, $u->bio]);
        $this->get('/profile/photo/'.$u->id)->assertOk();
        $this->put('/profile/password', ['current_password' => 'wrong', 'password' => 'NewPass12345', 'password_confirmation' => 'NewPass12345'])->assertSessionHasErrors('current_password');
    }

    public function test_activity_log_reads_as_sentences(): void
    {
        $admin = $this->as('admin@school.test');
        $e = Enrollment::with('student', 'section')->whereNotNull('section_id')->first();
        $other = Section::where('school_year_id', $e->school_year_id)->where('id', '!=', $e->section_id)->first();
        $old = $e->section->name;
        $e->update(['section_id' => $other->id]);
        $this->get('/audit')->assertOk()->assertSee($admin->name)->assertSee('changed enrollment')
            ->assertSee($e->student->full_name)->assertSee($old)->assertSee($other->name)->assertDontSee('section_id');
        $this->get('/audit?action=updated&auditable_type=Enrollment')->assertOk();
    }

    public function test_forced_password_change_and_inactive_block(): void
    {
        $u = User::where('email', 'registrar@school.test')->first();
        $u->update(['must_change_password' => true]);
        $this->actingAs($u)->get('/dashboard')->assertRedirect('/password');
        $u->update(['must_change_password' => false, 'is_active' => false]);
        $this->actingAs($u)->get('/dashboard')->assertRedirect('/login');
    }
}
