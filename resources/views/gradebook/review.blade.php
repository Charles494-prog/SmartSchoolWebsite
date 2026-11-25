@php $fmt = fn ($v) => $v === null ? '—' : number_format($v, 1); @endphp
<x-layouts.app title="Review grades">
    <x-page-header :title="$cs->subject->name.' – '.$cs->section->label()" :subtitle="'Term '.$s->term.' · '.($cs->teacher?->name ?? 'No teacher').' · submitted '.$s->updated_at->format('M j, Y')" :back="route('approvals.index', ['term' => $s->term])" back-label="Grade approvals" />

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
        @foreach([['Students', $stats['students']], ['Class average', $fmt($stats['average'])], ['Highest', $fmt($stats['highest'])], ['Lowest', $fmt($stats['lowest'])], ['Below passing', $stats['failing']], ['Missing scores', $stats['missing']]] as [$t, $v])
            <div class="card"><p class="text-2xl font-bold tabular-nums {{ $t === 'Missing scores' && $v > 0 ? 'text-amber-800' : '' }}">{{ $v }}</p><p class="text-sm text-slate-600 dark:text-slate-400">{{ $t }}</p></div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5"><tr><th class="px-4 py-2.5">Student</th><th class="px-3 py-2.5 text-right">Initial</th><th class="px-3 py-2.5 text-right">Grade</th><th class="px-3 py-2.5 text-right">Missing</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($sheet as $id => $r)
                            <tr><td class="px-4 py-2 break-words">{{ $r['student']->full_name }}</td><td class="px-3 py-2 text-right tabular-nums">{{ $fmt($r['initial']) }}</td>
                                <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $r['quarterly'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums {{ ($missingBy[$id] ?? 0) ? 'font-semibold text-amber-800' : 'text-slate-500' }}">{{ $missingBy[$id] ?? 0 }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No students in this section.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="space-y-3">
            @if($s->status === 'submitted')
                <form method="post" action="{{ route('gradebook.review', $s) }}" class="card" x-data="{ busy: false }" x-on:submit="busy = true">@csrf<input type="hidden" name="back" value="queue"><input type="hidden" name="action" value="approve">
                    <h2 class="h2 mb-1">Approve</h2><p class="mb-3 text-sm text-slate-600 dark:text-slate-400">Grades become official and the teacher can no longer edit them.</p>
                    <button class="btn btn-primary min-h-11 w-full" :disabled="busy">Approve grades</button>
                </form>
                <form method="post" action="{{ route('gradebook.review', $s) }}" class="card" x-data="{ busy: false }" x-on:submit="busy = true">@csrf<input type="hidden" name="back" value="queue"><input type="hidden" name="action" value="return">
                    <h2 class="h2 mb-1">Return to teacher</h2>
                    <x-field name="remarks" type="textarea" label="What needs fixing? (required)" required maxlength="500" placeholder="e.g. 3 students are missing the quarterly exam score." />
                    <button class="btn mt-3 min-h-11 w-full text-rose-700" :disabled="busy">Return with comment</button>
                </form>
            @else
                <div class="card"><p class="font-semibold">Already {{ $s->status }}.</p>@if($s->remarks)<p class="mt-1 text-sm">“{{ $s->remarks }}”</p>@endif</div>
            @endif
            <a href="{{ route('gradebook.show', [$cs, 'term' => $s->term]) }}" class="btn min-h-11 w-full">Open the full gradebook</a>
        </div>
    </div>
</x-layouts.app>
