<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\PaymentChannel;
use App\Models\PaymentProof;
use App\Models\SchoolService;
use App\Models\ServiceRequest;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use App\Services\Tuition;
use App\Support\DemoNames;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Storage::fake('local');
    }

    public function test_cashier_cannot_backdate_a_payment(): void
    {
        $account = StudentAccount::get()->first(fn ($a) => $a->balance() > 600);
        $this->actingAs(User::where('role', 'cashier')->first())
            ->post("/finance/accounts/{$account->enrollment_id}/pay", ['amount' => 500, 'method' => 'cash', 'paid_on' => '2020-01-01', 'pay_key' => 'other'])
            ->assertSessionHasNoErrors();
        $this->assertSame(today()->toDateString(), $account->payments()->latest('id')->first()->paid_on->toDateString());
    }

    public function test_admin_payment_page_has_multi_select_and_no_date_input(): void
    {
        $e = StudentAccount::get()->first(fn ($a) => $a->balance() > 0)->enrollment;
        $this->actingAs(User::where('role', 'admin')->first())->get("/finance/accounts/{$e->id}")
            ->assertOk()->assertSee('Select all')->assertDontSee('name="paid_on"', false);
    }

    public function test_second_online_payment_for_same_item_waits_for_finance(): void
    {
        $this->actingAs(User::where('role', 'admin')->first())->post('/payment-methods', ['name' => 'GCash', 'kind' => 'gcash', 'account_name' => 'X', 'account_number' => '0917', 'qr' => UploadedFile::fake()->image('qr.png')]);
        $ch = PaymentChannel::first();
        $tor = SchoolService::where('name', 'like', 'Transcript%')->first();
        $account = StudentAccount::with('enrollment.student')->get()->first(fn ($a) => $a->enrollment->student->user_id);
        $student = $account->enrollment->student;
        $this->actingAs($student->user)->post("/portal/services/{$student->id}", ['school_service_id' => $tor->id, 'copies' => 1, 'purpose' => 'College']);
        $key = 'order:'.ServiceRequest::latest('id')->first()->account_charge_id;
        $pay = fn () => $this->post("/portal/pay/{$student->id}", ['payment_channel_id' => $ch->id, 'amount' => $tor->fee, 'reference' => 'R'.uniqid(), 'pay_key' => $key, 'paid_on' => '2020-01-01', 'screenshot' => UploadedFile::fake()->image('r.jpg')]);

        $pay()->assertSessionHas('ok');
        $this->assertSame(today()->toDateString(), PaymentProof::first()->paid_on->toDateString());
        $pay()->assertSessionHasErrors('pay_key');
        $this->assertSame(1, PaymentProof::count());

        $this->actingAs(User::where('role', 'cashier')->first())->post('/finance/online/'.PaymentProof::first()->id, ['action' => 'reject', 'note' => 'Blurry']);
        $this->actingAs($student->user);
        $pay()->assertSessionHas('ok');
    }

    public function test_notify_while_being_prepared_says_being_prepared(): void
    {
        $tor = SchoolService::where('name', 'like', 'Transcript%')->first();
        $student = Student::whereNotNull('user_id')->first();
        $this->actingAs($student->user)->post("/portal/services/{$student->id}", ['school_service_id' => $tor->id, 'copies' => 1]);
        $req = ServiceRequest::latest('id')->first();
        $this->actingAs(User::where('role', 'registrar')->first());
        $this->post("/requests/{$req->id}", ['status' => 'processing']);
        $student->user->notifications()->delete();
        $this->post("/requests/{$req->id}/notify")->assertSessionHas('ok', fn ($m) => str_contains($m, 'being prepared'));
        $data = json_encode($student->user->notifications()->first()->data);
        $this->assertStringContainsString('being prepared', $data);
        $this->assertStringNotContainsString('document is ready', $data);
        $this->get('/requests?status=processing')->assertOk()->assertSee('Mark papers ready');
    }

    public function test_unsectioned_warning_links_to_a_page_that_fixes_it(): void
    {
        $e = Enrollment::where('status', 'enrolled')->whereNotNull('section_id')->first();
        $section = $e->section;
        $e->update(['section_id' => null]);
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get('/sections')->assertSee('Set it here')->assertSee(route('sections.unsectioned'), false);
        $this->get('/sections/unsectioned')->assertOk()->assertSee($e->student->full_name);
        $this->post('/sections/unsectioned', ['place' => [$e->id => $section->id]])->assertSessionHas('ok');
        $this->assertSame($section->id, $e->fresh()->section_id);
    }

    public function test_public_school_removes_tuition_from_accounts(): void
    {
        $account = StudentAccount::whereHas('fees', fn ($q) => $q->where('category', 'tuition'))->first();
        Tuition::save('public', 'same', null, []);
        $this->assertSame(0, $account->fresh()->fees()->where('category', 'tuition')->count());
    }

    public function test_demo_names_are_unique(): void
    {
        $names = collect(range(0, 1999))->map(fn ($n) => implode(' ', [DemoNames::for($n)[0], DemoNames::for($n)[2]]));
        $this->assertSame($names->count(), $names->unique()->count());
        $this->assertSame(0, Student::get()->countBy(fn ($s) => $s->first_name.' '.$s->last_name)->filter(fn ($c) => $c > 1)->count());
    }
}
