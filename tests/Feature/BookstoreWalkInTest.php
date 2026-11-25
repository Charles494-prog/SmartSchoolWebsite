<?php

namespace Tests\Feature;

use App\Models\ShopItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderLine;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookstoreWalkInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_walk_in_order_flows_to_finance_and_release(): void
    {
        $staff = User::where('role', 'bookstore')->first() ?? User::where('role', 'admin')->first();
        $item = ShopItem::whereNull('sizes')->first();
        $item->update(['stock' => 3]);
        $account = StudentAccount::with('enrollment.student')->first();
        $student = $account->enrollment->student;

        $this->actingAs($staff)->get('/bookstore')->assertOk()->assertSee('Shopping mode')->assertSee('Add item');
        $this->getJson('/bookstore/students?q='.urlencode($student->last_name))->assertOk()->assertJsonFragment(['id' => $student->id]);
        // free text / unknown student rejected
        $this->post('/bookstore/walk-in', ['student_id' => 999999, 'cart' => [['id' => $item->id, 'qty' => 1]]])->assertSessionHasErrors('student_id');
        // overselling rejected, nothing reserved
        $this->post('/bookstore/walk-in', ['student_id' => $student->id, 'cart' => [['id' => $item->id, 'qty' => 4]]])->assertSessionHasErrors('cart');
        $this->assertSame(3, $item->fresh()->stock);

        $this->post('/bookstore/walk-in', ['student_id' => $student->id, 'cart' => [['id' => $item->id, 'qty' => 2]]])->assertRedirect('/bookstore')->assertSessionHas('walkin');
        $order = ShopOrder::latest('id')->first();
        $this->assertTrue($order->walk_in);
        $this->assertSame('awaiting', $order->shownStatus());
        $this->assertSame(1, $item->fresh()->stock);
        $this->get("/bookstore/orders/{$order->id}/slip")->assertOk()->assertSee('PLEASE PAY AT THE FINANCE OFFICE');
        $this->getJson('/bookstore/orders.json')->assertOk()->assertJsonFragment(['id' => $order->id, 'status' => 'awaiting']);

        $cashier = User::where('role', 'cashier')->first();
        $this->actingAs($cashier)->get('/finance/bookstore')->assertOk()->assertSee('#'.$order->id);
        $this->post("/finance/accounts/{$account->enrollment_id}/pay", ['amount' => $order->total, 'method' => 'cash', 'pay_key' => 'order:'.$order->charge->id, 'purpose' => 'Bookstore'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->shownStatus());

        $this->actingAs($staff)->post("/bookstore/orders/{$order->id}", ['status' => 'released'])->assertSessionHas('ok');
        $this->assertSame('released', $order->fresh()->status);
    }

    public function test_delete_archives_items_used_in_orders(): void
    {
        $staff = User::where('role', 'admin')->first();
        $unused = ShopItem::create(['name' => 'Spare pencil', 'category' => 'other', 'price' => 10, 'is_active' => true]);
        $this->actingAs($staff)->delete("/bookstore/items/{$unused->id}")->assertSessionHas('ok');
        $this->assertNull(ShopItem::find($unused->id));

        $used = ShopItem::first();
        ShopOrderLine::create(['shop_order_id' => ShopOrder::create(['student_id' => StudentAccount::first()->enrollment->student_id, 'student_account_id' => StudentAccount::first()->id, 'total' => 1])->id, 'shop_item_id' => $used->id, 'name' => $used->name, 'qty' => 1, 'price' => 1]);
        $this->delete("/bookstore/items/{$used->id}")->assertSessionHas('ok', fn ($m) => str_contains($m, 'archived'));
        $this->assertFalse($used->fresh()->is_active);
    }
}
