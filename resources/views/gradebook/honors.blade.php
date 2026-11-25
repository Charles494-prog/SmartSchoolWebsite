<x-layouts.app title="Top achievers & awards">
    @php
        $S = \App\Models\Setting::class;
        $isAdmin = auth()->user()->hasRole('admin', 'registrar');
        $period = $term ? \App\Support\School::periodName($terms).' '.$term : 'Whole school year (so far)';
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
            <label><span class="mb-1 block text-xs font-medium text-slate-500">{{ $isAdmin ? 'Section' : 'My advisory class' }}</span>
                <select name="section_id" class="input h-11" x-on:change="$refs.f.submit()">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected($section?->id == $k)>{{ $v }}</option>@endforeach</select></label>
            <label><span class="mb-1 block text-xs font-medium text-slate-500">Show</span>
                <select name="scope" class="input h-11" x-on:change="$refs.f.submit()"><option value="section" @selected($scope === 'section')>{{ $isAdmin ? 'This section' : 'My advisory' }}</option><option value="level" @selected($scope === 'level')>Whole grade level</option></select></label>
            <label><span class="mb-1 block text-xs font-medium text-slate-500">Period</span>
                <select name="term" class="input h-11" x-on:change="$refs.f.submit()">
                    @foreach(range(1, $terms) as $t)<option value="{{ $t }}" @selected($term === $t)>{{ \App\Support\School::periodName($terms) }} {{ $t }}</option>@endforeach
                    <option value="final" @selected($term === null)>Whole year – live average</option>
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
                            $step = [['h-40 sm:h-48', 'from-amber-300 to-amber-500', $scope === 'level' ? '🏆' : '🥇', 'text-amber-900'], ['h-28 sm:h-36', 'from-slate-200 to-slate-400', '🥈', 'text-slate-800'], ['h-20 sm:h-24', 'from-orange-300 to-orange-500', '🥉', 'text-orange-950']][$pos];
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
        <div x-show="tab === 'subjects'" x-cloak>
            @php $grads = ['from-indigo-500 to-violet-600', 'from-emerald-500 to-teal-600', 'from-sky-500 to-blue-600', 'from-amber-400 to-orange-500', 'from-rose-500 to-pink-600', 'from-fuchsia-500 to-purple-600', 'from-teal-500 to-cyan-600', 'from-lime-500 to-green-600']; @endphp
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($bestSubject as $sub => $list)
                    @php $win = $list->where('place', 1); $rest = $list->where('place', '>', 1); @endphp
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-2 bg-gradient-to-r px-5 py-3 text-white {{ $grads[crc32($sub) % count($grads)] }}">
                            <span class="text-lg">{{ $scope === 'level' ? '🏆' : '⭐' }}</span>
                            <span class="font-semibold">Best in {{ $sub }}</span>
                        </div>
                        <div class="p-5">
                            @foreach($win as $w)
                                <a href="{{ route('honors.journey', ['student' => $w['enrollment']->student_id, 'term' => $term ?? 'final']) }}" class="group flex items-center gap-3 {{ ! $loop->first ? 'mt-3' : '' }}">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-800 ring-2 ring-amber-300">{{ strtoupper(substr($w['enrollment']->student->first_name, 0, 1).substr($w['enrollment']->student->last_name, 0, 1)) }}</span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-lg font-bold group-hover:underline">{{ $w['enrollment']->student->full_name }}</span>@if($scope === 'level')<span class="text-xs text-slate-500">{{ $w['section']->name }}</span>@endif</span>
                                    <span class="text-3xl font-extrabold text-brand-600">{{ $w['grade'] }}</span>
                                </a>
                            @endforeach
                            @if($rest->isNotEmpty())
                                <div class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 dark:border-slate-800">
                                    @foreach($rest as $r)
                                        <a href="{{ route('honors.journey', ['student' => $r['enrollment']->student_id, 'term' => $term ?? 'final']) }}" class="flex items-center gap-2 text-sm hover:underline">
                                            <span>{{ $r['place'] === 2 ? '🥈' : '🥉' }}</span><span class="min-w-0 flex-1 truncate">{{ $r['enrollment']->student->full_name }}</span><span class="font-semibold text-slate-600 dark:text-slate-300">{{ $r['grade'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="card muted sm:col-span-3">No grades for {{ strtolower($period) }} yet.</p>
                @endforelse
            </div>
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
        <div x-show="tab === 'awards'" x-cloak x-data="{ give: null }">
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <p class="mr-auto text-sm text-slate-500">{{ $section?->label() }} · {{ $period }}. {{ $isAdmin ? 'Give an award, or approve what advisers suggest.' : 'Suggest an award for a student in your advisory class – the admin approves it.' }}</p>
            </div>

            @if($pending->isNotEmpty())
                <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="mb-2 font-semibold text-amber-900 dark:text-amber-200">{{ $isAdmin ? 'Waiting for your approval' : 'Your suggestions waiting for approval' }} ({{ $pending->count() }})</p>
                    @foreach($pending as $n)
                        <div class="flex flex-wrap items-center gap-3 border-t border-amber-200/70 py-2 text-sm dark:border-amber-500/20">
                            <span class="mr-auto"><b>{{ $n->student->full_name }}</b> – {{ $n->award->name }}@if($n->reason) <span class="text-amber-800/80 dark:text-amber-200/70">· “{{ $n->reason }}”</span>@endif @if($isAdmin && $n->nominator)<span class="text-xs text-amber-800/70">· by {{ $n->nominator->name }}</span>@endif</span>
                            @if($isAdmin)
                                <form method="post" action="{{ route('honors.nominations.review', $n) }}" class="flex gap-2">@csrf
                                    <button name="action" value="approve" class="btn btn-primary btn-sm">Approve</button><button name="action" value="reject" class="btn btn-sm">Decline</button>
                                </form>
                            @else
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-amber-800">Pending</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($awards as $a)
                    @php $winners = $approved->where('award_id', $a->id); @endphp
                    <div class="flex flex-col rounded-2xl border bg-white shadow-sm dark:bg-slate-900 {{ $a->is_active ? 'border-slate-200 dark:border-slate-800' : 'border-dashed border-slate-300 opacity-60 dark:border-slate-700' }}">
                        <div class="flex items-start gap-3 p-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-300 to-amber-500 text-2xl shadow-sm">🏆</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ $a->name }}</p>
                                @if($a->description)<p class="text-xs text-slate-500">{{ $a->description }}</p>@endif
                            </div>
                            @if($isAdmin)<form method="post" action="{{ route('honors.awards.toggle', $a) }}">@csrf<button class="text-xs text-slate-400 hover:text-brand-600">{{ $a->is_active ? 'Turn off' : 'Turn on' }}</button></form>@endif
                        </div>
                        <div class="flex-1 px-5">
                            @forelse($winners as $w)
                                <div class="mb-2 flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm dark:bg-amber-500/10">
                                    <span>🎖️</span><span class="min-w-0 flex-1"><b class="block truncate">{{ $w->student->full_name }}</b>@if($w->reason)<span class="block truncate text-xs text-slate-500">{{ $w->reason }}</span>@endif</span>
                                </div>
                            @empty
                                <p class="mb-2 text-sm text-slate-400">No one yet this period.</p>
                            @endforelse
                        </div>
                        @if($a->is_active && $nominees->isNotEmpty())
                            <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                                <button type="button" x-show="give !== {{ $a->id }}" class="w-full rounded-xl border border-brand-200 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-500/30 dark:text-brand-300" x-on:click="give = {{ $a->id }}">{{ $isAdmin ? '+ Give this award' : '+ Suggest a student' }}</button>
                                <form x-show="give === {{ $a->id }}" x-cloak method="post" action="{{ route('honors.nominate') }}" class="space-y-2">@csrf
                                    <input type="hidden" name="award_id" value="{{ $a->id }}"><input type="hidden" name="term" value="{{ $term }}">
                                    <select name="student_id" class="input" required>@foreach($nominees as $st)<option value="{{ $st->id }}">{{ $st->full_name }}</option>@endforeach</select>
                                    <input name="reason" class="input" placeholder="Why? (optional)">
                                    <div class="flex gap-2"><button class="btn btn-primary btn-sm flex-1">{{ $isAdmin ? 'Give award' : 'Send to admin' }}</button><button type="button" class="btn btn-sm" x-on:click="give = null">Cancel</button></div>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- Add a custom award (advisers and admin) --}}
                <form method="post" action="{{ route('honors.awards.store') }}" class="flex flex-col justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 p-5 dark:border-slate-700">@csrf
                    <p class="font-semibold">+ New award</p>
                    <p class="text-xs text-slate-500">e.g. Best in Science Fair, Most Improved, Kindness Award</p>
                    <input name="name" class="input" placeholder="Award name" required>
                    <input name="description" class="input" placeholder="Short description (optional)">
                    <button class="btn btn-sm">Add to the award list</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
