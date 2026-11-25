@php $tileGroups = \App\Support\Nav::groupedTiles($u); @endphp
<x-layouts.app title="Home">
    {{-- Greeting --}}
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-6 dark:border-white/10">
        <div>
            <p class="text-sm text-slate-500">{{ now()->format('l, M j, Y') }} · S.Y. {{ $sy?->name ?? 'not set yet' }}</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900 sm:text-3xl">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', $u->name)[0] }}</h1>
            <p class="mt-1 text-sm text-slate-600">Signed in as {{ $u->roleLabel() }}</p>
        </div>
    </div>

    {{-- Setup checklist (admins, until done) --}}
    @isset($steps)
        @php $done = collect($steps)->where(1, true)->count(); @endphp
        @if($done < count($steps) && ! \App\Support\School::isSetUp())
            <div class="card mb-8" x-data="{ open: false }">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><x-icon name="settings" /></div>
                    <div class="flex-1">
                        <h2 class="h2">Set up your school</h2>
                        <p class="muted">{{ $done }} of {{ count($steps) }} steps done – follow them in order.</p>
                    </div>
                    <a href="{{ route('setup.wizard') }}" class="btn btn-primary btn-sm">Continue setup</a>
                    <button @click="open=!open" class="btn btn-sm" x-text="open ? 'Hide' : 'Show steps'"></button>
                </div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $done / count($steps) * 100 }}%"></div></div>
                <ol x-show="open" class="mt-5 grid gap-2 sm:grid-cols-2">
                    @foreach($steps as $i => [$label, $ok, $url])
                        <li>
                            <a href="{{ $url }}" class="flex items-center gap-3 rounded-xl p-3 ring-1 transition {{ $ok ? 'ring-slate-100 text-slate-400' : 'ring-slate-200 hover:bg-brand-50 hover:ring-brand-200' }}">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $ok ? 'bg-emerald-500 text-white' : 'bg-brand-50 text-brand-700' }}">@if($ok)<x-icon name="check" class="h-4 w-4" />@else{{ $i + 1 }}@endif</span>
                                <span class="flex-1 text-sm font-medium {{ $ok ? 'line-through' : 'text-slate-800' }}">{{ $label }}</span>
                                @unless($ok)<x-icon name="chevron" class="h-4 w-4 text-slate-400" />@endunless
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    @endisset

    {{-- Numbers at a glance --}}
    @isset($enrolledCount)
        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat icon="users" label="Students enrolled" :value="$enrolledCount" tone="brand" :href="route('students.index')" />
            @if($u->hasRole('admin', 'registrar'))<x-stat icon="inbox" label="Applications to review" :value="$pendingApps" :tone="$pendingApps ? 'amber' : 'slate'" :href="route('applications.index')" />@endif
            <x-stat icon="check-square" label="Present today ({{ $today['late'] ?? 0 }} late, {{ $today['absent'] ?? 0 }} absent)" :value="($today['present'] ?? 0) + ($today['late'] ?? 0)" tone="green" :href="route('attendance.report')" />
            @if($u->hasRole('admin', 'cashier'))<x-stat icon="wallet" label="Fees collected" :value="$rate.'%'" tone="green" :href="route('finance.index')" />@endif
        </div>
    @endisset

    @isset($classes)
        <div class="mb-8 grid gap-4 lg:grid-cols-3">
            <div class="card">
                <div class="mb-4 flex items-center gap-2"><x-icon name="clock" class="h-5 w-5 text-sky-500" /><h2 class="h2">Today's classes</h2></div>
                <div class="space-y-2">
                    @forelse($todaySlots as $s)
                        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3"><div class="w-20 shrink-0 text-sm font-semibold text-brand-600 dark:text-brand-200">{{ ampm($s->starts_at) }}</div><div class="min-w-0"><div class="truncate text-sm font-semibold">{{ $s->classSubject->subject->name }}</div><div class="truncate text-xs text-slate-500">{{ $s->classSubject->section->label() }} {{ $s->room ? '· '.$s->room : '' }}</div></div></div>
                    @empty <p class="muted">No classes today.</p> @endforelse
                </div>
            </div>
            <div class="card">
                <div class="mb-4 flex items-center gap-2"><x-icon name="book-open" class="h-5 w-5 text-amber-500" /><h2 class="h2">My classes</h2></div>
                <div class="space-y-1">
                    @forelse($classes as $c)
                        <a href="{{ route('gradebook.show', $c) }}" class="flex items-center justify-between rounded-xl p-2.5 text-sm hover:bg-slate-50"><span><b>{{ $c->subject->name }}</b> <span class="text-slate-500">· {{ $c->section->label() }}</span></span><x-icon name="chevron" class="h-4 w-4 text-slate-300" /></a>
                    @empty <p class="muted">No classes assigned yet. The registrar assigns them.</p> @endforelse
                </div>
            </div>
            <div class="card">
                <div class="mb-4 flex items-center gap-2"><x-icon name="users" class="h-5 w-5 text-brand-500" /><h2 class="h2">{{ $advisory->count() > 1 ? 'My advisory classes' : 'My advisory class' }}</h2></div>
                <div class="divide-y divide-slate-100 dark:divide-white/5">
                    @forelse($advisory as $s)
                        <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-600 dark:bg-white/10 dark:text-slate-200">{{ preg_replace('/\D/', '', $s->gradeLevel->name) ?: 'K' }}</div>
                            <a href="{{ route('students.index', ['section_id' => $s->id]) }}" class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-slate-900 hover:text-brand-600">{{ $s->label() }}</div>
                                <div class="text-xs text-slate-500">{{ $s->enrollments_count }} {{ \Illuminate\Support\Str::plural('student', $s->enrollments_count) }}{{ $s->room ? ' · '.$s->room : '' }}</div>
                            </a>
                            <a href="{{ route('attendance.sheet', ['section_id' => $s->id]) }}" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10" title="Take attendance"><x-icon name="check-square" class="h-[18px] w-[18px]" /></a>
                        </div>
                    @empty
                        <p class="muted">You are not an adviser this school year.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endisset

    {{-- What would you like to do? – every feature, grouped, with a live filter --}}
    <section class="mb-8" x-data="{ q: '', match(text) { const t = this.q.trim().toLowerCase(); return !t || text.includes(t) } }" aria-labelledby="todo-h">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 id="todo-h" class="text-lg font-bold text-slate-900">What would you like to do?</h2><p class="muted">Everything you can open with your account, grouped by area.</p></div>
            <label class="relative block w-full sm:w-72">
                <span class="sr-only">Filter features</span>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" x-model="q" x-on:keydown.escape="q = ''" class="input min-h-11 pl-9" placeholder="Type to filter, e.g. “Su”" autocomplete="off">
            </label>
        </div>
        <div class="space-y-6">
            @foreach($tileGroups as $group => $items)
                @php $groupText = collect($items)->map(fn ($i) => mb_strtolower($i['label'].' '.$i['desc'].' '.$group))->implode(' | '); @endphp
                <div x-show="match(@js($groupText))" x-transition.opacity.duration.200ms>
                    <h3 class="mb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ $group }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($items as $t)
                            <div x-show="match(@js(mb_strtolower($t['label'].' '.$t['desc'].' '.$group)))" x-transition.opacity.duration.200ms><x-tile :item="$t" /></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <p x-cloak x-show="q && !@js(collect($tileGroups)->flatMap(fn ($items, $g) => collect($items)->map(fn ($i) => mb_strtolower($i['label'].' '.$i['desc'].' '.$g)))->values()).some(t => match(t))" class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">No feature matches “<span x-text="q"></span>”. Try another word.</p>
    </section>

    @isset($enrolledCount)
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="card">
                <h2 class="h2 mb-4">Students per grade level</h2>
                @php $max = max(1, $byLevel->max(1) ?? 1); @endphp
                <div class="space-y-3">
                    @forelse($byLevel as [$name, $n])
                        <div class="text-sm"><div class="mb-1 flex justify-between"><span class="text-slate-600">{{ $name }}</span><b>{{ $n }}</b></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $n / $max * 100 }}%"></div></div></div>
                    @empty <p class="muted">No enrollments yet.</p> @endforelse
                </div>
            </div>
            <div class="card">
                <h2 class="h2 mb-4">Enrollment per school year</h2>
                @php $tmax = max(1, $trend->max(1) ?? 1); @endphp
                <div class="flex h-44 items-end gap-3">
                    @forelse($trend as [$name, $n])
                        <div class="flex flex-1 flex-col items-center gap-1"><span class="text-xs font-bold">{{ $n }}</span><div class="w-full rounded-t-lg bg-brand-600" style="height: {{ max(6, $n / $tmax * 130) }}px"></div><span class="text-[11px] text-slate-500">{{ $name }}</span></div>
                    @empty <p class="muted">No data yet.</p> @endforelse
                </div>
            </div>
            <div class="card">
                <h2 class="h2 mb-4">Needs attention: failing grades</h2>
                <div class="space-y-2">
                    @forelse($failing as $f)
                        <a href="{{ route('students.show', $f['student']) }}" class="flex items-center gap-3 rounded-xl p-2 hover:bg-slate-50">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-sm font-bold text-rose-600">{{ $f['grade'] }}</span>
                            <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $f['student']->full_name }}</span><span class="block truncate text-xs text-slate-500">{{ $f['subject'] }} · Q{{ $f['term'] }} · {{ $f['section'] }}</span></span>
                        </a>
                    @empty <p class="muted">Nobody is failing right now.</p> @endforelse
                </div>
            </div>
        </div>
    @endisset
</x-layouts.app>
