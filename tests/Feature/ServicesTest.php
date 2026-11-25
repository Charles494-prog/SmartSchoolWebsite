<?php

namespace Tests\Feature;

use App\Models\SchoolService;
use App\Models\ServiceRequest;
use App\Models\StudentAccount;
use App\Models\User;
use App\Support\Nav;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_student_requests_a_tor_pays_and_registrar_releases(): void
    {
        $tor = SchoolService::where('name', 'like', 'Transcript%')->first();
        $account = StudentAccount::with('enrollment.student')->get()->first(fn ($a) => $a->enrollment->student->user_id);
        $student = $account->enrollment->student;
        $before = $account->balance();
        $this->actingAs($student->user)->get('/portal/services')->assertOk()->assertSee('Transcript');
        $this->post("/portal/services/{$student->id}", ['school_service_id' => $tor->id, 'copies' => 1, 'purpose' => 'College'])->assertSessionHas('ok');
        $this->assertEqualsWithDelta($before + $tor->fee, $account->fresh()->balance(), 0.01);
        $req = ServiceRequest::latest('id')->first();

        $reg = User::where('role', 'registrar')->first();
        $this->actingAs($reg)->get('/requests')->assertOk()->assertSee('Transcript');
        $this->post("/requests/{$req->id}", ['status' => 'released'])->assertStatus(422);   // not paid
        $this->post("/requests/{$req->id}", ['status' => 'ready'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'Not paid yet'));
        $this->post("/requests/{$req->id}/notify")->assertSessionHas('ok');
        $this->assertTrue($student->user->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), 'still unpaid')));
        $this->actingAs(User::where('role', 'cashier')->first())->post("/finance/accounts/{$account->enrollment_id}/pay", ['amount' => $tor->fee, 'method' => 'cash', 'paid_on' => today()->toDateString(), 'pay_key' => 'order:'.$req->account_charge_id]);
        $this->assertTrue($reg->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), 'ready for releasing')), 'registrar told it is paid');
        $this->assertTrue($student->user->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), 'paid and ready')), 'family told automatically once paid + ready');
        $this->assertSame(0, ServiceRequest::whereIn('status', ['requested', 'processing'])->whereKey($req->id)->count());
        $this->actingAs($reg)->post("/requests/{$req->id}/notify")->assertSessionHas('ok', fn ($m) => str_starts_with($m, 'Reminded'));
        $this->post("/requests/{$req->id}", ['status' => 'released'])->assertSessionHas('ok');
        $this->assertSame('released', $req->fresh()->status);
    }

    public function test_registrar_has_no_attendance_or_teaching_and_librarian_no_student_list(): void
    {
        $labels = fn ($email) => collect(Nav::for(User::where('email', $email)->first()))->flatten(1)->pluck('label')->all();
        $this->assertNotContains('QR scanner', $labels('registrar@school.test'));
        $this->assertNotContains('Honor roll', $labels('registrar@school.test'));
        $this->assertContains('Grade approvals', $labels('registrar@school.test'));
        $this->assertNotContains('Students', $labels('librarian@school.test'));
    }

    public function test_student_can_add_at_most_two_parent_accounts(): void
    {
        $student = User::where('email', 'student@school.test')->first();
        $this->actingAs($student)->post('/welcome', ['parent_email' => 'p2@example.com', 'parent_name' => 'P2', 'parent_phone' => '0917'])->assertSessionHasNoErrors()->assertSessionHas('ok', fn ($m) => str_contains($m, 'p2@'));
        $this->assertSame(2, $student->student->guardians()->whereNotNull('user_id')->count());
        $this->post('/welcome', ['parent_email' => 'p3@example.com', 'parent_name' => 'P3'])->assertStatus(422);
    }
}
