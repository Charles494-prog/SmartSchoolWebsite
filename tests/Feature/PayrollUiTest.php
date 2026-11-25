<?php

namespace Tests\Feature;

use App\Models\Payroll;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_run_review_adjust_approve_payslips_paid(): void
    {
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get('/payroll')->assertOk()->assertSee('Run payroll');
        $this->post('/payroll', ['period_start' => '2026-11-01', 'period_end' => '2026-11-30'])->assertRedirect();
        $p = Payroll::latest('id')->first();
        $item = $p->items()->first();
        $net = $item->net_pay;
        $this->get("/payroll/{$p->id}")->assertOk()->assertSee('Approve payroll')->assertSee('Net pay');
        $this->get("/payroll/items/{$item->id}/payslip")->assertStatus(409);
        $f = $item->only(['basic_pay', 'allowances', 'sss', 'philhealth', 'pagibig', 'withholding_tax', 'other_deductions']);
        $this->put("/payroll/items/{$item->id}", ['other_deductions' => $item->other_deductions + 100] + $f)->assertSessionHas('ok');
        $this->assertEqualsWithDelta($net - 100, $item->fresh()->net_pay, 0.01);
        $this->post("/payroll/{$p->id}/finalize")->assertSessionHas('ok');
        $this->get("/payroll/items/{$item->id}/payslip")->assertOk();
        $this->post("/payroll/{$p->id}/paid")->assertSessionHas('ok');
        $this->assertSame('paid', $p->fresh()->status);
        $this->put("/payroll/items/{$item->id}", $f)->assertForbidden();
    }
}
