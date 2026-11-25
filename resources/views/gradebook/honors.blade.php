<x-layouts.app title="Top achievers & awards">
    @php
        $S = \App\Models\Setting::class;
        $period = $term ? \App\Support\School::periodName($terms).' '.$term : 'Whole school year (so far)';
        $levelName = $levels->firstWhere('id', $levelId)?->name ?? $section?->gradeLevel->name;
        $where = match ($scope) { 'school' => 'Whole school', 'level' => $levelName.' – all sections', default => $section?->label() };
        $up = $scope === 'section' ? 'the entire '.$section?->gradeLevel->name : 'the whole school';
        $honorStyle = ['With Highest Honors' => 'bg-amber-100 text-amber-800 ring-amber-300', 'With High Honors' => 'bg-slate-100 text-slate-700 ring-slate-300', 'With Honors' => 'bg-orange-100 text-orange-800 ring-orange-300'];
        $counts = $top->groupBy('honor')->map->count();
        // "Play awardees": from the least intense award to the top 1.
        $slides = [];
        $intro = fn ($title, $sub) => ['intro' => true, 'title' => $title, 'sub' => $sub];
        $grand = $scope === 'school';
        $slides[] = ['cover' => true, 'grand' => $grand, 'title' => ($grand ? 'Overall' : ($scope === 'level' ? $levelName : $section?->label())).' Awarding Ceremony', 'sub' => $period, 'school' => (string) \App\Models\Setting::get('school_name')];
        if ($grand) {
            // Superadmin's overall show: each grade level's top 3, then the school's top N with a crown for #1.
            foreach ($byLevel as $lv) {
                if ($lv['top']->isEmpty()) { continue; }
                $slides[] = $intro($lv['level']->name, 'Top 3 of the grade level');
                foreach ($lv['top']->reverse()->values() as $r) {
                    $rk = (int) $r['rank'];
                    $slides[] = ['icon' => [1 => '🏆', 2 => '🥈', 3 => '🥉'][$rk] ?? '🎖️', 'kind' => $lv['level']->name.' · Rank #'.$rk, 'name' => $r['enrollment']->student->full_name, 'detail' => 'Average '.$r['average'].($r['honor'] ? ' · '.$r['honor'] : ''), 'section' => $r['section']->name, 'tier' => $rk];
                }
            }
            $perfect = collect(); $approved = collect(); $bestSubject = collect();   // the overall show focuses on the top achievers
        }
        if ($perfect->isNotEmpty()) {
            $slides[] = $intro('Perfect attendance', 'Never absent, never late');
            foreach ($perfect as $p) { $slides[] = ['icon' => '🏅', 'kind' => 'Perfect attendance', 'name' => $p['student']->full_name, 'detail' => $p['days'].' school days', 'section' => $p['section']->name]; }
        }
        if ($approved->isNotEmpty()) {
            $slides[] = $intro('Special awards', 'For character, talent and service');
            foreach ($approved as $a) { $slides[] = ['icon' => '🏆', 'kind' => $a->award->name, 'name' => $a->student->full_name, 'detail' => (string) $a->reason, 'section' => '']; }
        }
        if ($bestSubject->isNotEmpty()) {
            $slides[] = $intro('Best in each subject', 'The highest grade in every subject');
            foreach ($bestSubject as $sub => $list) {
                foreach ($list->where('place', 1) as $w) {
                    $sid = $w['enrollment']->student_id;
                    $lp = $scope !== 'school' ? ($levelSubject[$sub][$sid] ?? null) : null;
                    $also = $lp ? ['Also '.($lp === 1 ? '#1' : 'Top '.$lp).' in '.$sub.' in '.$up] : [];
                    $slides[] = ['icon' => $scope === 'level' ? '🏆' : '⭐', 'kind' => 'Best in '.$sub.($scope === 'section' ? ' in the section' : ''), 'name' => $w['enrollment']->student->full_name, 'detail' => 'Grade '.$w['grade'], 'section' => $scope === 'level' ? $w['section']->name : '', 'also' => $also];
                }
            }
        }
        if ($top->isNotEmpty()) {
            $slides[] = $intro($grand ? 'Overall Top '.$n : 'Top '.$n.' achievers', $grand ? 'The best of the whole school · '.$period : $period);
            foreach ($top->reverse()->values() as $r) {
                $rank = (int) $r['rank'];
                $sid = $r['enrollment']->student_id;
                $lr = $scope !== 'school' ? ($levelRank[$sid] ?? null) : null;
                $also = $lr ? [($lr === 1 ? 'Also #1 in ' : 'Also Top '.$lr.' in ').$up.'!'] : [];
                if ($rank === 1) {
                    $slides[] = ['countdown' => true, 'title' => $grand ? 'And the Top 1 of the whole school is…' : 'And the Top 1 is…'];
                }
                $icon = match ($rank) { 1 => $grand ? '👑' : ($scope === 'level' ? '🏆' : '🥇'), 2 => '🥈', 3 => '🥉', default => '🎖️' };
                $slides[] = ['grand' => $grand && $rank === 1, 'icon' => $icon, 'kind' => 'Rank #'.$rank, 'name' => $r['enrollment']->student->full_name, 'detail' => 'Average '.$r['average'].($r['honor'] ? ' · '.$r['honor'] : ''), 'section' => $scope !== 'section' ? $r['section']->gradeLevel->name.' – '.$r['section']->name : '', 'tier' => $rank <= 3 ? $rank : 0, 'also' => $also];
            }
        }
    @endphp

    <div x-data="{ tab: @js(request('tab', 'top')) }">
        {{-- Filters --}}
        <form class="mb-6 flex flex-wrap items-end gap-3 print:hidden" x-ref="f">
            <div class="mr-auto">
                <h1 class="h1">Top achievers &amp; awards</h1>
                <p class="muted">{{ $where }} · {{ $period }}</p>
            </div>
            <input type="hidden" name="tab" :value="tab">
            <input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="level_id" value="{{ $levelId }}">
            @if($isAdmin && $scope === 'section')
                <label><span class="mb-1 block text-xs font-medium text-slate-500">Section</span>
                    <select name="section_id" class="input h-11" x-on:change="$refs.f.submit()">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected($section?->id == $k)>{{ $v }}</option>@endforeach</select></label>
            @endif
            <label><span class="mb-1 block text-xs font-medium text-slate-500">Period</span>
                <select name="term" class="input h-11" x-on:change="$refs.f.submit()">
                    @foreach(range(1, $terms) as $t)<option value="{{ $t }}" @selected($term === $t)>{{ \App\Support\School::periodName($terms) }} {{ $t }}</option>@endforeach
                    <option value="final" @selected($term === null)>Whole year – live average</option>
                </select></label>
            <button type="button" x-on:click="window.print()" class="btn h-11">Print</button>
            @if($slides && $canPlay)<button type="button" class="btn btn-primary h-11" x-on:click="$dispatch('play-awardees')">▶ Play awardees</button>@endif
        </form>
        @php $q = fn ($extra) => route('honors', array_merge(['tab' => request('tab', 'top'), 'term' => $term ?? 'final'], $extra)); @endphp
        <div class="-mt-2 mb-5 flex flex-wrap gap-2 print:hidden">
            @if($isAdmin)
                @foreach($levels as $lv)
                    <a href="{{ $q(['scope' => 'level', 'level_id' => $lv->id]) }}" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $scope === 'level' && $levelId == $lv->id ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 hover:border-slate-400 dark:border-slate-700 dark:text-slate-300' }}">{{ $lv->name }}</a>
                @endforeach
                <a href="{{ $q(['scope' => 'school']) }}" class="rounded-full border px-3 py-1.5 text-sm font-semibold {{ $scope === 'school' ? 'border-amber-500 bg-amber-500 text-white' : 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:text-amber-300' }}">👑 Overall</a>
                <a href="{{ $q(['scope' => 'section', 'level_id' => $levelId]) }}" class="ml-auto rounded-full border px-3 py-1.5 text-sm {{ $scope === 'section' ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300' }}">One section…</a>
            @elseif($section)
                <a href="{{ $q(['scope' => 'section']) }}" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $scope === 'section' ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300' }}">My advisory · {{ $section->name }}</a>
                <a href="{{ $q(['scope' => 'level']) }}" class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $scope === 'level' ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300' }}">{{ $section->gradeLevel->name }} – all sections</a>
            @endif
        </div>
        <h1 class="mb-4 hidden text-2xl font-bold print:block">Top achievers – {{ $where }} · {{ $period }}</h1>

        {{-- Tabs --}}
        <div class="mb-5 flex flex-wrap gap-1 border-b border-slate-200 print:hidden dark:border-slate-800">
            @foreach(['top' => 'Top '.$n, 'subjects' => 'Best in subject', 'attendance' => 'Perfect attendance', 'awards' => 'Special awards'.($pending->count() ? ' ('.$pending->count().')' : '')] as $k => $label)
                <button type="button" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold" :class="tab === '{{ $k }}' ? 'border-brand-600 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-500 hover:text-slate-800'" x-on:click="tab = '{{ $k }}'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- TOP N --}}
        <div x-show="tab === 'top'">
            @if($canSetN && $scope === 'section')
                <form method="post" action="{{ route('honors.top', $section) }}" class="mb-4 flex flex-wrap items-center gap-2 text-sm print:hidden" x-data>@csrf
                    <span class="text-slate-500">List length for {{ $section->name }}:</span>
                    <select name="n" class="input h-9 w-auto py-0" x-on:change="$el.form.submit()">
                        <option value="">School setting (Top {{ $levelN }})</option>
                        @foreach([3, 5, 10, 15, 20, 30] as $opt)<option value="{{ $opt }}" @selected((int) \App\Models\Setting::get('honors_top_n_section_'.$section->id) === $opt)>Top {{ $opt }}</option>@endforeach
                    </select>
                </form>
            @endif
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
                            $step = [['h-40 sm:h-48', 'border-amber-400 bg-amber-50 dark:bg-amber-500/10', $scope === 'school' ? '👑' : ($scope === 'level' ? '🏆' : '🥇'), 'text-amber-600'], ['h-28 sm:h-36', 'border-slate-400 bg-slate-100 dark:bg-slate-800', '🥈', 'text-slate-500'], ['h-20 sm:h-24', 'border-orange-400 bg-orange-50 dark:bg-orange-500/10', '🥉', 'text-orange-600']][$pos];
                            $url = route('honors.journey', ['student' => $r['enrollment']->student_id, 'term' => $term ?? 'final']);
                        @endphp
                        <a href="{{ $url }}" class="group flex flex-col items-center text-center">
                            <div class="mb-3 px-1">
                                <div class="{{ $pos === 0 ? 'text-5xl sm:text-6xl' : 'text-4xl' }} transition group-hover:-translate-y-1">{{ $step[2] }}</div>
                                <div class="mt-1 font-semibold text-slate-900 group-hover:underline dark:text-white {{ $pos === 0 ? 'sm:text-lg' : '' }}">{{ $r['enrollment']->student->full_name }}</div>
                                @if($scope === 'level')<div class="text-xs text-slate-500">{{ $r['section']->name }}</div>@endif
                                <div class="{{ $pos === 0 ? 'text-3xl sm:text-4xl' : 'text-2xl' }} font-bold tabular-nums text-slate-900 dark:text-white">{{ $r['average'] }}</div>
                                @if($r['honor'])<span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $honorStyle[$r['honor']] }}">{{ $r['honor'] }}</span>@endif
                            </div>
                            <div class="flex w-full items-start justify-center rounded-t-xl border-t-4 pt-3 {{ $step[0] }} {{ $step[1] }}">
                                <span class="text-4xl font-bold tabular-nums sm:text-5xl {{ $step[3] }}">{{ $pos + 1 }}</span>
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
                            <td><div class="flex items-center gap-3"><span class="w-12 font-semibold">{{ $r['average'] }}</span><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full {{ $r['honor'] ? 'bg-amber-500' : 'bg-slate-400' }}" style="width: {{ max(0, min(100, ($r['average'] - 70) * 3.4)) }}%"></span></span></div></td>
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
        <div x-show="tab === 'subjects'" x-cloak x-data="{ podium: localStorage.getItem('ss.bestTop3') !== '0' }">
            <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
                <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="!podium ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="podium = false; localStorage.setItem('ss.bestTop3', '0')">1st place only</button>
                <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="podium ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="podium = true; localStorage.setItem('ss.bestTop3', '1')">1st, 2nd and 3rd</button>
            </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($bestSubject as $sub => $list)
                    @php $win = $list->where('place', 1); $rest = $list->where('place', '>', 1); @endphp
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                            <span>{{ $scope === 'level' ? '🏆' : '⭐' }}</span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Best in {{ $sub }}</span>
                        </div>
                        <div class="p-5">
                            @foreach($win as $w)
                                <a href="{{ route('honors.journey', ['student' => $w['enrollment']->student_id, 'term' => $term ?? 'final']) }}" class="group flex items-center gap-3 {{ ! $loop->first ? 'mt-3' : '' }}">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ strtoupper(substr($w['enrollment']->student->first_name, 0, 1).substr($w['enrollment']->student->last_name, 0, 1)) }}</span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-lg font-bold group-hover:underline">{{ $w['enrollment']->student->full_name }}</span>@if($scope === 'level')<span class="text-xs text-slate-500">{{ $w['section']->name }}</span>@endif</span>
                                    <span class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $w['grade'] }}</span>
                                </a>
                            @endforeach
                            @if($rest->isNotEmpty())
                                <div x-show="podium" class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 dark:border-slate-800">
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
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-xl dark:bg-amber-500/10">🏆</span>
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

    {{-- Play awardees: full-screen, one student at a time; builds up to a countdown and the Top 1 reveal --}}
    <div x-data="awardShow(@js($slides))" x-on:play-awardees.window="start()" x-show="on" x-cloak
         x-on:keydown.window="if (on) { if (['ArrowRight', ' ', 'Enter'].includes($event.key)) { $event.preventDefault(); next() } if ($event.key === 'ArrowLeft') prev(); if ($event.key === 'Escape') stop() }"
         class="fixed inset-0 z-[100] flex flex-col overflow-hidden text-white transition-colors duration-1000" :class="bg()" role="dialog" aria-modal="true" aria-label="Awardees">
        <canvas x-ref="confetti" class="pointer-events-none absolute inset-0 h-full w-full"></canvas>
        <div class="relative z-10 flex items-center gap-3 px-6 py-4 text-sm text-white/60">
            <span class="mr-auto">{{ $where }} · {{ $period }}</span>
            <span x-text="(i + 1) + ' / ' + slides.length"></span>
            <button type="button" class="rounded-full bg-white/10 px-3 py-1.5 hover:bg-white/20" x-on:click="auto = !auto; tick()" x-text="auto ? 'Pause' : 'Auto-play'"></button>
            <button type="button" class="rounded-full bg-white/10 px-3 py-1.5 hover:bg-white/20" x-on:click="stop()">Exit</button>
        </div>
        <div class="relative z-10 flex flex-1 items-center justify-center px-6" x-on:click="next()">
            <template x-for="(s, k) in slides" :key="k">
                <div x-show="i === k" x-transition:enter="transition duration-700 ease-out" x-transition:enter-start="opacity-0 translate-y-10 scale-90" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute w-full max-w-5xl text-center">
                    {{-- cover --}}
                    <template x-if="s.cover">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[.35em] text-white/50" x-text="s.school"></p>
                            <div class="mt-6" :class="s.grand ? 'text-8xl' : 'text-7xl'" x-text="s.grand ? '👑' : '🏆'"></div>
                            <h2 class="mt-6 text-5xl font-bold leading-tight sm:text-7xl" x-text="s.title"></h2>
                            <p class="mt-4 text-2xl text-amber-300" x-text="s.sub"></p>
                            <p class="mx-auto mt-14 flex w-fit items-center gap-3 rounded-full border border-white/20 px-5 py-2.5 text-base text-white/70">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                                Plug into HDMI and use a big screen for a better experience
                            </p>
                            <p class="mt-6 text-sm text-white/40">Press Space or click to begin</p>
                        </div>
                    </template>
                    {{-- group title --}}
                    <template x-if="s.intro"><div><p class="text-sm font-semibold uppercase tracking-[.3em] text-amber-400">Next</p><h2 class="mt-3 text-5xl font-bold sm:text-7xl" x-text="s.title"></h2><p class="mt-4 text-xl text-white/60" x-text="s.sub"></p></div></template>
                    {{-- drumroll countdown before #1 --}}
                    <template x-if="s.countdown">
                        <div>
                            <p class="text-3xl font-semibold text-amber-300 sm:text-5xl" x-text="s.title"></p>
                            <div class="relative mx-auto mt-10 flex h-56 w-56 items-center justify-center">
                                <span class="absolute inset-0 animate-ping rounded-full border-4 border-amber-400/60"></span>
                                <span class="absolute inset-6 animate-ping rounded-full border-4 border-amber-300/40 [animation-delay:.3s]"></span>
                                <span class="text-9xl font-black tabular-nums text-white" x-text="count" :key="count"></span>
                            </div>
                        </div>
                    </template>
                    {{-- a student --}}
                    <template x-if="!s.intro && !s.countdown && !s.cover">
                        <div :class="s.tier === 1 && 'reveal-one'">
                            <p x-show="s.grand" class="mb-2 text-sm font-bold uppercase tracking-[.4em] text-yellow-300">Top of the whole school</p>
                            <div class="relative mx-auto flex h-48 w-48 items-center justify-center sm:h-60 sm:w-60">
                                <template x-if="s.tier"><span class="absolute inset-0 animate-ping rounded-full" :class="{ 'border-8 border-amber-400/70': s.tier === 1, 'border-4 border-slate-300/40': s.tier === 2, 'border-4 border-orange-400/40': s.tier === 3 }"></span></template>
                                <span :class="s.grand ? 'text-[9rem] sm:text-[13rem] animate-bounce drop-shadow-[0_0_40px_rgba(250,204,21,.7)]' : s.tier === 1 ? 'text-9xl sm:text-[11rem] animate-bounce' : (s.tier ? 'text-8xl sm:text-9xl' : 'text-7xl sm:text-8xl')" x-text="s.icon"></span>
                            </div>
                            <p class="mt-4 font-semibold uppercase tracking-[.25em]" :class="s.tier === 1 ? 'text-2xl text-amber-300' : 'text-lg text-amber-400'" x-text="s.kind"></p>
                            <h2 class="mt-3 font-bold" :class="s.tier === 1 ? 'text-6xl sm:text-8xl' : 'text-5xl sm:text-7xl'" x-text="s.name"></h2>
                            <p class="mt-4 text-2xl text-white/75" x-text="s.detail"></p>
                            <p class="mt-1 text-lg text-white/40" x-text="s.section"></p>
                            <template x-for="(a, n) in (s.also || [])" :key="n">
                                <p x-show="showAlso" x-transition:enter="transition duration-700 ease-out" x-transition:enter-start="opacity-0 scale-50" x-transition:enter-end="opacity-100 scale-100"
                                   class="mx-auto mt-6 w-fit rounded-full border-2 border-amber-400 bg-amber-400/15 px-6 py-2 text-xl font-bold text-amber-200 sm:text-2xl">✨ <span x-text="a"></span></p>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        <div class="relative z-10 flex justify-center gap-3 pb-8">
            <button type="button" class="rounded-full bg-white/10 px-5 py-2 hover:bg-white/20" x-on:click="prev()">← Back</button>
            <button type="button" class="rounded-full bg-white px-6 py-2 font-semibold text-slate-900 hover:bg-white/90" x-on:click="next()" x-text="i === slides.length - 1 ? 'Finish' : 'Next →'"></button>
        </div>
    </div>
    <style>
        @keyframes reveal-one { 0% { transform: scale(.3) rotate(-8deg); filter: blur(12px); opacity: 0 } 60% { transform: scale(1.12) rotate(2deg); filter: blur(0); opacity: 1 } 80% { transform: scale(.97) } 100% { transform: scale(1) } }
        .reveal-one { animation: reveal-one 1.4s cubic-bezier(.2,.8,.2,1) both }
        @media (prefers-reduced-motion: reduce) { .reveal-one { animation: none } }
    </style>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function awardShow(slides) {
            return {
                slides, i: 0, on: false, auto: false, timer: null, count: 3, showAlso: false, cd: null,
                async start() { this.i = 0; this.on = true; this.enter(); try { await document.documentElement.requestFullscreen?.() } catch (e) {} },
                async stop() { this.on = false; this.auto = false; clearTimeout(this.timer); clearInterval(this.cd); if (document.fullscreenElement) { try { await document.exitFullscreen() } catch (e) {} } },
                next() { if (this.slides[this.i]?.countdown && this.count > 0) return; if (this.i < this.slides.length - 1) { this.i++; this.enter() } else this.stop() },
                prev() { if (this.i > 0) { this.i--; this.enter() } },
                // Background darkens and warms up as we get closer to #1.
                bg() { const s = this.slides[this.i] || {}; return s.countdown ? 'bg-black' : (s.grand ? 'bg-yellow-950' : ({ 1: 'bg-amber-950', 2: 'bg-slate-900', 3: 'bg-orange-950' }[s.tier] || 'bg-slate-950')) },
                enter() {
                    clearInterval(this.cd); this.showAlso = false;
                    const s = this.slides[this.i] || {};
                    if (s.countdown) {
                        this.count = 3;
                        this.cd = setInterval(() => { this.count--; if (this.count <= 0) { clearInterval(this.cd); setTimeout(() => this.next(), 500) } }, 1000);
                        return;
                    }
                    setTimeout(() => this.showAlso = true, s.tier === 1 ? 2200 : 1300);   // the surprise comes after the name
                    if (s.grand && s.tier === 1) { setTimeout(() => this.confetti(12000, 450), 400); setTimeout(() => this.confetti(9000, 300), 3000) }
                    else if (s.tier === 1) setTimeout(() => this.confetti(6000), 600);
                    else if (s.tier) setTimeout(() => this.confetti(1200, 60), 400);
                    this.tick();
                },
                tick() { clearTimeout(this.timer); const s = this.slides[this.i] || {}; if (this.auto && this.on && !s.countdown) this.timer = setTimeout(() => this.next(), s.intro ? 2500 : (s.tier === 1 ? 9000 : (s.also?.length ? 6500 : 4500))) },
                confetti(ms, n = 220) {
                    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    const c = this.$refs.confetti, x = c.getContext('2d'); c.width = innerWidth; c.height = innerHeight;
                    const colors = ['#fbbf24', '#f59e0b', '#ffffff', '#f472b6', '#60a5fa', '#34d399'];
                    const p = Array.from({ length: n }, () => ({ x: Math.random() * c.width, y: -20 - Math.random() * c.height * .5, r: 4 + Math.random() * 6, v: 2 + Math.random() * 4, s: Math.random() * 6 - 3, a: Math.random() * 6, col: colors[Math.floor(Math.random() * colors.length)] }));
                    const end = performance.now() + ms;
                    const draw = (t) => {
                        x.clearRect(0, 0, c.width, c.height);
                        p.forEach((q) => { q.y += q.v; q.x += q.s * .5; q.a += .1; if (q.y > c.height && t < end - 1500) q.y = -10; x.save(); x.translate(q.x, q.y); x.rotate(q.a); x.fillStyle = q.col; x.fillRect(-q.r / 2, -q.r / 4, q.r, q.r / 2); x.restore() });
                        if (t < end && this.on) requestAnimationFrame(draw); else x.clearRect(0, 0, c.width, c.height);
                    };
                    requestAnimationFrame(draw);
                },
            };
        }
    </script>
</x-layouts.app>
