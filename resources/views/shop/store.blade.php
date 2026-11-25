@php
    $peso = fn ($v) => \App\Services\Billing::peso($v);
    $data = $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'category' => $i->category, 'description' => $i->description, 'price' => $i->price, 'sizes' => $i->sizes, 'stock' => $i->stock, 'image' => $i->image_path ? route('shop.image', $i) : null])->values();
    $chip = ['placed' => 'bg-slate-100 text-slate-700', 'approved' => 'bg-amber-50 text-amber-900', 'awaiting' => 'bg-amber-50 text-amber-900', 'ready' => 'bg-sky-50 text-sky-800', 'paid' => 'bg-emerald-50 text-emerald-800', 'released' => 'bg-slate-100 text-slate-600', 'cancelled' => 'bg-rose-50 text-rose-800'];
@endphp
<x-layouts.app title="Bookstore">
    @error('cart')<p class="mb-4 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800" role="alert">{{ $message }}</p>@enderror
    @error('student_id')<p class="mb-4 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800" role="alert">{{ $message }}</p>@enderror
    <div x-data="bookstore(@js($data), { key: 'ss.cart.{{ $child?->id }}' })">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="mr-auto"><h1 class="h1">Bookstore</h1><p class="muted text-sm">Order → the bookstore approves → pay online or at Finance → claim it at the bookstore.</p></div>
            @if($children->count() > 1)
                <form><label class="sr-only" for="child">Student</label><select id="child" name="child" class="input min-h-11" x-on:change="$el.form.submit()">@foreach($children as $c)<option value="{{ $c->id }}" @selected($child?->id === $c->id)>{{ $c->full_name }}</option>@endforeach</select></form>
            @endif
            <button type="button" class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900" x-on:click="bagOpen = true" :aria-label="'Bag, ' + count + ' items'">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span x-show="count" x-cloak class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[11px] font-bold text-white" x-text="count"></span>
            </button>
        </div>
        @unless($canOrder)
            <p class="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $child && ! $child->currentEnrollment()?->account ? 'Fees are not set up yet – ask the cashier before ordering.' : 'Ordering is not turned on for your account. You can still browse.' }}</p>
        @endunless

        @include('shop._grid', ['manager' => false, 'canAdd' => (bool) $canOrder])

        @if($orders->isNotEmpty())
            <section class="mt-8">
                <h2 class="h2 mb-3">Your orders</h2>
                <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    @foreach($orders as $o)
                        @php $st = $o->shownStatus(); $rem = $o->charge?->remaining() ?? 0; @endphp
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                            <span class="font-semibold">#{{ $o->id }}</span>
                            <span class="min-w-0 flex-1 basis-48 break-words text-slate-600 dark:text-slate-400">{{ $o->summary() }}</span>
                            <span class="tabular-nums">{{ $peso($o->total) }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $chip[$st] }}">{{ \App\Models\ShopOrder::SHOWN[$st] }}</span>
                            @if($o->status === 'cancelled' && $o->note)<span class="w-full text-xs text-rose-800">“{{ $o->note }}”</span>
                            @elseif(in_array($o->status, ['approved', 'ready'], true) && $rem > 0)<a href="{{ route('portal.account') }}" class="btn min-h-11">Pay {{ $peso($rem) }}</a>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($child && $canOrder)
            @include('shop._bag', ['action' => route('portal.shop.checkout', $child), 'note' => 'The bookstore checks your order first. Once approved it shows on Payments for you to pay.'])
        @endif
    </div>
</x-layouts.app>
