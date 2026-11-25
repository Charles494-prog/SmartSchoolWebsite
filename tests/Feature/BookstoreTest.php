<?php

namespace Tests\Feature;

use App\Models\AccountCharge;
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
        $store = User::create(['name' => 'Book Store', 'email' => 'store@school.test', 'password' => 'Demo12345', 'role' => 'bookstore', 'is_active' => true]);
        $this->actingAs(User::where('role', 'cashier')->first())->get('/bookstore')->assertForbidden();
        $this->actingAs($store)->post('/bookstore/items', ['name' => 'Uniform', 'category' => 'uniform', 'price' => 1200, 'sizes' => 'S, M, L', 'stock' => 5])->assertRedirect();
        $item = ShopItem::where('name', 'Uniform')->first();
        $this->assertSame(['S', 'M', 'L'], $item->sizes);

        $account = StudentAccount::with('enrollment.student')->get()->first(fn ($a) => $a->tuitionBalance() > 100 && $a->enrollment->student->user_id);
        $student = $account->enrollment->student->user;
        $before = $account->balance();
        $tuitionBefore = $account->tuitionBalance();
        $this->actingAs($student)->get('/portal/shop')->assertOk()->assertSee('Uniform');
        $this->post("/portal/shop/{$student->student->id}", ['cart' => [['id' => $item->id, 'qty' => 1, 'size' => 'M']]])->assertRedirect(route('portal.shop'));
        $order = ShopOrder::latest('id')->first();
        $this->assertEqualsWithDelta($before, $account->fresh()->balance(), 0.01, 'not billed until the bookstore approves');
        $this->actingAs($store)->get('/bookstore')->assertOk();
        $this->post("/bookstore/orders/{$order->id}", ['status' => 'approved'])->assertSessionHas('ok');
        $this->actingAs($student);
        $this->assertEqualsWithDelta($before + 1200, $account->fresh()->balance(), 0.01);
        $this->assertSame(4, $item->fresh()->stock);
        $this->get('/portal/account')->assertOk()->assertSee('What you owe')->assertSee('Bookstore order #'.$order->id);

        // the cashier takes a custom 1,210: 1,200 clears the uniform, 10 goes to tuition
        $cashier = User::where('role', 'cashier')->first();
        $this->actingAs($cashier)->post('/finance/accounts/'.$account->enrollment_id.'/pay', ['amount' => 1210, 'method' => 'cash', 'paid_on' => today()->toDateString(), 'pay_key' => 'other', 'purpose' => 'Partial payment'])->assertSessionHasNoErrors();
        $a = $account->fresh();
        $this->assertEqualsWithDelta(0, $order->charge->fresh()->remaining(), 0.01);
        $this->assertEqualsWithDelta(max(0, $tuitionBefore - 10), $a->tuitionBalance(), 0.01);

        $this->assertTrue($store->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), 'ready for releasing')));
        $this->actingAs($store)->post("/bookstore/orders/{$order->id}/notify")->assertSessionHas('ok');
        $this->actingAs($store)->post("/bookstore/orders/{$order->id}", ['status' => 'released'])->assertSessionHas('ok');
        $this->assertSame('released', $order->fresh()->status);
    }

    public function test_bookstore_declines_with_a_reason_and_cannot_release_unpaid(): void
    {
        $store = User::create(['name' => 'Book Store', 'email' => 'store@school.test', 'password' => 'Demo12345', 'role' => 'bookstore', 'is_active' => true]);
        $account = StudentAccount::with('enrollment.student')->get()->first(fn ($a) => $a->enrollment->student->user_id);
        $student = $account->enrollment->student;
        $item = ShopItem::create(['name' => 'Cap', 'category' => 'merch', 'price' => 100]);
        $this->actingAs($student->user)->post("/portal/shop/{$student->id}", ['cart' => [['id' => $item->id, 'qty' => 1]]]);
        $o = ShopOrder::latest('id')->first();
        $this->actingAs($store)->post("/bookstore/orders/{$o->id}", ['status' => 'cancelled'])->assertSessionHasErrors('reason');
        $this->post("/bookstore/orders/{$o->id}", ['status' => 'cancelled', 'reason' => 'Out of stock'])->assertSessionHas('ok');
        $this->assertTrue($student->user->notifications()->get()->contains(fn ($n) => str_contains($n->data['body'] ?? json_encode($n->data), 'Out of stock')));
        $this->actingAs($student->user)->post("/portal/shop/{$student->id}", ['cart' => [['id' => $item->id, 'qty' => 1]]]);
        $o2 = ShopOrder::latest('id')->first();
        $this->actingAs($store)->post("/bookstore/orders/{$o2->id}", ['status' => 'approved']);
        $this->post("/bookstore/orders/{$o2->id}", ['status' => 'released'])->assertStatus(422);
    }

    public function test_paying_several_items_at_once_splits_between_them(): void
    {
        $account = StudentAccount::with('enrollment.student')->get()->first(fn ($a) => $a->tuitionBalance() > 500);
        $c1 = AccountCharge::create(['student_account_id' => $account->id, 'description' => 'TOR', 'amount' => 150]);
        $c2 = AccountCharge::create(['student_account_id' => $account->id, 'description' => 'Good moral', 'amount' => 50]);
        $c3 = AccountCharge::create(['student_account_id' => $account->id, 'description' => 'Uniform', 'amount' => 600]);
        $tuition = $account->tuitionBalance();
        $this->actingAs(User::where('role', 'cashier')->first())->post("/finance/accounts/{$account->enrollment_id}/pay",
            ['amount' => 300, 'method' => 'cash', 'paid_on' => today()->toDateString(), 'pay_key' => "order:{$c1->id},order:{$c2->id},fee:1", 'purpose' => 'TOR + Good moral + ID'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0, $c1->fresh()->remaining(), 0.01);
        $this->assertEqualsWithDelta(0, $c2->fresh()->remaining(), 0.01);
        $this->assertEqualsWithDelta(600, $c3->fresh()->remaining(), 0.01, 'the uniform was not picked');
        $this->assertEqualsWithDelta($tuition - 100, $account->fresh()->tuitionBalance(), 0.01);
    }
}
