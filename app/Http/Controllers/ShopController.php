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
        $all = ShopOrder::with('lines', 'student', 'charge')->latest()->get();
        $eff = fn ($o) => in_array($o->status, ['approved', 'ready'], true) && $o->isPaid() ? 'paid' : $o->status;

        return view('shop.manage', [
            'items' => ShopItem::orderBy('category')->orderBy('name')->get(),
            'orders' => $all->filter(fn ($o) => $eff($o) === $status)->values(),
            'status' => $status,
            'counts' => $all->groupBy($eff)->map->count(),
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
        $d = $request->validate(['status' => ['required', Rule::in(['approved', 'ready', 'released', 'cancelled'])], 'reason' => 'nullable|required_if:status,cancelled|max:255'],
            ['reason.required_if' => 'Tell the buyer why, e.g. "Size M is out of stock".']);
        $status = $d['status'];
        $user = $order->student->user;
        if ($status === 'approved') {
            abort_unless($order->status === 'placed', 422, 'Only new orders can be approved.');
            $account = $order->student->currentEnrollment()?->account;
            abort_unless($account, 422, 'This student has no fee account yet – ask finance to set it up.');
            AccountCharge::create(['student_account_id' => $account->id, 'shop_order_id' => $order->id, 'description' => 'Bookstore order #'.$order->id, 'amount' => $order->total]);
            $order->update(['status' => 'approved', 'student_account_id' => $account->id]);
            foreach (array_filter([$user, ...$order->student->guardians()->with('user')->get()->pluck('user')->all()]) as $u) {
                $u->notify(new GeneralNotice('Order approved – please pay', 'Bookstore order #'.$order->id.' ('.Billing::peso($order->total).') is on the balance. Pay online or at the finance office.', '/portal/account'));
            }

            return back()->with('ok', 'Order #'.$order->id.' approved and added to the balance. Finance will confirm the payment.');
        }
        if ($status === 'ready') {
            abort_unless($order->status === 'approved', 422, 'Approve the order first.');
            $order->update(['status' => 'ready']);

            return back()->with('ok', $order->isPaid() ? 'Prepared. It is paid – release it, or notify the family to come get it.' : 'Prepared. Not paid yet – you will be notified when finance confirms the payment.');
        }
        if ($status === 'released') {
            abort_unless($order->isPaid(), 422, 'Not paid yet – finance must confirm the payment before you release it.');
            $order->update(['status' => 'released']);
            $user?->notify(new GeneralNotice('Order released', 'You received bookstore order #'.$order->id.'.', '/portal/shop'));

            return back()->with('ok', 'Order #'.$order->id.' released.');
        }
        abort_if($order->charge && $order->charge->paid() > 0, 422, 'This order is already (partly) paid. Refund it at finance first.');
        DB::transaction(function () use ($order, $d) {
            $order->charge?->update(['is_void' => true]);
            foreach ($order->lines as $l) {
                if ($l->shop_item_id && ($item = ShopItem::find($l->shop_item_id)) && $item->stock !== null) {
                    $item->increment('stock', $l->qty);
                }
            }
            $order->update(['status' => 'cancelled', 'note' => $d['reason']]);
        });
        $buyer = $order->ordered_by ? User::find($order->ordered_by) : $user;
        foreach (array_unique(array_filter([$buyer?->id, $user?->id])) as $uid) {
            User::find($uid)?->notify(new GeneralNotice('Order not accepted', 'Bookstore order #'.$order->id.': '.$d['reason'], '/portal/shop'));
        }

        return back()->with('ok', 'Order #'.$order->id.' declined – the buyer was told why.');
    }

    /** Tell the student and parents the order is ready to claim (or that it still needs to be paid). */
    public function notify(ShopOrder $order)
    {
        abort_unless(in_array($order->status, ['approved', 'ready'], true), 422);
        $paid = $order->isPaid();
        $body = $paid ? 'Bookstore order #'.$order->id.' ('.$order->summary().') is ready. You can claim it at the bookstore now.'
            : 'Bookstore order #'.$order->id.' is ready, but '.Billing::peso($order->charge->remaining()).' is still unpaid. Please pay first (Payments), then claim it.';
        $people = collect([$order->student->user])->merge($order->student->guardians()->with('user')->get()->pluck('user'))->filter()->unique('id');
        Notification::send($people, new GeneralNotice('Your order is ready', $body, $paid ? '/portal/shop' : '/portal/account'));

        return back()->with('ok', 'Notified '.$people->count().' '.str('account')->plural($people->count()).'.');
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

            return $order;
        });
        Notification::send(User::whereIn('role', [Role::BOOKSTORE, Role::ADMIN])->where('is_active', true)->get(),
            new GeneralNotice('New bookstore order', $student->full_name.' · '.Billing::peso($order->total), route('shop.manage', [], false)));

        return redirect()->route('portal.shop')->with('ok', 'Order #'.$order->id.' sent. The bookstore will check it first; once approved you can pay it.');
    }
}
