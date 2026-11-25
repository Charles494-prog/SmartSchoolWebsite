{{-- Search, sort, category chips and the product grid. $manager: show add/edit/delete; $canAdd: show "Add to bag". --}}
<div class="mb-4 flex flex-wrap items-center gap-2">
    <label class="relative min-w-0 flex-1 basis-56"><span class="sr-only">Search items</span><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="search" x-model="q" class="input min-h-11 pl-9" placeholder="Search books, uniforms, merch…"></label>
    <label class="sr-only" for="shop-sort">Sort</label>
    <select id="shop-sort" x-model="sort" class="input min-h-11 w-auto"><option value="name">Name A–Z</option><option value="low">Price: low to high</option><option value="high">Price: high to low</option></select>
</div>
<div class="mb-4 flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Category">
    <button type="button" class="min-h-10 shrink-0 rounded-full border px-4 text-sm font-medium" :aria-pressed="cat === ''" :class="cat === '' ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'" x-on:click="cat = ''">All</button>
    @foreach(\App\Models\ShopItem::CATEGORIES as $k => $label)
        <button type="button" class="min-h-10 shrink-0 rounded-full border px-4 text-sm font-medium" :aria-pressed="cat === '{{ $k }}'" :class="cat === '{{ $k }}' ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'" x-on:click="cat = '{{ $k }}'" x-show="items.some((i) => i.category === '{{ $k }}')">{{ $label }}</button>
    @endforeach
</div>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
    @if($manager)
        <button type="button" x-on:click="$dispatch('item-edit', null)" class="flex min-h-64 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 text-slate-600 hover:border-brand-500 hover:text-brand-700 dark:border-slate-700 dark:text-slate-300">
            <x-icon name="plus" class="h-8 w-8" /><span class="font-semibold">Add item</span></button>
    @endif
    <template x-for="i in shown" :key="i.id">
        <article class="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900" :class="i.stock === 0 ? 'opacity-70' : ''">
            <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden bg-slate-100 dark:bg-slate-800">
                <template x-if="i.image"><img :src="i.image" :alt="i.name" class="absolute inset-0 h-full w-full object-cover" loading="lazy"></template>
                <template x-if="!i.image"><svg viewBox="0 0 24 24" class="h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></template>
                <span x-show="stockChip(i)" class="absolute left-2 top-2 z-10 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="i.stock === 0 ? 'bg-slate-900 text-white' : (i.stock <= 5 ? 'bg-rose-600 text-white' : 'bg-white/90 text-slate-700')" x-text="stockChip(i)"></span>
                @if($manager)
                    <span class="absolute right-2 top-2 z-10 flex gap-1">
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow hover:text-brand-700" :aria-label="'Edit ' + i.name" x-on:click="$dispatch('item-edit', i)"><x-icon name="pencil" class="h-4 w-4" /></button>
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow hover:text-rose-700" :aria-label="'Delete ' + i.name" x-on:click="$dispatch('item-delete', i)"><x-icon name="x" class="h-4 w-4" /></button>
                    </span>
                @endif
            </div>
            <div class="flex flex-1 flex-col p-3">
                <p class="line-clamp-2 min-h-10 text-sm leading-5" x-text="i.name" :title="i.name"></p>
                <p class="mt-1 text-lg font-bold tabular-nums text-brand-700 dark:text-brand-300" x-text="peso(i.price)"></p>
                <p class="text-xs text-slate-500" x-show="i.sizes && i.sizes.length" x-text="'Sizes: ' + (i.sizes || []).join(', ')"></p>
                @if($canAdd)
                    <button type="button" class="btn mt-auto min-h-11 w-full" :class="left(i.id) > 0 ? 'btn-primary' : ''" :disabled="left(i.id) <= 0" x-on:click="add(i.id)"
                            x-text="i.stock === 0 ? 'Out of stock' : (left(i.id) <= 0 ? 'All in your bag' : 'Add to bag')"></button>
                @endif
            </div>
        </article>
    </template>
</div>
<p x-show="!shown.length" x-cloak class="card mt-3 text-center text-slate-500" x-text="items.length ? 'No items match your search.' : 'The bookstore is empty for now.'"></p>

{{-- size picker --}}
<div x-show="sizeFor" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" x-on:keydown.escape.window="sizeFor = null">
    <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-labelledby="size-title" x-on:click.outside="sizeFor = null">
        <h2 id="size-title" class="h2 mb-3" x-text="sizeFor ? 'Choose a size – ' + item(sizeFor).name : ''"></h2>
        <div class="flex flex-wrap gap-2"><template x-for="sz in (sizeFor ? item(sizeFor).sizes : [])"><button type="button" class="min-h-11 min-w-11 rounded-xl border-2 px-3 font-semibold" :class="size === sz ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-slate-200 dark:border-slate-700'" x-on:click="size = sz" x-text="sz"></button></template></div>
        <div class="mt-4 flex gap-2"><button type="button" class="btn min-h-11 flex-1" x-on:click="sizeFor = null">Cancel</button><button type="button" class="btn btn-primary min-h-11 flex-1" x-on:click="add(sizeFor, size)">Add to bag</button></div>
    </div>
</div>
