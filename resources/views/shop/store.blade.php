<x-layouts.app title="Bookstore">
    @php $peso = fn ($v) => \App\Services\Billing::peso($v); @endphp
    <div x-data="shop(@js($items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'price' => $i->price, 'sizes' => $i->sizes, 'stock' => $i->stock])->values()))" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div>
            <div class="mb-5 flex flex-wrap items-end gap-3">
                <div class="mr-auto"><h1 class="h1">Bookstore</h1><p class="muted">Books, uniforms and school merch. Your order is added to {{ $child?->first_name ?? 'the' }}'s balance – pay it online or at the cashier, then claim it at school.</p></div>
                @if($children->count() > 1)
                    <form><select name="child" class="input h-10" x-on:change="$el.form.submit()">@foreach($children as $c)<option value="{{ $c->id }}" @selected($child?->id === $c->id)>{{ $c->full_name }}</option>@endforeach</select></form>
                @endif
            </div>
            <div class="mb-4 flex flex-wrap gap-2">
                <button type="button" class="rounded-full border px-3 py-1 text-sm" :class="cat === '' ? 'border-slate-900 bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" x-on:click="cat = ''">All</button>
                @foreach(\App\Models\ShopItem::CATEGORIES as $k => $label)
                    @if($items->where('category', $k)->isNotEmpty())<button type="button" class="rounded-full border px-3 py-1 text-sm" :class="cat === '{{ $k }}' ? 'border-slate-900 bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" x-on:click="cat = '{{ $k }}'">{{ $label }}</button>@endif
                @endforeach
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($items as $i)
                    <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" x-show="cat === '' || cat === '{{ $i->category }}'" x-data="{ size: @js($i->sizes[0] ?? null), qty: 1 }">
                        <div class="flex aspect-[4/3] items-center justify-center bg-slate-100 dark:bg-slate-800">
                            @if($i->image_path)<img src="{{ route('shop.image', $i) }}" alt="{{ $i->name }}" class="h-full w-full object-cover" loading="lazy">@else<x-icon name="tag" class="h-10 w-10 text-slate-300" />@endif
                        </div>
                        <div class="flex flex-1 flex-col p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ \App\Models\ShopItem::CATEGORIES[$i->category] ?? $i->category }}</p>
                            <p class="font-semibold">{{ $i->name }}</p>
                            @if($i->description)<p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $i->description }}</p>@endif
                            <p class="mt-2 text-xl font-bold tabular-nums">{{ $peso($i->price) }}</p>
                            @if($i->stock !== null)<p class="text-xs {{ $i->stock ? 'text-slate-500' : 'text-rose-600' }}">{{ $i->stock ? $i->stock.' left' : 'Sold out' }}</p>@endif
                            <div class="mt-auto flex flex-wrap items-center gap-2 pt-3">
                                @if($i->sizes)
                                    <select x-model="size" class="input h-9 w-auto py-0 text-sm">@foreach($i->sizes as $sz)<option>{{ $sz }}</option>@endforeach</select>
                                @endif
                                <input type="number" min="1" max="20" x-model.number="qty" class="input h-9 w-16 py-0 text-center text-sm" aria-label="Quantity">
                                @if($canOrder && ($i->stock === null || $i->stock > 0))
                                    <button type="button" class="btn btn-primary btn-sm ml-auto" x-on:click="add({{ $i->id }}, size, qty)">Add to cart</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="card text-slate-500 sm:col-span-3">The bookstore is empty for now.</p>
                @endforelse
            </div>
        </div>

        {{-- Cart --}}
        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <form method="post" action="{{ $child ? route('portal.shop.checkout', $child) : '#' }}" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">@csrf
                <h2 class="mb-3 flex items-center gap-2 font-semibold"><x-icon name="tag" class="h-4 w-4" /> Cart <span class="text-sm font-normal text-slate-500" x-text="cart.length ? '(' + cart.length + ')' : ''"></span></h2>
                <p x-show="!cart.length" class="text-sm text-slate-500">{{ $canOrder ? 'Nothing yet.' : ($child && ! $child->currentEnrollment()?->account ? 'Fees are not set up yet – ask the cashier.' : 'Ordering is not turned on for your account.') }}</p>
                <template x-for="(c, n) in cart" :key="n">
                    <div class="flex items-start gap-2 border-b border-slate-100 py-2 text-sm dark:border-slate-800">
                        <input type="hidden" :name="`cart[${n}][id]`" :value="c.id"><input type="hidden" :name="`cart[${n}][qty]`" :value="c.qty"><input type="hidden" :name="`cart[${n}][size]`" :value="c.size || ''">
                        <span class="flex-1"><span class="block font-medium" x-text="c.name"></span><span class="text-xs text-slate-500" x-text="(c.size ? 'Size ' + c.size + ' · ' : '') + c.qty + ' × ' + peso(c.price)"></span></span>
                        <span class="tabular-nums" x-text="peso(c.qty * c.price)"></span>
                        <button type="button" class="text-slate-400 hover:text-rose-600" x-on:click="cart.splice(n, 1); save()" aria-label="Remove">✕</button>
                    </div>
                </template>
                <div x-show="cart.length" class="mt-3 space-y-3">
                    <p class="flex justify-between font-semibold"><span>Total</span><span class="tabular-nums" x-text="peso(total())"></span></p>
                    <p class="text-xs text-slate-500">It's added to the balance as “Bookstore order”. You can pay it by itself or together with tuition.</p>
                    <button class="btn btn-primary w-full" x-on:click="localStorage.removeItem('ss.cart')">Place order</button>
                </div>
            </form>
            @if($orders->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-2 font-semibold">Your orders</h2>
                    @foreach($orders as $o)
                        @php $rem = $o->charge?->remaining() ?? 0; @endphp
                        <div class="border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                            <p class="flex justify-between"><b>#{{ $o->id }}</b><span class="rounded-full px-2 text-xs {{ ['placed' => 'bg-slate-100 text-slate-600', 'ready' => 'bg-emerald-100 text-emerald-700', 'claimed' => 'bg-slate-100 text-slate-500', 'cancelled' => 'bg-rose-100 text-rose-700'][$o->status] }}">{{ \App\Models\ShopOrder::STATUSES[$o->status] }}</span></p>
                            <p class="text-slate-500">{{ $o->summary() }}</p>
                            <p class="text-xs {{ $rem > 0 ? 'text-amber-700' : 'text-emerald-600' }}">{{ $o->status === 'cancelled' ? '' : ($rem > 0 ? $peso($rem).' to pay' : 'Paid') }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function shop(items) {
            return {
                items, cat: '', cart: JSON.parse(localStorage.getItem('ss.cart') || '[]'),
                peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                add(id, size, qty) {
                    const it = this.items.find((x) => x.id === id); if (!it) return;
                    const same = this.cart.find((c) => c.id === id && c.size === size);
                    same ? same.qty += Number(qty || 1) : this.cart.push({ id, name: it.name, price: it.price, size, qty: Number(qty || 1) });
                    this.save();
                },
                total() { return this.cart.reduce((s, c) => s + c.qty * c.price, 0) },
                save() { localStorage.setItem('ss.cart', JSON.stringify(this.cart)) },
            };
        }
    </script>
</x-layouts.app>
