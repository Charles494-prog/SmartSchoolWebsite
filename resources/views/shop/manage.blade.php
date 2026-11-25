@php
    $peso = fn ($v) => \App\Services\Billing::peso($v);
    $data = $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'category' => $i->category, 'description' => $i->description, 'price' => $i->price, 'sizes' => $i->sizes, 'stock' => $i->stock, 'is_active' => $i->is_active, 'image' => $i->image_path ? route('shop.image', $i) : null])->values();
    $walk = session('walkin') ? \App\Models\ShopOrder::with('lines', 'student')->find(session('walkin')) : null;
    $tabs = ['new' => 'New', 'payment' => 'Waiting for payment', 'release' => 'Paid – release', 'done' => 'Done'];
@endphp
<x-layouts.app :ajaxForms="true" title="Bookstore">
    @error('cart')<p class="mb-4 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800" role="alert">{{ $message }}</p>@enderror
    @error('student_id')<p class="mb-4 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800" role="alert">{{ $message }}</p>@enderror
    <script type="application/json" id="shop-items" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($data)</script>
    <div x-data="bookstore(@js($data), { walkIn: true })" x-on:ajax-done.window="const j = $event.detail.doc.getElementById('shop-items'); if (j) items = JSON.parse(j.textContent)">
        <div x-data="{ shopping: {{ old('student_id') ? 'true' : 'false' }}, student: null }" x-on:shop-for.window="student = $event.detail; shopping = true">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <div class="mr-auto"><h1 class="h1">Bookstore</h1><p class="muted text-sm">Your items as students see them. Use Shopping mode for a student at the counter.</p></div>
                <button type="button" class="btn min-h-11" :class="shopping ? 'btn-primary' : ''" x-on:click="shopping = !shopping; if (!shopping) { student = null; clear() }" :aria-pressed="shopping">
                    <x-icon name="user" class="h-4 w-4" /> <span x-text="shopping ? 'Exit shopping mode' : 'Shopping mode'"></span></button>
                <button type="button" class="relative btn min-h-11" x-on:click="$dispatch('orders-open')">
                    <x-icon name="list" class="h-4 w-4" /> Orders
                    <span x-data x-show="$store.shopOrders.badge" x-cloak class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[11px] font-bold text-white" x-text="$store.shopOrders.badge"></span>
                </button>
                <button type="button" x-show="shopping" x-cloak class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900" x-on:click="bagOpen = true" :aria-label="'Bag, ' + count + ' items'">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <span x-show="count" class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[11px] font-bold text-white" x-text="count"></span>
                </button>
            </div>

            {{-- Shopping mode: pick the student first (must be a real record) --}}
            <div x-show="shopping" x-cloak class="mb-4">
                <div x-show="!student" class="card" x-data="studentPick(@js(route('shop.students')))">
                    <label for="who" class="label">Who are you shopping for?</label>
                    <div class="relative">
                        <input id="who" type="text" x-model="q" x-on:input.debounce.250ms="search()" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter.prevent="pick(hits[at])"
                               class="input min-h-12 text-base" placeholder="Type the student's name or ID…" autocomplete="off" role="combobox" :aria-expanded="hits.length > 0" aria-controls="who-list" :aria-activedescendant="hits.length ? 'who-' + at : null">
                        <ul id="who-list" role="listbox" x-show="hits.length || (q.length >= 2 && !loading)" class="absolute z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-xl bg-white shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10">
                            <template x-for="(h, n) in hits" :key="h.id">
                                <li :id="'who-' + n" role="option" :aria-selected="n === at" x-on:click="pick(h)" x-on:mouseenter="at = n" class="flex cursor-pointer items-center gap-3 px-3 py-2.5" :class="n === at ? 'bg-brand-50 dark:bg-brand-500/10' : ''">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800" x-text="h.initials"></span>
                                    <span class="min-w-0 flex-1"><span class="block break-words font-medium" x-text="h.name"></span><span class="text-xs text-slate-500" x-text="h.class + ' · ID ' + h.no"></span></span>
                                    <span x-show="!h.billable" class="text-xs font-medium text-amber-800">No fee account</span>
                                </li>
                            </template>
                            <li x-show="!hits.length && q.length >= 2 && !loading" class="px-3 py-3 text-sm text-slate-500">No student found. Only existing student records can be picked.</li>
                        </ul>
                    </div>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">Use ↑ ↓ and Enter to pick. Typed names alone are not accepted.</p>
                </div>
                <div x-show="student" class="flex flex-wrap items-center gap-3 rounded-2xl bg-brand-600 px-4 py-3 text-white">
                    <x-icon name="user" class="h-5 w-5" />
                    <p class="flex-1">Shopping for: <b x-text="student?.name"></b>, <span x-text="student?.class"></span></p>
                    <button type="button" class="min-h-11 rounded-lg bg-white/15 px-3 font-semibold hover:bg-white/25" x-on:click="student = null">Change</button>
                    <button type="button" class="min-h-11 rounded-lg border-2 border-white px-3 font-semibold text-white hover:bg-white/15" x-on:click="student = null; shopping = false; clear()">Exit</button>
                </div>
            </div>

            <div x-show="!shopping">@include('shop._grid', ['manager' => true, 'canAdd' => false])</div>
            <div x-show="shopping" x-cloak :class="student ? '' : 'pointer-events-none opacity-50'">@include('shop._grid', ['manager' => false, 'canAdd' => true])</div>

            @include('shop._bag', ['action' => route('shop.walkin'), 'note' => 'Walk-in order: no approval needed. The student pays at the Finance office, then you release the items.', 'clearOnSubmit' => false, 'disabledWhen' => '!student',
                'extra' => new \Illuminate\Support\HtmlString('<input type="hidden" name="student_id" :value="student?.id ?? \'\'">')])

            @if($hidden->isNotEmpty())
                <details class="mt-8 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <summary class="min-h-11 cursor-pointer font-semibold">Archived items ({{ $hidden->count() }})</summary>
                    <ul class="mt-2 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        @foreach($hidden as $h)
                            <li class="flex items-center gap-3 py-2"><span class="flex-1">{{ $h->name }}</span><span class="tabular-nums">{{ $peso($h->price) }}</span>
                                <button type="button" class="btn min-h-11" x-on:click="$dispatch('item-edit', @js(['id' => $h->id, 'name' => $h->name, 'category' => $h->category, 'description' => $h->description, 'price' => $h->price, 'sizes' => $h->sizes, 'stock' => $h->stock, 'is_active' => false, 'image' => null]))">Edit / restore</button></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    </div>

    {{-- Add / edit item side panel --}}
    <div x-data="{ open: {{ $errors->hasAny(['name', 'price', 'category', 'stock', 'sizes', 'image', 'description']) ? 'true' : 'false' }}, it: null }" x-on:item-edit.window="it = $event.detail; open = true" x-on:ajax-done.window="if (!$event.detail.doc.querySelector('.field-error')) open = false" x-on:keydown.escape.window="open = false">
        <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-slate-900/40" x-on:click="open = false"></div>
        <aside x-show="open" x-cloak x-transition class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="item-title">
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 id="item-title" class="h2" x-text="it ? 'Edit item' : 'Add item'"></h2>
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-white/10" x-on:click="open = false" aria-label="Close"><x-icon name="x" class="h-5 w-5" /></button>
            </header>
            <form method="post" :action="it ? @js(url('/bookstore/items')) + '/' + it.id : @js(route('shop.items.store'))" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col" x-data="{ busy: false }" x-on:submit="busy = true">@csrf
                <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    <label class="block"><span class="label">Name <span class="text-rose-600">*</span></span><input name="name" class="input min-h-11" required maxlength="120" :value="it?.name ?? @js(old('name'))"></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block"><span class="label">Category</span><select name="category" class="input min-h-11">@foreach(\App\Models\ShopItem::CATEGORIES as $k => $v)<option value="{{ $k }}" :selected="(it?.category ?? 'book') === '{{ $k }}'">{{ $v }}</option>@endforeach</select></label>
                        <label class="block"><span class="label">Price (₱) <span class="text-rose-600">*</span></span><input name="price" type="number" step="0.01" min="0" required class="input min-h-11" :value="it?.price ?? ''"></label>
                        <label class="block"><span class="label">Stock</span><input name="stock" type="number" min="0" class="input min-h-11" :value="it?.stock ?? ''" placeholder="Blank = no limit"></label>
                        <label class="block"><span class="label">Sizes</span><input name="sizes" class="input min-h-11" :value="(it?.sizes || []).join(', ')" placeholder="S, M, L"></label>
                    </div>
                    <label class="block"><span class="label">Description</span><textarea name="description" rows="3" class="input" x-text="it?.description ?? ''"></textarea></label>
                    <label class="block"><span class="label">Photo</span><input type="file" name="image" accept="image/*" class="block w-full text-sm"><span class="text-xs text-slate-500" x-show="it?.image">Leave empty to keep the current photo.</span></label>
                    <label class="flex min-h-11 items-center gap-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="h-5 w-5 accent-brand-600" :checked="it ? it.is_active !== false : true"> Show in the bookstore (active)</label>
                    @foreach(['name', 'price', 'category', 'stock', 'sizes', 'image', 'description'] as $f)@error($f)<p class="field-error">{{ $message }}</p>@enderror @endforeach
                </div>
                <footer class="flex gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800"><button type="button" class="btn min-h-11 flex-1" x-on:click="open = false">Cancel</button><button class="btn btn-primary min-h-11 flex-1" :disabled="busy" x-text="busy ? 'Saving…' : 'Save item'">Save item</button></footer>
            </form>
        </aside>
    </div>

    {{-- Delete confirmation --}}
    <div x-data="{ it: null }" x-on:item-delete.window="it = $event.detail" x-on:ajax-done.window="it = null" x-on:keydown.escape.window="it = null">
        <div x-show="it" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <form method="post" :action="it ? @js(url('/bookstore/items')) + '/' + it.id : '#'" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900" role="alertdialog" aria-modal="true" aria-labelledby="del-title" x-on:click.outside="it = null">@csrf @method('DELETE')
                <h2 id="del-title" class="h2" x-text="'Delete ' + (it?.name ?? '') + '?'"></h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">If it appears in past orders it is archived instead, so those orders stay intact.</p>
                <div class="mt-4 flex gap-2"><button type="button" class="btn min-h-11 flex-1" x-on:click="it = null">Cancel</button><button class="btn min-h-11 flex-1 bg-rose-600 text-white hover:bg-rose-700">Delete</button></div>
            </form>
        </div>
    </div>

    {{-- Orders panel (polls every 5 s) --}}
    <div x-data="ordersPanel(@js(route('shop.orders')), {{ json_encode($orderCounts) }})" x-on:orders-open.window="open = true; load()" x-on:ajax-done.window="load()" x-on:keydown.escape.window="open = false">
        <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-slate-900/40" x-on:click="open = false"></div>
        <aside x-show="open" x-cloak x-transition class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col bg-white shadow-2xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-label="Orders">
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="h2">Orders</h2>
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-white/10" x-on:click="open = false" aria-label="Close"><x-icon name="x" class="h-5 w-5" /></button>
            </header>
            <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 px-3 py-2 text-sm dark:border-slate-800" role="tablist">
                @foreach($tabs as $k => $t)
                    <button type="button" role="tab" :aria-selected="tab === '{{ $k }}'" class="flex min-h-10 shrink-0 items-center gap-1.5 rounded-lg px-3 font-medium" :class="tab === '{{ $k }}' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'" x-on:click="tab = '{{ $k }}'">{{ $t }} <span class="text-xs opacity-70" x-text="counts['{{ $k }}'] || 0"></span></button>
                @endforeach
            </nav>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <p x-show="error" x-text="error" class="m-4 rounded-lg bg-rose-50 p-2 text-sm text-rose-800" role="alert"></p>
                <p x-show="loading && !orders.length" class="p-6 text-center text-slate-500">Loading…</p>
                <p x-show="!loading && !list.length" class="p-10 text-center text-slate-500">No orders here.</p>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <template x-for="o in list" :key="o.id">
                        <li class="space-y-2 px-5 py-4" x-data="{ no: false }">
                            <div class="flex flex-wrap items-start gap-2">
                                <div class="min-w-0 flex-1"><p class="font-semibold"><span x-text="'#' + o.id"></span> · <span class="break-words" x-text="o.student"></span>
                                    <span x-show="o.walk_in" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-700 dark:bg-white/10 dark:text-slate-200">Walk-in</span></p>
                                    <p class="break-words text-sm text-slate-600 dark:text-slate-400" x-text="o.summary"></p><p class="text-xs text-slate-500" x-text="o.when"></p></div>
                                <div class="text-right"><p class="font-semibold tabular-nums" x-text="peso(o.total)"></p><p class="text-xs" :class="o.unpaid > 0 ? 'text-amber-800' : 'text-emerald-800'" x-text="o.status === 'placed' ? 'Not billed yet' : (o.unpaid > 0 ? peso(o.unpaid) + ' unpaid' : 'Paid')"></p></div>
                            </div>
                            <p class="text-xs font-semibold" x-text="o.label"></p>
                            <p x-show="o.note" class="text-xs text-rose-800" x-text="'“' + o.note + '”'"></p>
                            <form method="post" :action="statusUrl(o.id)" class="flex flex-wrap gap-2">@csrf
                                <template x-if="o.status === 'placed'"><span class="flex gap-2" x-show="!no"><button name="status" value="approved" class="btn btn-primary min-h-11">Approve</button></span></template>
                                <template x-if="o.status === 'approved' && !o.walk_in"><button name="status" value="ready" class="btn min-h-11" x-show="!no">Prepared</button></template>
                                <template x-if="o.status === 'paid'"><button name="status" value="released" class="btn btn-primary min-h-11" x-show="!no">Release</button></template>
                                <template x-if="['approved', 'ready', 'paid'].includes(o.status) && !o.walk_in"><button type="submit" :formaction="notifyUrl(o.id)" class="btn min-h-11" x-show="!no">Notify student &amp; parents</button></template>
                                <template x-if="o.walk_in && ['awaiting', 'paid'].includes(o.status)"><a :href="slipUrl(o.id)" target="_blank" class="btn min-h-11" x-show="!no">Print slip</a></template>
                                <template x-if="o.canDecline && ['placed', 'approved', 'awaiting', 'ready'].includes(o.status)"><button type="button" class="btn min-h-11 text-rose-700" x-show="!no" x-on:click="no = true">Decline…</button></template>
                                <span x-show="no" class="flex w-full flex-wrap gap-2"><label class="sr-only" :for="'r' + o.id">Reason</label><input :id="'r' + o.id" name="reason" class="input min-h-11 flex-1" placeholder="Reason, e.g. Size M is out of stock" :required="no">
                                    <button name="status" value="cancelled" class="btn min-h-11 text-rose-700">Decline order</button><button type="button" class="btn min-h-11" x-on:click="no = false">Back</button></span>
                            </form>
                        </li>
                    </template>
                </ul>
            </div>
        </aside>
    </div>

    {{-- After a walk-in checkout --}}
    @if($walk)
        <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-slate-900" role="alertdialog" aria-modal="true" aria-labelledby="pay-title">
                <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-800"><x-icon name="wallet" class="h-7 w-7" /></span>
                <h2 id="pay-title" class="text-xl font-bold">Please pay at the Finance office</h2>
                <p class="mt-1 text-slate-600 dark:text-slate-400">{{ $walk->student->full_name }}</p>
                <dl class="mx-auto mt-4 grid max-w-xs grid-cols-2 gap-y-1 text-left text-sm"><dt class="text-slate-500">Order no.</dt><dd class="text-right font-semibold">#{{ $walk->id }}</dd><dt class="text-slate-500">Total</dt><dd class="text-right text-lg font-bold">{{ $peso($walk->total) }}</dd></dl>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">Status: Awaiting payment. Release the items once Finance marks it paid.</p>
                <div class="mt-5 flex gap-2"><a href="{{ route('shop.slip', $walk) }}" target="_blank" class="btn min-h-11 flex-1">Print slip</a><button type="button" class="btn btn-primary min-h-11 flex-1" x-on:click="open = false">Done</button></div>
            </div>
        </div>
    @endif

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.store('shopOrders', { badge: 0 });
            Alpine.data('studentPick', (url) => ({
                q: '', hits: [], at: 0, loading: false,
                async search() {
                    if (this.q.trim().length < 2) { this.hits = []; return }
                    this.loading = true;
                    try { const r = await fetch(url + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } }); this.hits = r.ok ? await r.json() : []; this.at = 0 } finally { this.loading = false }
                },
                move(d) { if (this.hits.length) this.at = (this.at + d + this.hits.length) % this.hits.length },
                pick(h) { if (!h) return; this.$dispatch('shop-for', h); this.q = ''; this.hits = [] },
            }));
            Alpine.data('ordersPanel', (url, counts) => ({
                open: false, tab: 'new', orders: [], counts, loading: false, error: '',
                init() { this.badge(); setInterval(() => { if (!document.hidden) this.load() }, 5000) },
                badge() { Alpine.store('shopOrders').badge = (this.counts.new || 0) + (this.counts.release || 0) },
                get list() { return this.orders.filter((o) => o.tab === this.tab) },
                peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                statusUrl(id) { return @js(url('/bookstore/orders')) + '/' + id },
                notifyUrl(id) { return @js(url('/bookstore/orders')) + '/' + id + '/notify' },
                slipUrl(id) { return @js(url('/bookstore/orders')) + '/' + id + '/slip' },
                async load() {
                    this.loading = true;
                    try { const r = await fetch(url, { headers: { Accept: 'application/json' } }); if (!r.ok) throw new Error(); const j = await r.json(); this.orders = j.orders; this.counts = j.counts; this.badge(); this.error = '' }
                    catch (e) { this.error = 'Could not refresh orders. Retrying…' } finally { this.loading = false }
                },
            }));
        });
    </script>
</x-layouts.app>
