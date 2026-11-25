{{-- Right-side bag drawer. $action: checkout URL; $note: text under the total; $extra: hidden inputs (e.g. student_id). --}}
<div x-show="bagOpen" x-cloak class="fixed inset-0 z-40 bg-slate-900/40" x-on:click="bagOpen = false" x-transition.opacity></div>
<aside x-show="bagOpen" x-cloak x-transition:enter="transition" x-transition:enter-start="translate-x-full" x-transition:leave="transition" x-transition:leave-end="translate-x-full"
       class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-label="Your bag" x-on:keydown.escape.window="bagOpen = false">
    <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h2 class="h2">Bag <span class="text-sm font-normal text-slate-500" x-text="'(' + count + ')'"></span></h2>
        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-white/10" x-on:click="bagOpen = false" aria-label="Close bag"><x-icon name="x" class="h-5 w-5" /></button>
    </header>
    <form method="post" action="{{ $action }}" class="flex min-h-0 flex-1 flex-col" x-on:submit="if (!bag.length || busy) { $event.preventDefault(); return } busy = true{{ $clearOnSubmit ?? true ? '; clear()' : '' }}">@csrf
        {{ $extra ?? '' }}
        <div class="min-h-0 flex-1 overflow-y-auto px-5">
            <p x-show="!bag.length" class="py-10 text-center text-slate-500">Your bag is empty.</p>
            <template x-for="(b, n) in bag" :key="b.id + '-' + b.size">
                <div class="flex items-center gap-3 border-b border-slate-100 py-3 dark:border-slate-800">
                    <input type="hidden" :name="`cart[${n}][id]`" :value="b.id"><input type="hidden" :name="`cart[${n}][qty]`" :value="b.qty"><input type="hidden" :name="`cart[${n}][size]`" :value="b.size || ''">
                    <div class="min-w-0 flex-1"><p class="line-clamp-2 text-sm font-medium" x-text="b.name"></p><p class="text-xs text-slate-500" x-text="(b.size ? 'Size ' + b.size + ' · ' : '') + peso(b.price)"></p></div>
                    <div class="flex items-center rounded-xl border border-slate-200 dark:border-slate-700">
                        <button type="button" class="flex h-10 w-10 items-center justify-center text-lg" x-on:click="step(n, -1)" :aria-label="'One less ' + b.name">−</button>
                        <span class="w-8 text-center tabular-nums" x-text="b.qty" aria-live="polite"></span>
                        <button type="button" class="flex h-10 w-10 items-center justify-center text-lg disabled:opacity-30" x-on:click="step(n, 1)" :disabled="left(b.id) <= 0" :aria-label="'One more ' + b.name">+</button>
                    </div>
                    <span class="w-20 text-right text-sm font-semibold tabular-nums" x-text="peso(b.qty * b.price)"></span>
                </div>
            </template>
        </div>
        <footer class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="mb-1 flex justify-between text-lg font-bold"><span>Subtotal</span><span class="tabular-nums" x-text="peso(subtotal)"></span></p>
            <p class="mb-3 text-xs text-slate-600 dark:text-slate-400">{{ $note }}</p>
            <button class="btn btn-primary min-h-12 w-full text-base" :disabled="!bag.length || busy{{ isset($disabledWhen) ? ' || '.$disabledWhen : '' }}" x-text="busy ? 'Checking out…' : 'Check out'">Check out</button>
        </footer>
    </form>
</aside>
