<?php

namespace App\Http\Controllers;

use App\Models\AccountCharge;
use App\Models\ShopItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderLine;
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
use Illuminate\Validation\ValidationException;

/**
 * Bookstore: the office posts books, uniforms, merch and souvenirs; families order online.
 * An order is added to the student's balance as its own line, so it can be paid with (or apart from) tuition.
 */
class ShopController extends Controller
{
    // ------------------------------------------------------------ office

    public function manage(Request $request)
    {
        return view('shop.manage', [
            'items' => ShopItem::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
            'hidden' => ShopItem::where('is_active', false)->orderBy('name')->get(),
            'orderCounts' => $this->orderFeed()['counts'],
        ]);
    }

    /** Orders panel data (polled every few seconds – no outside services, works on LAN). */
    public function orders(Request $request)
    {
        return response()->json($this->orderFeed());
    }

    private function orderFeed(): array
    {
        $all = ShopOrder::with('lines', 'student', 'charge')->where(fn ($q) => $q->whereNotIn('status', ['released', 'cancelled'])->orWhere('updated_at', '>=', now()->subDays(14)))->latest()->limit(300)->get();
        $tab = fn ($o) => match ($o->shownStatus()) {
            'placed' => 'new', 'approved', 'awaiting', 'ready' => 'payment', 'paid' => 'release', default => 'done'
        };

        return [
            'counts' => $all->groupBy($tab)->map->count()->all() + ['new' => 0, 'payment' => 0, 'release' => 0, 'done' => 0],
            'orders' => $all->map(fn ($o) => ['id' => $o->id, 'tab' => $tab($o), 'status' => $o->shownStatus(), 'label' => ShopOrder::SHOWN[$o->shownStatus()], 'walk_in' => $o->walk_in,
                'student' => $o->student->full_name, 'summary' => $o->summary(), 'total' => $o->total, 'unpaid' => $o->charge?->remaining() ?? 0, 'note' => $o->note,
                'when' => $o->created_at->format('M j, Y').' '.ampm($o->created_at->format('H:i')), 'canDecline' => ! $o->charge || $o->charge->paid() == 0])->values()->all(),
        ];
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

        return redirect()->route('shop.manage')->with('ok', 'Item saved.');
    }

    public function deleteItem(ShopItem $item)
    {
        if (ShopOrderLine::where('shop_item_id', $item->id)->exists()) {
            $item->update(['is_active' => false]);

            return back()->with('ok', $item->name.' is in past orders, so it was archived (hidden) instead of deleted.');
        }
        $item->delete();

        return back()->with('ok', $item->name.' deleted.');
    }

