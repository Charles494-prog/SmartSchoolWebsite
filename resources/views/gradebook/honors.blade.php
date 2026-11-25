<x-layouts.app title="Top achievers & awards">
    @php
        $S = \App\Models\Setting::class;
        $isAdmin = auth()->user()->hasRole('admin', 'registrar');
        $period = $term ? \App\Support\School::periodName($terms).' '.$term : 'Whole school year';
        $where = $scope === 'level' ? ($section?->gradeLevel->name.' – all sections') : $section?->label();
        $honorStyle = ['With Highest Honors' => 'bg-amber-100 text-amber-800 ring-amber-300', 'With High Honors' => 'bg-slate-100 text-slate-700 ring-slate-300', 'With Honors' => 'bg-orange-100 text-orange-800 ring-orange-300'];
        $counts = $top->groupBy('honor')->map->count();
        $n = \App\Services\Honors::topN();
    @endphp

    <div x-data="{ tab: @js(request('tab', 'top')) }">
        {{-- Filters --}}
        <form class="mb-6 flex flex-wrap items-end gap-3 print:hidden" x-ref="f">
            <div class="mr-auto">
                <h1 class="h1">Top achievers &amp; awards</h1>
                <p class="muted">{{ $where }} · {{ $period }}</p>
            </div>
            <input type="hidden" name="tab" :value="tab">
            <label><span class="mb-1 block text-xs font-medium text-slate-500">Section</span>
                <select name="section_id" class="input h-11" x-on:change="$refs.f.submit()">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected($section?->id == $k)>{{ $v }}</option>@endforeach</select></label>
            <label><span class="mb-1 block text-xs font-medium text-slate-500">Show</span>
                <select name="scope" class="input h-11" x-on:change="$refs.f.submit()"><option value="section" @selected($scope === 'section')>This section</option><option value="level" @selected($scope === 'level')>Whole grade level</option></select></label>
            <label><span class="mb-1 block text-xs font-medium text-slate-500">Period</span>
                <select name="term" class="input h-11" x-on:change="$refs.f.submit()">
                    @foreach(range(1, $terms) as $t)<option value="{{ $t }}" @selected($term === $t)>{{ \App\Support\School::periodName($terms) }} {{ $t }}</option>@endforeach
                    <option value="final" @selected($term === null)>Whole year (final)</option>
                </select></label>
            <button type="button" x-on:click="window.print()" class="btn h-11">Print</button>
        </form>
        <h1 class="mb-4 hidden text-2xl font-bold print:block">Top achievers – {{ $where }} · {{ $period }}</h1>

        {{-- Tabs --}}
        <div class="mb-5 flex flex-wrap gap-1 border-b border-slate-200 print:hidden dark:border-slate-800">
            @foreach(['top' => 'Top '.$n, 'subjects' => 'Best in subject', 'attendance' => 'Perfect attendance', 'awards' => 'Special awards'.($pending->count() ? ' ('.$pending->count().')' : '')] as $k => $label)
                <button type="button" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold" :class="tab === '{{ $k }}' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-800'" x-on:click="tab = '{{ $k }}'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- TOP N --}}
        <div x-show="tab === 'top'">
            <div class="mb-6 grid gap-3 sm:grid-cols-4">
                @foreach(['With Highest Honors' => $S::get('honors_with_highest'), 'With High Honors' => $S::get('honors_with_high'), 'With Honors' => $S::get('honors_with')] as $h => $min)
                    <div class="rounded-2xl p-4 ring-1 {{ $honorStyle[$h] }}"><div class="text-3xl font-bold">{{ $counts[$h] ?? 0 }}</div><div class="text-sm font-semibold">{{ $h }}</div><div class="text-xs opacity-75">average {{ $min }} and up</div></div>
                @endforeach
                <div class="rounded-2xl bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800"><div class="text-3xl font-bold">{{ $top->count() }}</div><div class="text-sm font-semibold">Top {{ $n }} list</div><div class="text-xs text-slate-500">from {{ $ranked }} students with passing grades</div></div>
            </div>

            @if($top->count() >= 3)
                {{-- Podium: 2nd · 1st · 3rd on steps --}}
                <div class="mb-8 grid grid-cols-3 items-end gap-2 sm:gap-4">
                    @foreach([1, 0, 2] as $pos)
                        @php
                            $r = $top[$pos];
                            $step = [['h-40 sm:h-48', 'from-amber-300 to-amber-500', '🥇', 'text-amber-900'], ['h-28 sm:h-36', 'from-slate-200 to-slate-400', '🥈', 'text-slate-800'], ['h-20 sm:h-24', 'from-orange-300 to-orange-500', '🥉', 'text-orange-950']][$pos];
                            $url = route('honors.journey', ['student' => $r['enrollment']->student_id, 'term' => $term ?? 'final']);
                        @endphp
                        <a href="{{ $url }}" class="group flex flex-col items-center text-center">
                            <div class="mb-3 px-1">
                                <div class="{{ $pos === 0 ? 'text-5xl sm:text-6xl' : 'text-4xl' }} transition group-hover:-translate-y-1">{{ $step[2] }}</div>
                                <div class="mt-1 font-semibold text-slate-900 group-hover:underline dark:text-white {{ $pos === 0 ? 'sm:text-lg' : '' }}">{{ $r['enrollment']->student->full_name }}</div>
                                @if($scope === 'level')<div class="text-xs text-slate-500">{{ $r['section']->name }}</div>@endif
                                <div class="{{ $pos === 0 ? 'text-3xl sm:text-4xl' : 'text-2xl' }} font-extrabold text-brand-600">{{ $r['average'] }}</div>
                                @if($r['honor'])<span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $honorStyle[$r['honor']] }}">{{ $r['honor'] }}</span>@endif
                            </div>
                            <div class="flex w-full items-start justify-center rounded-t-2xl bg-gradient-to-b pt-3 shadow-inner {{ $step[0] }} {{ $step[1] }}">
                                <span class="text-4xl font-black {{ $step[3] }} opacity-80 sm:text-5xl">{{ $pos + 1 }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="card overflow-x-auto p-0">
                <table class="table">
                    <thead><tr><th class="w-16">Rank</th><th>Student</th>@if($scope === 'level')<th>Section</th>@endif<th class="w-56">Average</th><th>Honor</th></tr></thead>
                    <tbody>
                    @forelse($top as $r)
                        <tr>
                            <td class="font-bold text-slate-500">#{{ $r['rank'] }}</td>
                            <td class="font-medium"><a class="hover:text-brand-600 hover:underline" href="{{ route('honors.journey', ['student' => $r['enrollment']->student_id, 'term' => $term ?? 'final']) }}">{{ $r['enrollment']->student->full_name }}</a></td>
                            @if($scope === 'level')<td>{{ $r['section']->name }}</td>@endif
                            <td><div class="flex items-center gap-3"><span class="w-12 font-semibold">{{ $r['average'] }}</span><span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full {{ $r['honor'] ? 'bg-amber-400' : 'bg-brand-500' }}" style="width: {{ max(0, min(100, ($r['average'] - 70) * 3.4)) }}%"></span></span></div></td>
                            <td>@if($r['honor'])<span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $honorStyle[$r['honor']] }}">{{ $r['honor'] }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-500">No complete grades for {{ strtolower($period) }} yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-500 print:hidden">Click a name to see their journey – every graded item. Only students who passed every subject are listed. {{ $private ? $private.' student(s) with a failing grade are not shown here – this is kept private.' : '' }} The list length (top {{ $n }}) and honor cut-offs are set in Settings.</p>
        </div>

        {{-- BEST IN SUBJECT --}}
        <div x-show="tab === 'subjects'" x-cloak class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($bestSubject as $sub => $winners)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Best in {{ $sub }}</p>
                    @foreach($winners as $w)
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <a href="{{ route('honors.journey', ['student' => $w['enrollment']->student_id, 'term' => $term ?? 'final']) }}" class="font-semibold hover:underline">{{ $w['enrollment']->student->full_name }}@if($scope === 'level')<span class="block text-xs font-normal text-slate-500">{{ $w['section']->name }}</span>@endif</a>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-sm font-bold text-emerald-700">{{ $w['grade'] }}</span>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="card muted sm:col-span-3">No grades for {{ strtolower($period) }} yet.</p>
            @endforelse
        </div>

        {{-- PERFECT ATTENDANCE --}}
        <div x-show="tab === 'attendance'" x-cloak>
            <div class="card">
                <h2 class="h2 mb-1">Perfect attendance</h2>
                <p class="mb-4 text-sm text-slate-500">No absences and no lates at the gate during {{ strtolower($period) }}.</p>
                <ul class="grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($perfect as $p)
                        <li class="flex items-center gap-3 border-b border-slate-100 py-2.5 dark:border-slate-800"><span class="text-xl">🏅</span><span><span class="font-medium">{{ $p['student']->full_name }}</span><span class="block text-xs text-slate-500">{{ $p['section']->name }} · {{ $p['days'] }} days</span></span></li>
                    @empty
                        <li class="text-slate-500">No one with perfect attendance in this period.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- SPECIAL AWARDS --}}
        <div x-show="tab === 'awards'" x-cloak class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-5">
                <div class="card">
                    <h2 class="h2 mb-3">Given awards</h2>
                    @forelse($approved->groupBy('award.name') as $award => $list)
                        <div class="mb-4"><p class="font-semibold text-brand-700 dark:text-brand-300">🏆 {{ $award }}</p>
                            @foreach($list as $n)<p class="mt-1 text-sm"><b>{{ $n->student->full_name }}</b>@if($n->reason) – <span class="text-slate-500">{{ $n->reason }}</span>@endif</p>@endforeach
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No special awards yet for this period.</p>
                    @endforelse
                </div>
                <div class="card">
                    <h2 class="h2 mb-3">{{ $isAdmin ? 'Waiting for your approval' : 'Your suggestions waiting for approval' }}</h2>
                    @forelse($pending as $n)
                        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 dark:border-slate-800">
                            <div class="mr-auto"><p class="font-medium">{{ $n->student->full_name }} – {{ $n->award->name }}</p><p class="text-xs text-slate-500">{{ $n->reason }} @if($n->nominator)· suggested by {{ $n->nominator->name }}@endif</p></div>
                            @if($isAdmin)
                                <form method="post" action="{{ route('honors.nominations.review', $n) }}" class="flex gap-2">@csrf
                                    <button name="action" value="approve" class="btn btn-primary btn-sm">Approve</button><button name="action" value="reject" class="btn btn-sm">Decline</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nothing waiting.</p>
                    @endforelse
                </div>
            </div>
            <aside class="space-y-5">
                <div class="card">
                    <h2 class="font-semibold">{{ $isAdmin ? 'Give an award' : 'Suggest an award' }}</h2>
                    <form method="post" action="{{ route('honors.nominate') }}" class="mt-3 space-y-3">@csrf
                        <select name="award_id" class="input" required>@foreach($awards->where('is_active', true) as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                        <select name="student_id" class="input" required>@foreach($nominees as $st)<option value="{{ $st->id }}">{{ $st->full_name }}</option>@endforeach</select>
                        <input type="hidden" name="term" value="{{ $term }}">
                        <textarea name="reason" rows="2" class="input" placeholder="Why? (optional)"></textarea>
                        <button class="btn btn-primary w-full">{{ $isAdmin ? 'Give award' : 'Send to admin' }}</button>
                    </form>
                </div>
                <div class="card">
                    <h2 class="font-semibold">Awards list</h2>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        @forelse($awards as $a)
                            <li class="flex items-center gap-2 {{ $a->is_active ? '' : 'text-slate-400 line-through' }}"><span class="mr-auto" title="{{ $a->description }}">{{ $a->name }}</span>
                                @if($isAdmin)<form method="post" action="{{ route('honors.awards.toggle', $a) }}">@csrf<button class="text-xs text-brand-600">{{ $a->is_active ? 'Turn off' : 'Turn on' }}</button></form>@endif
                            </li>
                        @empty
                            <li class="text-slate-500">No awards set up yet.</li>
                        @endforelse
                    </ul>
                    @if($isAdmin)
                        <form method="post" action="{{ route('honors.awards.store') }}" class="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800">@csrf
                            <input name="name" class="input" placeholder="New award, e.g. Best in Science Fair" required>
                            <input name="description" class="input" placeholder="Short description (optional)">
                            <button class="btn btn-sm w-full">Add award</button>
                        </form>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.app>
