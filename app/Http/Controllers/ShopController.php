<?php

namespace App\Http\Controllers;

use App\Models\AccountCharge;
use App\Models\ShopItem;
use App\Models\ShopOrder;
use App\Models\Student;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Services\Billing;
use App\Support\ParentAccess;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Bookstore: the office posts books, uniforms, merch and souvenirs; families order online.
 * An order is added to the student's balance as its own line, so it can be paid with (or apart from) tuition.
 */
class ShopController extends Controller
{
    // ------------------------------------------------------------ office

    public function manage(Request $request)
    {
        $status = in_array($request->query('status'), array_keys(ShopOrder::STATUSES), true) ? $request->query('status') : 'placed';

        return view('shop.manage', [
            'items' => ShopItem::orderBy('category')->orderBy('name')->get(),
            'orders' => ShopOrder::with('lines', 'student', 'charge')->where('status', $status)->latest()->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => ShopOrder::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function saveItem(Request $request, ?ShopItem $item = null)
    {
        $d = $request->validate([
            'name' => 'required|max:120', 'category' => ['required', Rule::in(array_keys(ShopItem::CATEGORIES))], 'description' => 'nullable|max:2000',
            'price' => 'required|numeric|min:0|max:1000000', 'sizes' => 'nullable|max:200', 'stock' => 'nullable|integer|min:0', 'image' => 'nullable|image|max:6144', 'is_active' => 'boolean',
        ]);
        $d['sizes'] = filled($d['sizes'] ?? null) ? array_values(array_filter(array_map('trim', explode(',', $d['sizes'])))) : null;
        $d['is_active'] = $request->boolean('is_active', true);
        if ($request->hasFile('image')) {
            $d['image_path'] = $request->file('image')->store('shop', 'local');
        }
        unset($d['image']);
        $item ? $item->update($d) : ShopItem::create($d);

        return back()->with('ok', 'Item saved.');
    }

    public function deleteItem(ShopItem $item)
    {
        $item->update(['is_active' => false]);

        return back()->with('ok', 'Item hidden from the bookstore (past orders are kept).');
    }

    public function setStatus(Request $request, ShopOrder $order)
    {
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(ShopOrder::STATUSES))]])['status'];
        if ($status === 'cancelled') {
            abort_if($order->charge && $order->charge->paid() > 0, 422, 'This order is already (partly) paid. Refund it at the cashier first.');
            DB::transaction(function () use ($order) {
                $order->charge?->update(['is_void' => true]);
                foreach ($order->lines as $l) {
                    if ($l->shop_item_id && ($item = ShopItem::find($l->shop_item_id)) && $item->stock !== null) {
                        $item->increment('stock', $l->qty);
                    }
                }
            });
        }
        $order->update(['status' => $status]);
        if ($status === 'ready') {
            $order->student->user?->notify(new GeneralNotice('Your order is ready', 'Bookstore order #'.$order->id.' can be claimed at the school.', '/portal/shop'));
        }

        return back()->with('ok', 'Order #'.$order->id.' – '.ShopOrder::STATUSES[$status].'.');
    }

    public function image(ShopItem $item)
    {
        abort_unless($item->image_path, 404);

        return Storage::disk('local')->response($item->image_path);
    }

    // ------------------------------------------------------------ families

    private function canOrder(User $u, Student $s): bool
    {
        return $u->role === Role::STUDENT ? $s->user_id === $u->id : ($u->role === Role::PARENT && ParentAccess::allows($u, $s, 'pay'));
    }

    public function store(Request $request)
    {
        $u = $request->user();
        $kids = $u->role === Role::STUDENT ? Student::where('user_id', $u->id)->get() : $u->children()->get();
        $child = $kids->firstWhere('id', (int) $request->query('child', session('child_id'))) ?? $kids->first();

        return view('shop.store', [
            'items' => ShopItem::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
            'child' => $child, 'children' => $kids,
            'orders' => $child ? ShopOrder::with('lines', 'charge')->where('student_id', $child->id)->latest()->take(10)->get() : collect(),
            'canOrder' => $child && $this->canOrder($u, $child) && $child->currentEnrollment()?->account,
        ]);
    }

    public function checkout(Request $request, Student $student)
    {
        $u = $request->user();
        abort_unless($this->canOrder($u, $student), 403);
        $account = $student->currentEnrollment()?->account;
        abort_unless($account, 422, 'Your school fees are not set up yet – ask the cashier.');
        $cart = $request->validate(['cart' => 'required|array|min:1', 'cart.*.id' => 'required|exists:shop_items,id', 'cart.*.qty' => 'required|integer|min:1|max:20', 'cart.*.size' => 'nullable|max:20'])['cart'];

        $order = DB::transaction(function () use ($cart, $student, $account, $u) {
            $lines = [];
            foreach ($cart as $c) {
                $item = ShopItem::lockForUpdate()->findOrFail($c['id']);
                abort_unless($item->is_active, 422, $item->name.' is no longer available.');
                if ($item->sizes) {
                    abort_unless(in_array($c['size'] ?? null, $item->sizes, true), 422, 'Choose a size for '.$item->name.'.');
                }
                if ($item->stock !== null) {
                    abort_if($item->stock < $c['qty'], 422, 'Only '.$item->stock.' '.$item->name.' left.');
                    $item->decrement('stock', $c['qty']);
                }
                $lines[] = ['shop_item_id' => $item->id, 'name' => $item->name, 'size' => $item->sizes ? $c['size'] : null, 'qty' => $c['qty'], 'price' => $item->price];
            }
            $total = round(collect($lines)->sum(fn ($l) => $l['price'] * $l['qty']), 2);
            $order = ShopOrder::create(['student_id' => $student->id, 'student_account_id' => $account->id, 'ordered_by' => $u->id, 'total' => $total]);
            $order->lines()->createMany($lines);
            AccountCharge::create(['student_account_id' => $account->id, 'shop_order_id' => $order->id, 'description' => 'Bookstore order #'.$order->id, 'amount' => $total]);

            return $order;
        });
        Notification::send(User::whereIn('role', [Role::CASHIER, Role::ADMIN])->where('is_active', true)->get(),
            new GeneralNotice('New bookstore order', $student->full_name.' · '.Billing::peso($order->total), route('shop.manage', [], false)));

        return redirect()->route('portal.account')->with('ok', 'Order #'.$order->id.' placed – '.Billing::peso($order->total).' was added to the balance. Pay it below or at the cashier.');
    }
}
