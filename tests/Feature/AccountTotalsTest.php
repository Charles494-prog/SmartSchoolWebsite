<?php

namespace Tests\Feature;

use App\Models\FeeItem;
use App\Models\StudentAccount;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    /** Fee lines minus discounts (plus any installment surcharge) must equal Total due – on every account. */
    public function test_fee_lines_always_add_up_to_total_due(): void
    {
        foreach (StudentAccount::with('fees', 'discounts')->get() as $a) {
            $surcharge = round($a->net_amount - ($a->gross_amount - $a->discount_amount), 2);
            $this->assertEqualsWithDelta($a->net_amount, $a->fees->sum('amount') - $a->discounts->sum('amount') + $surcharge, 0.01, "account {$a->id}");
        }
    }

    public function test_changing_a_grades_fees_later_does_not_change_what_a_student_was_charged(): void
    {
        $a = StudentAccount::with('enrollment')->first();
        $before = $a->fees()->sum('amount');
        FeeItem::where('category', 'tuition')->update(['amount' => 0]);
        $this->assertEqualsWithDelta($before, $a->fresh()->fees()->sum('amount'), 0.01);
        $this->assertEqualsWithDelta($a->gross_amount, $a->fees()->sum('amount'), 0.01);
        $this->actingAs(User::where('role', 'cashier')->first())->get('/finance/accounts/'.$a->enrollment_id)->assertOk();
    }
}
