<x-layouts.app title="Honor roll & ranking">
    @php
        $S = \App\Models\Setting::class;
        $period = $term ? \App\Support\School::periodName($terms).' '.$term : 'Whole school year';
        $ranked = collect($ranking)->whereNotNull('average')->values();
        $incomplete = collect($ranking)->whereNull('average');
        $honorStyle = ['With Highest Honors' => 'bg-amber-100 text-amber-800 ring-amber-300', 'With High Honors' => 'bg-slate-100 text-slate-700 ring-slate-300', 'With Honors' => 'bg-orange-100 text-orange-800 ring-orange-300'];
        $rank = 0; $prev = null;
        foreach ($ranked as $i => $r) { if ($r['average'] !== $prev) { $rank = $i + 1; $prev = $r['average']; } $ranked[$i] = $r + ['rank' => $rank]; }
        $counts = $ranked->groupBy('honor')->map->count();
    @endphp

    <div class="mb-6 flex flex-wrap items-end gap-3 print:hidden">
        <div class="mr-auto">
            <h1 class="h1">Honor roll</h1>
            <p class="muted">{{ $section?->label() }} · {{ $period }}</p>
        </div>
        <form class="flex flex-wrap gap-2">
            <select name="section_id" class="input h-11" onchange="this.form.submit()">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected($section?->id == $k)>{{ $v }}</option>@endforeach</select>
            <select name="term" class="input h-11" onchange="this.form.submit()">
                @foreach(range(1, $terms) as $t)<option value="{{ $t }}" @selected($term === $t)>{{ \App\Support\School::periodName($terms) }} {{ $t }}</option>@endforeach
                <option value="final" @selected($term === null)>Whole year (final)</option>
            </select>
        </form>
        <button type="button" onclick="window.print()" class="btn h-11">Print</button>
    </div>
    <h1 class="mb-4 hidden text-2xl font-bold print:block">Honor roll – {{ $section?->label() }} · {{ $period }}</h1>

    {{-- Summary --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        @foreach(['With Highest Honors' => $S::get('honors_with_highest'), 'With High Honors' => $S::get('honors_with_high'), 'With Honors' => $S::get('honors_with')] as $h => $min)
            <div class="rounded-2xl p-4 ring-1 {{ $honorStyle[$h] }}">
                <div class="text-3xl font-bold">{{ $counts[$h] ?? 0 }}</div>
                <div class="text-sm font-semibold">{{ $h }}</div>
                <div class="text-xs opacity-75">average {{ $min }} and up</div>
            </div>
        @endforeach
        <div class="rounded-2xl bg-white p-4 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="text-3xl font-bold">{{ $ranked->count() }}</div>
            <div class="text-sm font-semibold">Ranked students</div>
            <div class="text-xs text-slate-500">{{ $incomplete->count() }} with incomplete grades</div>
        </div>
    </div>

    {{-- Top 3 --}}
    @if($ranked->count() >= 3)
        <div class="mb-6 grid items-end gap-3 sm:grid-cols-3">
            @foreach([1, 0, 2] as $pos)
                @php $r = $ranked[$pos]; $medal = ['🥇', '🥈', '🥉'][$pos]; @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900 {{ $pos === 0 ? 'sm:pb-10 sm:pt-8 ring-2 ring-amber-300' : '' }}">
                    <div class="text-4xl">{{ $medal }}</div>
                    <div class="mt-2 font-semibold text-slate-900 dark:text-white">{{ $r['enrollment']->student->full_name }}</div>
                    <div class="text-3xl font-bold text-brand-600">{{ $r['average'] }}</div>
                    @if($r['honor'])<span class="mt-2 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $honorStyle[$r['honor']] }}">{{ $r['honor'] }}</span>@endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Full ranking --}}
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th class="w-16">Rank</th><th>Student</th><th class="w-56">Average</th><th>Honor</th></tr></thead>
            <tbody>
            @forelse($ranked as $r)
                <tr>
                    <td class="font-bold text-slate-500">#{{ $r['rank'] }}</td>
                    <td class="font-medium">{{ $r['enrollment']->student->full_name }}</td>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="w-12 font-semibold">{{ $r['average'] }}</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full {{ $r['honor'] ? 'bg-amber-400' : 'bg-brand-500' }}" style="width: {{ max(0, min(100, ($r['average'] - 60) * 2.5)) }}%"></span></span>
                        </div>
                    </td>
                    <td>@if($r['honor'])<span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $honorStyle[$r['honor']] }}">{{ $r['honor'] }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-10 text-center text-slate-500">No complete grades for {{ strtolower($period) }} yet.</td></tr>
            @endforelse
            @foreach($incomplete as $r)
                <tr class="text-slate-400"><td>—</td><td>{{ $r['enrollment']->student->full_name }}</td><td colspan="2">Incomplete grades</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500 print:hidden">Honor cut-offs are set in Settings. A student is ranked only when every subject has a grade for the period.</p>
</x-layouts.app>
