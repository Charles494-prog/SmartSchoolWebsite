<x-layouts.app :title="$class->subject->name.' · '.$class->section->name">
    @php
        $CW = \App\Support\Classwork::class;
        $weights = $class->weights();
        $slot = $class->slots->sortBy('starts_at')->first();
        $work = $posts->filter(fn ($p) => $CW::gradable($p->type));
        $openTerm = collect(range(1, $terms))->first(fn ($t) => ! $class->isLocked($t)) ?? 1;
        $gradableTypes = collect($CW::TYPES)->filter(fn ($t) => $t[3])->keys()->values();
    @endphp
    <div x-data="{ tab: @js(request('tab', 'stream')), filter: @js(request('tab') === 'work' ? 'work' : 'all'), create: {{ $errors->any() || request()->has('create') ? 'true' : 'false' }}, settings: false }">
        <a href="{{ route('assignments.index') }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />My classes</a>

        {{-- Banner --}}
        <div class="relative mt-3 overflow-hidden rounded-2xl {{ $CW::color($class) }} p-6 text-white shadow-sm sm:p-8">
            <h1 class="text-3xl font-bold">{{ $class->subject->name }}</h1>
            <p class="mt-1 text-white/85">{{ $class->section->label() }} · {{ $class->teacher?->name }}</p>
            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-white/15 px-3 py-1">{{ $students->count() }} students</span>
                @if($slot)<span class="rounded-full bg-white/15 px-3 py-1">{{ ampm(substr($slot->starts_at, 0, 5)) }}–{{ ampm(substr($slot->ends_at, 0, 5)) }}{{ $slot->room ? ' · '.$slot->room : '' }}</span>@endif
            </div>
            <button type="button" class="absolute top-4 right-4 rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium backdrop-blur hover:bg-white/25" x-on:click="settings = !settings">Card colour</button>
            <form x-show="settings" x-cloak method="post" action="{{ route('assignments.color', $class) }}" class="mt-4 flex flex-wrap items-center gap-2">@csrf
                <span class="text-sm text-white/85">Default colour for your students:</span>
                @foreach($CW::COLORS as $key => $grad)
                    <button name="color" value="{{ $key }}" class="h-8 w-8 rounded-full {{ $grad }} ring-2 {{ $CW::colorKey($class) === $key ? 'ring-white' : 'ring-white/30' }}" aria-label="{{ ucfirst($key) }}"></button>
                @endforeach
            </form>
        </div>

        {{-- Tabs --}}
        <div class="mt-5 flex flex-wrap items-center gap-1 border-b border-slate-200 dark:border-slate-800" role="tablist">
            @foreach(['stream' => 'Stream', 'work' => 'Classwork', 'people' => 'People'] as $k => $label)
                <button type="button" role="tab" :aria-selected="tab === '{{ $k }}'" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold" :class="tab === '{{ $k }}' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'" x-on:click="tab = '{{ $k }}'; filter = '{{ $k === 'work' ? 'work' : 'all' }}'">{{ $label }}</button>
            @endforeach
            <a href="{{ route('gradebook.show', $class) }}" class="-mb-px border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">Grades</a>
            <button type="button" class="btn btn-primary mb-2 ml-auto" x-on:click="create = true"><x-icon name="plus" class="h-4 w-4" /> Create</button>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
            {{-- Posts: Stream shows everything, Classwork only graded work --}}
            <div x-show="tab !== 'people'" class="space-y-3">
                <div x-show="tab === 'work'" class="flex flex-wrap gap-2">
                    @foreach(['work' => 'All classwork'] + collect($CW::TYPES)->filter(fn ($t) => $t[3])->map(fn ($t) => \Illuminate\Support\Str::plural($t[0]))->all() as $k => $label)
                        <button type="button" class="rounded-full border px-3 py-1 text-sm" :class="filter === '{{ $k }}' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'" x-on:click="filter = '{{ $k }}'">{{ $label }}</button>
                    @endforeach
                </div>
                @forelse($posts as $p)
                    @include('assignments._post', ['p' => $p, 'teacher' => true, 'sub' => null, 'students' => $students->count()])
                @empty
                    <div class="card py-12 text-center">
                        <p class="text-lg font-semibold">Nothing posted yet</p>
                        <p class="muted mt-1">Click <b>Create</b> to post an announcement, a document, or classwork.</p>
                    </div>
                @endforelse
            </div>

            {{-- People --}}
            <div x-show="tab === 'people'" x-cloak class="card lg:col-span-2">
                <h2 class="h2 mb-1">Teacher</h2>
                <p class="mb-5">{{ $class->teacher?->name ?? '—' }}</p>
                <h2 class="h2 mb-2">Students <span class="text-base font-normal text-slate-500">({{ $students->count() }})</span></h2>
                <ul class="grid gap-x-6 sm:grid-cols-2">
                    @foreach($students as $s)
                        <li class="flex items-center gap-3 border-b border-slate-100 py-2.5 dark:border-slate-800">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ strtoupper(substr($s->first_name, 0, 1).substr($s->last_name, 0, 1)) }}</span>
                            <span>{{ $s->full_name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Side: how grades are made --}}
            <aside x-show="tab !== 'people'" class="space-y-3">
                <div class="card">
                    <h2 class="font-semibold">How this class is graded</h2>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach($CW::COMPONENTS as $k => $label)
                            <li class="flex justify-between gap-2"><span>{{ $label }}</span><b>{{ $weights[$k] ?? 0 }}%</b></li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-slate-500">When you create classwork, pick where it counts. Graded work goes into the gradebook by itself.</p>
                    <a href="{{ route('gradebook.show', $class) }}" class="btn btn-sm mt-3 w-full">Open gradebook</a>
                </div>
                <div class="card">
                    <h2 class="font-semibold">Coming up</h2>
                    @forelse($work->filter(fn ($p) => $p->due_at?->isFuture())->sortBy('due_at')->take(4) as $p)
                        <p class="mt-2 text-sm"><span class="block font-medium">{{ $p->title }}</span><span class="text-slate-500">{{ $p->due_at->format('D, M d') }}</span></p>
                    @empty
                        <p class="mt-2 text-sm text-slate-500">Nothing due soon.</p>
                    @endforelse
                </div>
            </aside>
        </div>

        {{-- Create panel --}}
        <div x-show="create" x-cloak class="fixed inset-0 z-[80] flex justify-end bg-black/40" x-on:keydown.escape.window="create = false" x-on:click.self="create = false">
            <div class="h-full w-full max-w-2xl overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900"
                 x-data="{ type: @js(old('type', request('type', 'assignment'))), counts: @js((string) old('component', request('component', ''))), design: {{ old('body_html') || old('body_js') ? 'true' : 'false' }}, gradable: @js($gradableTypes) }">
                <div class="mb-5 flex items-center">
                    <h2 class="h2 mr-auto">Create</h2>
                    <button type="button" class="btn btn-sm" x-on:click="create = false" aria-label="Close"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
                <form method="post" action="{{ route('assignments.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <input type="hidden" name="class_subject_id" value="{{ $class->id }}">
                    <fieldset>
                        <legend class="label">What are you posting?</legend>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($CW::TYPES as $k => [$label, $icon])
                                <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-slate-200 p-3 text-sm font-medium has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-500/10">
                                    <input type="radio" name="type" value="{{ $k }}" class="sr-only" x-model="type">
                                    <x-icon :name="$icon" class="h-5 w-5" /> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <x-field name="title" label="Title" required />
                    <x-field name="instructions" label="Message / instructions" type="textarea" />

                    <div x-show="gradable.includes(type)" class="space-y-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field name="due_at" label="Due" type="datetime-local" :value="now()->addWeek()->setTime(17, 0)->format('Y-m-d\TH:i')" />
                            <x-field name="max_score" label="Points" type="number" value="100" />
                        </div>
                        <div>
                            <span class="label">Does it count toward the grade?</span>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-500/10">
                                    <input type="radio" name="component" value="" x-model="counts"> <span><b>Not included</b><span class="block text-xs text-slate-500">Practice only – students still see the score</span></span>
                                </label>
                                @foreach($CW::COMPONENTS as $k => $label)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-500/10">
                                        <input type="radio" name="component" value="{{ $k }}" x-model="counts"> <span><b>{{ $label }}</b><span class="block text-xs text-slate-500">{{ $weights[$k] ?? 0 }}% of the grade</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <label x-show="counts" class="block">
                            <span class="label">Grading period</span>
                            <select name="term" class="input" :disabled="!counts">
                                @foreach(range(1, $terms) as $t)
                                    <option value="{{ $t }}" @selected((int) old('term', request('term', $openTerm)) === $t) @disabled($class->isLocked($t))>{{ \App\Support\School::periodName($terms) }} {{ $t }}{{ $class->isLocked($t) ? ' – already submitted' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    {{-- Moodle-style design --}}
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700">
                        <button type="button" class="flex w-full items-center justify-between p-4 text-left text-sm font-semibold" x-on:click="design = !design">
                            <span>Design it yourself (paste HTML / CSS / JS code) <span class="font-normal text-slate-500">– optional</span></span>
                            <span :class="design && 'rotate-90'" class="transition"><x-icon name="chevron" class="h-4 w-4" /></span>
                        </button>
                        <div x-show="design" x-cloak class="space-y-3 border-t border-slate-100 p-4 dark:border-slate-800">
                            <p class="text-xs text-slate-500">Shown inside its own box on the post, so your styles and scripts only affect that box – never the rest of SmartSchool. You can paste embed codes too (YouTube, an <b>H5P</b> embed from h5p.org or Lumi, Google Slides).</p>
                            <label class="block"><span class="label">Your code</span>
                                <textarea name="body_html" rows="14" spellcheck="false" class="input font-mono text-xs" placeholder="Paste one whole block – HTML with <style> and <script> inside, or a full page (<!doctype html>…). Embed codes work too.">{{ old('body_html') }}</textarea>
                            </label>
                        </div>
                    </div>

                    <x-field name="attachment" label="Attach a file (optional, up to 20 MB – PDF, slides, worksheet…)" type="file" />
                    <button class="btn btn-primary w-full">Post to {{ $class->section->name }}</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
