<x-layouts.app title="Library">
    @php
        $spine = ['bg-indigo-600', 'bg-emerald-600', 'bg-amber-600', 'bg-rose-600', 'bg-sky-600', 'bg-violet-600', 'bg-teal-600', 'bg-slate-700'];
        $studentList = $students->map(fn ($n, $id) => ['id' => $id, 'name' => $n])->values();
    @endphp
    <div x-data="{ tab: @js(request('tab', 'catalogue')), add: {{ $edit || $errors->any() ? 'true' : 'false' }}, lend: null, lendTitle: '', q: '', who: null, students: @js($studentList),
                   get hits() { const t = this.q.trim().toLowerCase(); return t ? this.students.filter((s) => s.name.toLowerCase().includes(t)).slice(0, 8) : [] } }">
        <div class="mb-5 flex flex-wrap items-end gap-3">
            <div class="mr-auto"><h1 class="h1">Library</h1><p class="muted">Your catalogue, what's out on loan, and what's late.</p></div>
            <button type="button" class="btn btn-primary" x-on:click="add = true"><x-icon name="plus" class="h-4 w-4" /> Add book</button>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat label="Titles" :value="$stats['titles']" />
            <x-stat label="Copies" :value="$stats['copies']" />
            <x-stat label="On loan" :value="$stats['out']" tone="brand" />
            <x-stat label="Late" :value="$stats['late']" :tone="$stats['late'] ? 'red' : 'green'" />
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
                <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="tab === 'catalogue' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="tab = 'catalogue'">Catalogue</button>
                <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="tab === 'loans' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="tab = 'loans'">On loan ({{ $loans->count() }})</button>
            </div>
            <form x-show="tab === 'catalogue'" class="ml-auto flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
                    <a href="{{ route('library.index', ['group' => 'category', 'q' => request('q')]) }}" class="rounded-lg px-3 py-1.5 font-medium {{ $group === 'category' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">By category</a>
                    <a href="{{ route('library.index', ['group' => 'shelf', 'q' => request('q')]) }}" class="rounded-lg px-3 py-1.5 font-medium {{ $group === 'shelf' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">By shelf / aisle</a>
                </div>
                <input type="hidden" name="group" value="{{ $group }}">
                <div class="relative"><x-icon name="search" class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" /><input name="q" value="{{ request('q') }}" class="input h-10 w-64 pl-9" placeholder="Title, author, ISBN, shelf"></div>
            </form>
        </div>

        {{-- Catalogue --}}
        <div x-show="tab === 'catalogue'" class="space-y-8">
            @forelse($grouped as $name => $list)
                <section>
                    <h2 class="mb-3 flex items-baseline gap-2 text-lg font-semibold">{{ $name }} <span class="text-sm font-normal text-slate-400">{{ $list->count() }} {{ str('title')->plural($list->count()) }}</span></h2>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        @foreach($list as $b)
                            @php $avail = max(0, $b->copies - $b->out_count); @endphp
                            <div class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                <div class="relative aspect-[3/4] overflow-hidden">
                                    @if($b->cover_path)
                                        <img src="{{ route('library.cover', $b) }}" alt="{{ $b->title }}" class="h-full w-full object-cover" loading="lazy">
                                    @else
                                        <div class="flex h-full flex-col justify-between p-4 text-white {{ $spine[crc32($b->title) % count($spine)] }}">
                                            <span class="text-xs uppercase tracking-wider text-white/70">{{ $b->category }}</span>
                                            <span class="text-lg leading-tight font-bold">{{ $b->title }}</span>
                                            <span class="text-xs text-white/80">{{ $b->author }}</span>
                                        </div>
                                    @endif
                                    @if($b->shelf)<span class="absolute top-2 right-2 rounded-md bg-white/90 px-2 py-0.5 text-xs font-semibold text-slate-800 shadow">{{ $b->shelf }}</span>@endif
                                </div>
                                <div class="flex flex-1 flex-col p-3">
                                    <p class="line-clamp-2 text-sm font-semibold">{{ $b->title }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $b->author }}</p>
                                    <div class="mt-2 flex items-center gap-2 text-xs">
                                        <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full {{ $avail ? 'bg-emerald-500' : 'bg-rose-500' }}" style="width: {{ $b->copies ? $avail / $b->copies * 100 : 0 }}%"></span></span>
                                        <span class="{{ $avail ? 'text-emerald-700' : 'text-rose-600' }} font-medium">{{ $avail }}/{{ $b->copies }} in</span>
                                    </div>
                                    <div class="mt-3 flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm flex-1" @disabled(! $avail) x-on:click="lend = {{ $b->id }}; lendTitle = @js($b->title); q = ''; who = null">{{ $avail ? 'Lend' : 'All out' }}</button>
                                        <a href="{{ route('library.index', ['edit' => $b->id, 'group' => $group]) }}" class="btn btn-sm">Edit</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="card py-12 text-center text-slate-500">No books {{ request('q') ? 'match “'.request('q').'”' : 'yet – add your first one' }}.</div>
            @endforelse
        </div>

        {{-- On loan --}}
        <div x-show="tab === 'loans'" x-cloak class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($loans as $l)
                    @php $late = $l->due_on->isPast() && ! $l->due_on->isToday(); @endphp
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3.5">
                        <div class="min-w-0 flex-1"><p class="font-medium">{{ $l->book->title }}</p><p class="text-sm text-slate-500">{{ $l->student?->full_name ?? $l->user?->name }} · borrowed {{ $l->borrowed_on->format('M d') }}{{ $l->copies > 1 ? ' · '.$l->copies.' copies' : '' }}{{ $l->condition ? ' · '.ucfirst($l->condition) : '' }}</p>@if($l->note)<p class="text-xs text-slate-500">“{{ $l->note }}”</p>@endif</div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $late ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600' }}">{{ $late ? 'Late · was due '.$l->due_on->format('M d') : 'Due '.$l->due_on->format('M d') }}</span>
                        <form method="post" action="{{ route('library.return', $l) }}">@csrf<button class="btn btn-sm">Returned</button></form>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-slate-500">No books are out.</li>
                @endforelse
            </ul>
        </div>

        {{-- Lend dialog --}}
        <div x-show="lend" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4" x-on:keydown.escape.window="lend = null" x-on:click.self="lend = null">
            <form method="post" action="{{ route('library.borrow') }}" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">@csrf
                <input type="hidden" name="book_id" :value="lend"><input type="hidden" name="student_id" :value="who?.id">
                <h2 class="text-lg font-semibold">Lend “<span x-text="lendTitle"></span>”</h2>
                <p class="mb-4 text-sm text-slate-500">Choose the borrower, due date and details.</p>
                <div class="relative">
                    <input x-model="q" x-on:input="who = null" class="input" placeholder="Type the student's name" autocomplete="off">
                    <ul x-show="hits.length && !who" class="absolute z-10 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <template x-for="s in hits" :key="s.id"><li><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5" x-on:click="who = s; q = s.name" x-text="s.name"></button></li></template>
                    </ul>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="block"><span class="label">Due back</span><input type="date" name="due_on" value="{{ today()->addDays((int) (\App\Models\Setting::get('library_loan_days') ?: 7))->toDateString() }}" min="{{ today()->toDateString() }}" class="input"></label>
                    <label class="block"><span class="label">Copies</span><input type="number" name="copies" value="1" min="1" max="10" class="input"></label>
                </div>
                <div class="mt-3"><span class="label">Condition when lent</span>
                    <div class="grid grid-cols-4 gap-2">@foreach(['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'worn' => 'Worn'] as $k => $v)
                        <label class="cursor-pointer rounded-lg py-1.5 text-center text-sm ring-1 ring-slate-200 has-[:checked]:bg-brand-50 has-[:checked]:font-semibold has-[:checked]:ring-2 has-[:checked]:ring-brand-500 dark:ring-slate-700"><input type="radio" name="condition" value="{{ $k }}" class="sr-only" @checked($k === 'good')>{{ $v }}</label>@endforeach</div>
                </div>
                <label class="mt-3 block"><span class="label">Note (optional)</span><input name="note" class="input" placeholder="e.g. For the book report, cover slightly torn"></label>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="btn" x-on:click="lend = null">Cancel</button><button class="btn btn-primary" :disabled="!who">Lend</button></div>
            </form>
        </div>

        {{-- Add / edit book --}}
        <div x-show="add" x-cloak class="fixed inset-0 z-[80] flex justify-end bg-black/40" x-on:keydown.escape.window="add = false" x-on:click.self="add = false">
            <form method="post" action="{{ $edit ? route('library.books.update', $edit) : route('library.books.store') }}" enctype="multipart/form-data" class="h-full w-full max-w-md space-y-3 overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900">@csrf
                <div class="flex items-center"><h2 class="h2 mr-auto">{{ $edit ? 'Edit book' : 'Add a book' }}</h2><a href="{{ route('library.index') }}" class="btn btn-sm" x-on:click.prevent="add = false">Close</a></div>
                <x-field name="title" label="Title" :value="$edit?->title" required />
                <x-field name="author" label="Author" :value="$edit?->author" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="category" label="Category" :value="$edit?->category" placeholder="e.g. Textbook" />
                    <x-field name="shelf" label="Shelf / aisle" :value="$edit?->shelf" placeholder="e.g. A4" />
                    <x-field name="copies" label="Copies" type="number" :value="$edit?->copies ?? 1" required />
                    <x-field name="isbn" label="ISBN" :value="$edit?->isbn" />
                </div>
                <x-field name="publisher" label="Publisher" :value="$edit?->publisher" />
                <div x-data="{ prev: @js($edit?->cover_path ? route('library.cover', $edit) : null) }">
                    <span class="label">Cover photo (optional)</span>
                    <label class="flex cursor-pointer items-center gap-4 rounded-xl border-2 border-dashed border-slate-300 p-3 hover:border-brand-400 hover:bg-brand-50/40 dark:border-slate-700">
                        <span class="flex h-24 w-[4.5rem] shrink-0 items-center justify-center overflow-hidden rounded-md bg-slate-100 dark:bg-slate-800">
                            <img x-show="prev" :src="prev" alt="" class="h-full w-full object-cover"><x-icon name="file" class="h-6 w-6 text-slate-400" x-show="!prev" />
                        </span>
                        <span class="text-sm"><span class="block font-medium text-slate-800 dark:text-slate-100" x-text="prev ? 'Change cover' : 'Upload a cover'"></span><span class="text-xs text-slate-500">JPG or PNG. Without one, a coloured cover with the title is used.</span></span>
                        <input type="file" name="cover" accept="image/*" class="sr-only" x-on:change="prev = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : prev">
                    </label>
                </div>
                <button class="btn btn-primary w-full">Save</button>
            </form>
        </div>
    </div>
</x-layouts.app>
