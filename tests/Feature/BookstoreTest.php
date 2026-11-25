<?php

namespace Tests\Feature;

use App\Models\ShopItem;
use App\Models\ShopOrder;
use App\Models\StudentAccount;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookstoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_order_goes_on_the_balance_and_a_custom_payment_clears_the_order_first(): void
    {
        $this->actingAs(User::where('role', 'cashier')->first())->post('/finance/bookstore/items', ['name' => 'Uniform', 'category' => 'uniform', 'price' => 1200, 'sizes' => 'S, M, L', 'stock' => 5])->assertRedirect();
        $item = ShopItem::where('name', 'Uniform')->first();
        $this->assertSame(['S', 'M', 'L'], $item->sizes);

        $account = StudentAccount::with('enrollment.student')->get()->first(fn ($a) => $a->tuitionBalance() > 100 && $a->enrollment->student->user_id);
        $student = $account->enrollment->student->user;
        $before = $account->balance();
        $tuitionBefore = $account->tuitionBalance();
        $this->actingAs($student)->get('/portal/shop')->assertOk()->assertSee('Uniform');
        $this->post("/portal/shop/{$student->student->id}", ['cart' => [['id' => $item->id, 'qty' => 1, 'size' => 'M']]])->assertRedirect(route('portal.account'));
        $order = ShopOrder::latest('id')->first();
        $this->assertEqualsWithDelta($before + 1200, $account->fresh()->balance(), 0.01);
        $this->assertSame(4, $item->fresh()->stock);
        $this->get('/portal/account')->assertOk()->assertSee('What you owe')->assertSee('Bookstore order #'.$order->id);

        // the cashier takes a custom 1,210: 1,200 clears the uniform, 10 goes to tuition
        $cashier = User::where('role', 'cashier')->first();
        $this->actingAs($cashier)->post('/finance/accounts/'.$account->enrollment_id.'/pay', ['amount' => 1210, 'method' => 'cash', 'paid_on' => today()->toDateString(), 'pay_key' => 'other', 'purpose' => 'Partial payment'])->assertSessionHasNoErrors();
        $a = $account->fresh();
        $this->assertEqualsWithDelta(0, $order->charge->fresh()->remaining(), 0.01);
        $this->assertEqualsWithDelta(max(0, $tuitionBefore - 10), $a->tuitionBalance(), 0.01);

        $this->post("/finance/bookstore/orders/{$order->id}", ['status' => 'ready'])->assertRedirect();
        $this->post("/finance/bookstore/orders/{$order->id}", ['status' => 'cancelled'])->assertStatus(422);   // already paid
    }
}
