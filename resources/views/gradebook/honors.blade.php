<x-layouts.app title="Honor roll & ranking">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="h1 flex-1">Honor roll & ranking</h1>
        <form><select name="section_id" class="input" onchange="this.form.submit()">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected($section?->id == $k)>{{ $v }}</option>@endforeach</select></form>
        <button onclick="window.print()" class="btn">Print</button>
    </div>
    <p class="mb-4 muted">Ranked by general average (only students with grades in every subject and term). Honors: With Highest Honors ≥ {{ \App\Models\Setting::get('honors_with_highest') }}, With High Honors ≥ {{ \App\Models\Setting::get('honors_with_high') }}, With Honors ≥ {{ \App\Models\Setting::get('honors_with') }} (change in Settings).</p>
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Rank</th><th>Student</th><th>General average</th><th>Honor</th></tr></thead>
            <tbody>
            @php $rank = 0; $prev = null; $i = 0; @endphp
            @forelse($ranking as $r)
                @php $i++; if ($r['average'] !== $prev) { $rank = $i; $prev = $r['average']; } @endphp
                <tr class="{{ $r['honor'] ? 'bg-amber-50' : '' }}">
                    <td>{{ $r['average'] !== null ? $rank : '—' }}</td>
                    <td>{{ $r['enrollment']->student->full_name }}</td>
                    <td class="font-semibold">{{ $r['average'] ?? 'incomplete' }}</td>
                    <td>{{ $r['honor'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-slate-500">No section selected or no students.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