    public function setStatus(Request $request, ShopOrder $order)
    {
        $d = $request->validate(['status' => ['required', Rule::in(['approved', 'ready', 'released', 'cancelled'])], 'reason' => 'nullable|required_if:status,cancelled|max:255'],
            ['reason.required_if' => 'Tell the buyer why, e.g. "Size M is out of stock".']);
        $status = $d['status'];
        $user = $order->student->user;
        if ($status === 'approved') {
            abort_unless($order->status === 'placed', 422, 'Only new orders can be approved.');
            $en = $order->student->currentEnrollment();
            abort_unless($en, 422, 'This student is not enrolled this school year.');
            $account = Billing::accountFor($en);
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
            if ($order->isPaid()) {
                $n = $order->tellFamilyReady();

                return back()->with('ok', 'Prepared and paid – told '.$n.' '.str('account')->plural($n).' to come claim it. Release it when they pick it up.');
            }

            return back()->with('ok', 'Prepared. Not paid yet – the family is told automatically once finance confirms the payment.');
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
        if ($order->status === 'ready' && $order->isPaid()) {
            $n = $order->tellFamilyReady();

            return back()->with('ok', 'Reminded '.$n.' '.str('account')->plural($n).' to come claim it.');
        }
        $paid = $order->isPaid();
        $body = $paid ? 'Bookstore order #'.$order->id.' ('.$order->summary().') is ready. You can claim it at the bookstore now.'
            : 'Bookstore order #'.$order->id.' is ready, but '.Billing::peso($order->charge->remaining()).' is still unpaid. Please pay first (Payments), then claim it.';
        $people = collect([$order->student->user])->merge($order->student->guardians()->with('user')->get()->pluck('user'))->filter()->unique('id');
        Notification::send($people, new GeneralNotice('Your order is ready', $body, $paid ? '/portal/shop' : '/portal/account'));

        return back()->with('ok', 'Notified '.$people->count().' '.str('account')->plural($people->count()).'.');
    }

    // ------------------------------------------------------------ shopping mode (staff shop for a student at the counter)

    /** As-you-type student picker: only what's needed to pick the right child. */
    public function studentLookup(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $students = Student::where('status', 'active')
            ->where(fn ($w) => $w->where('last_name', 'like', "%$q%")->orWhere('first_name', 'like', "%$q%")->orWhere('student_no', 'like', "%$q%"))
            ->orderBy('last_name')->orderBy('first_name')->limit(12)->get();

        return response()->json($students->map(function ($s) {
            $e = $s->currentEnrollment();

            return ['id' => $s->id, 'name' => $s->full_name, 'no' => $s->student_no, 'initials' => mb_substr($s->first_name, 0, 1).mb_substr($s->last_name, 0, 1),
                'class' => $e ? $e->gradeLevel->name.($e->section ? ' – '.$e->section->name : '') : 'Not enrolled this year', 'billable' => (bool) $e?->account];
        })->values());
    }

    /** Walk-in checkout: no approval, no "prepared" – the order goes straight to the balance; pay at Finance, then release. */
    public function walkIn(Request $request)
    {
        $d = $request->validate(['student_id' => 'required|integer|exists:students,id', 'cart' => 'required|array|min:1|max:50', 'cart.*.id' => 'required|exists:shop_items,id', 'cart.*.qty' => 'required|integer|min:1|max:50', 'cart.*.size' => 'nullable|max:20'],
            ['student_id.required' => 'Pick a student from the list first.', 'student_id.exists' => 'Pick a student from the list first.']);
        $student = Student::findOrFail($d['student_id']);
        $en = $student->currentEnrollment();
        $account = $en ? Billing::accountFor($en) : null;
        if (! $account) {
            return back()->with('error', $student->full_name.' is not enrolled this school year.');
        }
        $order = DB::transaction(function () use ($d, $student, $account, $request) {
            $order = $this->placeOrder($d['cart'], $student, $account, $request->user());
            AccountCharge::create(['student_account_id' => $account->id, 'shop_order_id' => $order->id, 'description' => 'Bookstore order #'.$order->id, 'amount' => $order->total]);
            $order->update(['status' => 'approved', 'walk_in' => true]);

            return $order;
        });
        Notification::send(User::whereIn('role', [Role::CASHIER])->where('is_active', true)->get(),
            new GeneralNotice('Bookstore order to pay', 'Walk-in order #'.$order->id.' · '.$student->full_name.' · '.Billing::peso($order->total), route('finance.bookstore', [], false)));

        return redirect()->route('shop.manage')->with('walkin', $order->id);
    }

    public function slip(ShopOrder $order)
    {
        $order->load('lines', 'student');

        return view('shop.slip', ['o' => $order, 'e' => $order->student->currentEnrollment()]);
    }

    /** Finance: bookstore orders billed and not yet paid (walk-ins first). */
    public function awaitingPayment(Request $request)
    {
        $orders = ShopOrder::with('lines', 'student', 'charge')->whereIn('status', ['approved', 'ready'])->latest()->get()
            ->filter(fn ($o) => $o->charge && $o->charge->remaining() > 0)->sortByDesc('walk_in')->values();
        if ($request->expectsJson()) {
            return response()->json(['count' => $orders->count(), 'ids' => $orders->pluck('id')]);
        }

        return view('shop.finance', ['orders' => $orders]);
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

        $order = DB::transaction(fn () => $this->placeOrder($cart, $student, $account, $u));
        Notification::send(User::whereIn('role', [Role::BOOKSTORE, Role::ADMIN])->where('is_active', true)->get(),
            new GeneralNotice('New bookstore order', $student->full_name.' · '.Billing::peso($order->total), route('shop.manage', [], false)));

        return redirect()->route('portal.shop')->with('ok', 'Order #'.$order->id.' sent. The bookstore will check it first; once approved you can pay it.');
    }

    /** Reserve stock (row-locked, so two checkouts can't oversell) and create the order. Call inside a transaction. */
    private function placeOrder(array $cart, Student $student, $account, User $u): ShopOrder
    {
        $lines = [];
        foreach ($cart as $c) {
            $item = ShopItem::lockForUpdate()->findOrFail($c['id']);
            $fail = fn ($m) => throw ValidationException::withMessages(['cart' => $m]);
            $item->is_active || $fail($item->name.' is no longer available.');
            if ($item->sizes && ! in_array($c['size'] ?? null, $item->sizes, true)) {
                $fail('Choose a size for '.$item->name.'.');
            }
            if ($item->stock !== null) {
                $item->stock >= $c['qty'] || $fail($item->stock > 0 ? 'Only '.$item->stock.' '.$item->name.' left – lower the quantity.' : $item->name.' just ran out of stock.');
                $item->decrement('stock', $c['qty']);
            }
            $lines[] = ['shop_item_id' => $item->id, 'name' => $item->name, 'size' => $item->sizes ? $c['size'] : null, 'qty' => $c['qty'], 'price' => $item->price];
        }
        $total = round(collect($lines)->sum(fn ($l) => $l['price'] * $l['qty']), 2);
        $order = ShopOrder::create(['student_id' => $student->id, 'student_account_id' => $account->id, 'ordered_by' => $u->id, 'total' => $total]);
        $order->lines()->createMany($lines);

        return $order;
    }
}
