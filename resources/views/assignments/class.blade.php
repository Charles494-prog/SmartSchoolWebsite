<x-layouts.app :title="$class->subject->name.' · '.$class->section->name">
    @php $typeInfo = \App\Support\Classwork::TYPES; @endphp
    <div x-data="{ tab: 'work', filter: 'all', create: {{ $errors->any() ? 'true' : 'false' }} }">
        <a href="{{ route('assignments.index') }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />All class cards</a>

        {{-- Banner --}}
        <div class="mt-3 overflow-hidden rounded-2xl bg-gradient-to-br {{ \App\Support\Classwork::color($class) }} p-6 text-white shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold">{{ $class->subject->name }}</h1>
            <p class="mt-1 text-white/85">{{ $class->section->label() }} · {{ $class->teacher?->name }}</p>
            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-white/15 px-3 py-1">{{ $students->count() }} students</span>
                @php $slot = $class->slots->sortBy('starts_at')->first(); @endphp
                @if($slot)<span class="rounded-full bg-white/15 px-3 py-1">{{ ampm(substr($slot->starts_at, 0, 5)) }}–{{ ampm(substr($slot->ends_at, 0, 5)) }} · {{ $slot->room }}</span>@endif
                <a href="{{ route('gradebook.show', $class) }}" class="rounded-full bg-white px-3 py-1 font-medium text-slate-900 hover:bg-white/90">Open gradebook</a>
            </div>
        </div>

        {{-- Tabs + create --}}
        <div class="mt-5 flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800" role="tablist">
            <button type="button" role="tab" :aria-selected="tab === 'work'" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold" :class="tab === 'work' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-800'" x-on:click="tab = 'work'">Classwork</button>
            <button type="button" role="tab" :aria-selected="tab === 'people'" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold" :class="tab === 'people' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-800'" x-on:click="tab = 'people'">People</button>
            <button type="button" class="btn btn-primary mb-2 ml-auto" x-on:click="create = true"><x-icon name="plus" class="h-4 w-4" /> Create</button>
        </div>

        {{-- Classwork --}}
        <div x-show="tab === 'work'" class="mt-5">
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach(['all' => 'All'] + collect($typeInfo)->map(fn ($t) => \Illuminate\Support\Str::plural($t[0]))->all() as $k => $label)
                    <button type="button" class="rounded-full border px-3 py-1 text-sm" :class="filter === '{{ $k }}' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" x-on:click="filter = '{{ $k }}'">{{ $label }}</button>
                @endforeach
            </div>
            <div class="space-y-3">
                @forelse($posts as $p)
                    @php [$tLabel, $tIcon, $tClass] = $typeInfo[$p->type] ?? $typeInfo['assignment']; $toCheck = $p->submissions_count - $p->graded_count; @endphp
                    <a href="{{ route('assignments.show', $p) }}" x-show="filter === 'all' || filter === '{{ $p->type }}'" class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:shadow dark:border-slate-800 dark:bg-slate-900">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $tClass }}"><x-icon :name="$tIcon" class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-semibold text-slate-900 dark:text-white">{{ $p->title }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tClass }}">{{ $tLabel }}</span>
                            </div>
                            <div class="text-sm {{ $p->due_at->isPast() ? 'text-slate-500' : 'text-slate-700 dark:text-slate-300' }}">Due {{ $p->due_at->format('D, M d · g:i A') }} · out of {{ rtrim(rtrim(number_format($p->max_score, 2), '0'), '.') }}</div>
                        </div>
                        <div class="hidden text-right text-sm sm:block">
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $p->submissions_count }}/{{ $students->count() }} turned in</div>
                            <div class="{{ $toCheck ? 'font-medium text-amber-600' : 'text-slate-500' }}">{{ $toCheck ? $toCheck.' to check' : 'All checked' }}</div>
                        </div>
                    </a>
                @empty
                    <div class="card py-12 text-center">
                        <p class="text-lg font-semibold">No classwork yet</p>
                        <p class="muted mt-1">Click <b>Create</b> to post the first assignment, project or activity.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- People --}}
        <div x-show="tab === 'people'" x-cloak class="mt-5 grid gap-4 lg:grid-cols-3">
            <div class="card h-fit">
                <h2 class="h2 mb-3">Teacher</h2>
                <p class="font-medium">{{ $class->teacher?->name ?? '—' }}</p>
            </div>
            <div class="card lg:col-span-2">
                <h2 class="h2 mb-3">Students <span class="text-base font-normal text-slate-500">({{ $students->count() }})</span></h2>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($students as $s)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ strtoupper(substr($s->first_name, 0, 1).substr($s->last_name, 0, 1)) }}</span>
                            <span>{{ $s->full_name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Create panel --}}
        <div x-show="create" x-cloak class="fixed inset-0 z-[80] flex justify-end bg-black/40" x-on:keydown.escape.window="create = false" x-on:click.self="create = false">
            <div class="h-full w-full max-w-lg overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900" x-transition>
                <div class="mb-4 flex items-center">
                    <h2 class="h2 mr-auto">New classwork</h2>
                    <button type="button" class="btn btn-sm" x-on:click="create = false" aria-label="Close"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
                <form method="post" action="{{ route('assignments.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="class_subject_id" value="{{ $class->id }}">
                    <fieldset>
                        <legend class="label">Type</legend>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($typeInfo as $k => [$label, $icon, $cls])
                                <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-slate-200 p-3 text-sm font-medium has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-500/10">
                                    <input type="radio" name="type" value="{{ $k }}" class="sr-only" @checked(old('type', 'assignment') === $k)>
                                    <x-icon :name="$icon" class="h-5 w-5" /> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <x-field name="title" label="Title" required />
                    <x-field name="instructions" label="Instructions" type="textarea" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="due_at" label="Due" type="datetime-local" :value="now()->addWeek()->setTime(17, 0)->format('Y-m-d\TH:i')" required />
                        <x-field name="max_score" label="Points" type="number" value="100" required />
                    </div>
                    <x-field name="attachment" label="Attachment (optional)" type="file" />
                    <button class="btn btn-primary w-full">Post to {{ $class->section->name }}</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
