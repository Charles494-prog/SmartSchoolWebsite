@php
    $label = ['submitted' => ['Submitted', 'bg-sky-50 text-sky-800 ring-sky-200'], 'approved' => ['Approved', 'bg-emerald-50 text-emerald-800 ring-emerald-200'], 'returned' => ['Returned', 'bg-rose-50 text-rose-800 ring-rose-200'],
              'in_progress' => ['In progress', 'bg-amber-50 text-amber-900 ring-amber-200'], 'not_started' => ['Not started', 'bg-slate-100 text-slate-700 ring-slate-200']];
    $period = \App\Support\School::periodName($maxTerms);
@endphp
<x-layouts.app title="Grade approvals">
    <x-page-header title="Grade approvals" subtitle="Teachers submit each {{ strtolower($period) }}'s grades. Review and approve them, or return them with a comment.">
        <label class="sr-only" for="term-pick">{{ $period }}</label>
        <select id="term-pick" x-data class="input min-h-11 w-auto font-medium" x-on:change="location='?tab={{ $tab }}&term='+$el.value">
            @for($t = 1; $t <= $maxTerms; $t++)<option value="{{ $t }}" @selected($t === $term)>{{ $period }} {{ $t }}{{ $t === $current ? ' (current)' : '' }}</option>@endfor
        </select>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([['submitted', 'Awaiting approval', 'clock'], ['returned', 'Returned', 'repeat'], ['in_progress', 'In progress', 'clipboard'], ['approved', 'Approved', 'check-circle']] as [$k, $t, $i])
            <a href="{{ route('approvals.index', ['term' => $term, 'tab' => in_array($k, ['submitted', 'returned']) ? $k : 'all']) }}" class="card flex min-h-20 items-center gap-3 hover:ring-2 hover:ring-brand-500">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300"><x-icon :name="$i" class="h-5 w-5" /></span>
                <span><span class="block text-2xl font-bold tabular-nums">{{ $counts[$k] ?? 0 }}</span><span class="text-sm text-slate-600 dark:text-slate-400">{{ $t }}</span></span>
            </a>
        @endforeach
    </div>

    <nav class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm font-medium dark:bg-white/5" aria-label="Show">
        @foreach(\App\Http\Controllers\GradeApprovalController::TABS as $k => $t)
            <a href="{{ route('approvals.index', ['term' => $term, 'tab' => $k]) }}" @if($tab === $k) aria-current="page" @endif class="flex min-h-10 items-center rounded-lg px-3.5 {{ $tab === $k ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white' : 'text-slate-600 dark:text-slate-300' }}">{{ $t }}@if($k !== 'all')<span class="ml-1.5 text-xs opacity-70">{{ $counts[$k] ?? 0 }}</span>@endif</a>
        @endforeach
    </nav>

    @if($sections->isEmpty())
        <div class="card"><x-empty icon="check-circle" :title="$tab === 'submitted' ? 'Nothing waiting for approval' : 'Nothing here'" :text="$tab === 'submitted' ? 'When teachers submit '.$period.' '.$term.' grades, they show up here.' : 'Try another tab or '.strtolower($period).'.'" /></div>
    @else
        <form method="post" action="{{ route('approvals.bulk') }}" x-data="{ ids: [], busy: false }" x-on:submit="if (!confirm('Approve ' + ids.length + ' classes?')) { $event.preventDefault(); return } busy = true">@csrf
            <div class="space-y-3">
                @foreach($sections as $sec)
                    @php $pct = $sec['total'] ? round($sec['approved'] / $sec['total'] * 100) : 0; @endphp
                    <details class="group overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" @if($tab === 'submitted' || $sections->count() === 1) open @endif>
                        <summary class="flex min-h-14 cursor-pointer list-none flex-wrap items-center gap-3 px-5 py-3">
                            <x-icon name="chevron" class="h-4 w-4 text-slate-400 transition group-open:rotate-90" />
                            <span class="min-w-0 flex-1 font-semibold break-words">{{ $sec['section']->label() }}</span>
                            <span class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <span class="h-2 w-28 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><span class="block h-full bg-emerald-600" style="width: {{ $pct }}%"></span></span>
                                {{ $sec['approved'] }}/{{ $sec['total'] }} subjects approved</span>
                        </summary>
                        <ul class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                            @foreach($sec['rows'] as $r)
                                @php $c = $r['class']; [$l, $cls] = $label[$r['state']]; @endphp
                                <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                                    @if($r['state'] === 'submitted')
                                        <input type="checkbox" name="ids[]" value="{{ $r['submission']->id }}" x-model="ids" class="h-5 w-5 accent-brand-600" aria-label="Select {{ $c->subject->name }}">
                                    @else<span class="w-5"></span>@endif
                                    <div class="min-w-0 flex-1 basis-48"><p class="font-medium break-words">{{ $c->subject->name }}</p><p class="text-sm text-slate-600 dark:text-slate-400">{{ $c->teacher?->name ?? 'No teacher yet' }}</p>
                                        @if($r['state'] === 'returned' && $r['submission']->remarks)<p class="text-xs text-rose-800 dark:text-rose-300">“{{ $r['submission']->remarks }}”</p>@endif</div>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $cls }}">{{ $l }}</span>
                                    @if($r['state'] === 'submitted')
                                        <a href="{{ route('approvals.show', $r['submission']) }}" class="btn btn-primary min-h-11">Review</a>
                                    @else
                                        <a href="{{ route('gradebook.show', [$c, 'term' => $term]) }}" class="btn min-h-11">Open</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
            <div class="sticky bottom-0 mt-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/90 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90" x-show="ids.length" x-cloak>
                <span class="text-sm" x-text="ids.length + ' selected'"></span>
                <button class="btn btn-primary min-h-11" :disabled="busy" x-text="busy ? 'Approving…' : 'Approve selected'">Approve selected</button>
            </div>
        </form>
    @endif
</x-layouts.app>
