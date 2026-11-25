<?php

namespace Tests\Feature;

use App\Models\PaymentChannel;
use App\Models\PaymentProof;
use App\Models\StudentAccount;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Storage::fake('local');
    }

    public function test_parent_pays_online_and_cashier_approves_with_receipt(): void
    {
        $this->actingAs(User::where('role', 'admin')->first())->post('/payment-methods', ['name' => 'GCash', 'kind' => 'gcash', 'account_name' => 'Sampaguita IS', 'account_number' => '09171234567', 'qr' => UploadedFile::fake()->image('qr.png')])->assertRedirect();
        $ch = PaymentChannel::first();

        $account = StudentAccount::get()->first(fn ($a) => $a->balance() > 600);
        $kid = $account->enrollment->student;
        $parent = $kid->guardians()->first()->user;
        $before = $account->balance();
        $this->actingAs($parent)->get('/portal/account')->assertOk()->assertSee('Pay online')->assertSee('GCash');
        $this->post("/portal/pay/{$kid->id}", ['payment_channel_id' => $ch->id, 'amount' => 500, 'reference' => 'GC123', 'paid_on' => today()->toDateString(), 'screenshot' => UploadedFile::fake()->image('r.jpg')])
            ->assertSessionHas('ok');
        $proof = PaymentProof::first();

        $this->actingAs(User::where('role', 'cashier')->first())->get('/finance/online')->assertOk()->assertSee('GC123');
        $this->post("/finance/online/{$proof->id}", ['action' => 'approve'])->assertSessionHas('ok');
        $this->assertSame('approved', $proof->fresh()->status);
        $this->assertEqualsWithDelta($before - 500, $account->fresh()->balance(), 0.01);
        $this->assertNotNull($proof->fresh()->payment_id);
    }

    public function test_parent_without_pay_access_cannot_pay(): void
    {
        $parent = User::where('email', 'parent@school.test')->first();
        $kid = $parent->children()->first();
        $kid->guardians()->updateExistingPivot($kid->guardians()->where('user_id', $parent->id)->first()->id, ['access' => json_encode(['grades'])]);
        $this->actingAs($parent)->get('/portal/account')->assertOk()->assertSee('Not shared with you');
        $this->post("/portal/pay/{$kid->id}", ['amount' => 1])->assertForbidden();
        $this->get('/portal/grades')->assertOk()->assertDontSee('Not shared with you');
    }
}
