<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ClinicVisit;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\Admission;
use App\Support\School;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdmissionClinicStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function admin(): User
    {
        $u = User::where('email', 'admin@school.test')->firstOrFail();
        $this->actingAs($u);

        return $u;
    }

    private function application(array $docs = []): array
    {
        return [
            'grade_level_id' => GradeLevel::where('name', 'Grade 8')->value('id'), 'applicant_type' => 'new',
            'first_name' => 'Lea', 'last_name' => 'Docs', 'sex' => 'Female', 'birthdate' => '2012-02-22', 'address' => 'Manila',
            'guardian_name' => 'Mama Docs', 'guardian_relationship' => 'Mother', 'guardian_phone' => '09171234567', 'guardian_email' => 'mama.docs@example.com',
            'documents' => $docs, 'consent' => '1',
        ];
    }

    public function test_admin_customizes_required_documents_in_setup_and_apply_form_enforces_them(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->admin();
        $this->get('/setup-wizard/documents')->assertOk()->assertSee('Add document');
        $this->post('/setup-wizard/documents', ['docs' => [
            ['key' => 'birth_certificate', 'label' => 'PSA birth certificate', 'required' => '1'],
            ['key' => '', 'label' => 'Baptismal certificate', 'required' => '0'],
            ['key' => '', 'label' => '   ', 'required' => '1'],
        ]])->assertRedirect();

        $docs = Admission::documents();
        $this->assertSame(['birth_certificate', 'baptismal_certificate'], array_keys($docs));
        $this->assertTrue($docs['birth_certificate']['required']);

        auth()->logout();
        $this->get('/apply')->assertOk()->assertSee('PSA birth certificate')->assertSee('Baptismal certificate')->assertDontSee('Good moral certificate');
        $this->post('/apply', $this->application())->assertSessionHasErrors('documents.birth_certificate');
        $this->post('/apply', $this->application(['birth_certificate' => UploadedFile::fake()->create('psa.pdf', 50, 'application/pdf')]))->assertRedirect();
        $this->assertCount(1, Application::where('last_name', 'Docs')->firstOrFail()->documents);
    }

    public function test_settings_can_clear_the_document_list(): void
    {
        $this->admin();
        Admission::save([]);
        $this->assertSame([], Admission::documents());
    }

    public function test_clinic_visit_for_staff_member(): void
    {
        $this->admin();
        $emp = Employee::first() ?? Employee::create(['employee_no' => 'E-1', 'first_name' => 'Ana', 'last_name' => 'Staff', 'position' => 'Clerk', 'monthly_salary' => 1]);
        $base = ['visited_at' => now()->format('Y-m-d\TH:i'), 'complaint' => 'Headache'];
        $this->post('/manage/clinic-visits', $base)->assertSessionHasErrors(['student_id', 'employee_id']);
        $this->post('/manage/clinic-visits', $base + ['employee_id' => $emp->id, 'parent_notified' => '1'])->assertSessionHas('ok');
        $v = ClinicVisit::latest('id')->first();
        $this->assertSame($emp->id, $v->employee_id);
        $this->assertNull($v->student_id);
        $this->assertFalse($v->parent_notified);
        $this->get('/manage/clinic-visits')->assertOk()->assertSee($emp->full_name.' (staff)');
    }

    public function test_notify_parent_hidden_on_lan(): void
    {
        $this->admin();
        $this->get('/manage/clinic-visits/create')->assertOk()->assertSee('Notify parent');
        Setting::put('deployment_mode', School::LAN);
        $this->get('/manage/clinic-visits/create')->assertOk()->assertDontSee('Notify parent');
        $this->post('/manage/clinic-visits', ['student_id' => Student::first()->id, 'visited_at' => now()->format('Y-m-d\TH:i'), 'complaint' => 'Fever', 'parent_notified' => '1'])->assertSessionHas('ok');
        $this->assertFalse(ClinicVisit::latest('id')->first()->parent_notified);
    }

    public function test_employee_number_is_automatic(): void
    {
        $this->admin();
        $base = ['first_name' => 'Rita', 'last_name' => 'Auto', 'position' => 'Teacher I', 'employment_type' => 'regular', 'monthly_salary' => 20000, 'status' => 'active'];
        $this->post('/manage/employees', $base)->assertSessionHas('ok');
        $this->post('/manage/employees', ['last_name' => 'Auto2'] + $base)->assertSessionHas('ok');
        $nos = Employee::whereIn('last_name', ['Auto', 'Auto2'])->orderBy('id')->pluck('employee_no')->all();
        $prefix = 'EMP-'.now()->format('Y').'-';
        $this->assertStringStartsWith($prefix, $nos[0]);
        $this->assertSame((int) substr($nos[0], -4) + 1, (int) substr($nos[1], -4));
    }

    public function test_scanner_has_full_screen_mode(): void
    {
        $this->admin();
        $this->get('/attendance/scan')->assertOk()->assertSee('Full screen')->assertSee('requestFullscreen', false)->assertSee('The camera is blocked on this address')->assertSee('Start camera');
    }
}
