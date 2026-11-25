<x-layouts.app title="Bookstore">
    @php $peso = fn ($v) => \App\Services\Billing::peso($v); @endphp
    <div class="mb-5"><h1 class="h1">Bookstore</h1><p class="muted">Post books, uniforms, merch and souvenirs. Families order from their portal; each order is added to the student's balance.</p></div>
    <div x-data="{ tab: @js(request('tab', 'orders')) }">
        <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
            <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="tab === 'orders' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="tab = 'orders'">Orders</button>
            <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="tab === 'items' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="tab = 'items'">Items ({{ $items->count() }})</button>
        </div>

        <div x-show="tab === 'orders'">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach(\App\Models\ShopOrder::STATUSES as $k => $label)
                    <a href="{{ route('shop.manage', ['status' => $k]) }}" class="rounded-full border px-3 py-1 text-sm {{ $status === $k ? 'border-slate-900 bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300' }}">{{ $label }} <span class="opacity-60">{{ $counts[$k] ?? 0 }}</span></a>
                @endforeach
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($orders as $o)
                        @php $rem = $o->charge?->remaining() ?? 0; @endphp
                        <li class="flex flex-wrap items-center gap-4 px-5 py-4">
                            <div class="min-w-0 flex-1 basis-64">
                                <p class="font-medium">#{{ $o->id }} · {{ $o->student->full_name }}</p>
                                <p class="text-sm text-slate-500">{{ $o->summary() }} · {{ $o->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="text-right"><span class="block font-semibold tabular-nums">{{ $peso($o->total) }}</span><span class="text-xs {{ $rem > 0 ? 'text-amber-700' : 'text-emerald-600' }}">{{ $rem > 0 ? $peso($rem).' unpaid' : 'Paid' }}</span></span>
                            <form method="post" action="{{ route('shop.orders.status', $o) }}" class="flex gap-2">@csrf
                                @if($o->status === 'placed')<button name="status" value="ready" class="btn btn-primary btn-sm">Ready to claim</button>@endif
                                @if($o->status === 'ready')<button name="status" value="claimed" class="btn btn-primary btn-sm">Mark claimed</button>@endif
                                @if(in_array($o->status, ['placed', 'ready'], true) && $o->charge && $o->charge->paid() == 0)<button name="status" value="cancelled" class="btn btn-sm text-rose-600">Cancel</button>@endif
                            </form>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-slate-500">No orders here.</li>
                    @endforelse
                </ul>
            </div>
            <div class="mt-3">{{ $orders->links() }}</div>
        </div>

        <div x-show="tab === 'items'" x-cloak class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach($items as $i)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 {{ $i->is_active ? '' : 'opacity-60' }}" x-data="{ edit: false }">
                        <div class="flex gap-3">
                            @if($i->image_path)<img src="{{ route('shop.image', $i) }}" alt="" class="h-16 w-16 rounded-lg object-cover">@else<span class="flex h-16 w-16 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800"><x-icon name="tag" class="h-6 w-6 text-slate-300" /></span>@endif
                            <div class="min-w-0 flex-1"><p class="font-semibold">{{ $i->name }}</p><p class="text-sm text-slate-500">{{ \App\Models\ShopItem::CATEGORIES[$i->category] ?? '' }} · {{ $peso($i->price) }}{{ $i->sizes ? ' · '.implode('/', $i->sizes) : '' }}{{ $i->stock !== null ? ' · '.$i->stock.' in stock' : '' }}</p>
                                <button type="button" class="mt-1 text-sm text-brand-600" x-on:click="edit = !edit">{{ $i->is_active ? 'Edit' : 'Hidden – edit to show again' }}</button></div>
                        </div>
                        <form x-show="edit" x-cloak method="post" action="{{ route('shop.items.update', $i) }}" enctype="multipart/form-data" class="mt-3 space-y-2">@csrf @include('shop._fields', ['i' => $i])<button class="btn btn-primary btn-sm">Save</button></form>
                    </div>
                @endforeach
            </div>
            <form method="post" action="{{ route('shop.items.store') }}" enctype="multipart/form-data" class="card h-fit space-y-2">@csrf
                <h2 class="font-semibold">Post an item</h2>
                @include('shop._fields', ['i' => null])
                <button class="btn btn-primary w-full">Post to the bookstore</button>
            </form>
        </div>
    </div>
</x-layouts.app>
