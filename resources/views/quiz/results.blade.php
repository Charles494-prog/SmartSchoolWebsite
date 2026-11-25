<x-layouts.app :title="'Results · '.$a->title">
    <a href="{{ route('quiz.edit', $a) }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />Back to quiz builder</a>
    @php
        $best = $attempts->map(fn ($list) => $list->sortByDesc('score')->first());
        $scores = $best->whereNotNull('score')->pluck('score');
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    @endphp
    <div class="mt-3 mb-5 flex flex-wrap items-end gap-3">
        <div class="mr-auto"><h1 class="h1">{{ $a->title }} – results</h1><p class="muted">{{ $a->classSubject->subject->name }} · {{ $a->classSubject->section->label() }}</p></div>
    </div>
    <div class="mb-5 grid gap-3 sm:grid-cols-4">
        <div class="card"><div class="text-3xl font-bold">{{ $best->count() }}/{{ $students->count() }}</div><div class="text-sm text-slate-500">took the quiz</div></div>
        <div class="card"><div class="text-3xl font-bold">{{ $scores->count() ? $fmt($scores->avg()) : '—' }}</div><div class="text-sm text-slate-500">average of {{ $fmt($a->max_score) }}</div></div>
        <div class="card"><div class="text-3xl font-bold">{{ $scores->count() ? $fmt($scores->max()) : '—' }}</div><div class="text-sm text-slate-500">highest</div></div>
        <div class="card"><div class="text-3xl font-bold text-amber-600">{{ $best->where('needs_review', true)->count() }}</div><div class="text-sm text-slate-500">need checking (essays)</div></div>
    </div>

    {{-- Per question: how many got it right --}}
    @if($best->isNotEmpty())
        <div class="card mb-5">
            <h2 class="h2 mb-3">Question by question</h2>
            <div class="space-y-2">
                @foreach($questions as $q)
                    @php $marks = $best->map(fn ($t) => $t->marks[$q->id] ?? null)->filter(fn ($m) => $m !== null); $pct = $marks->count() && $q->points > 0 ? round($marks->avg() / $q->points * 100) : null; @endphp
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-6 font-bold text-slate-400">{{ $loop->iteration }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $q->text }}</span>
                        <span class="h-2 w-40 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full {{ $pct === null ? '' : ($pct >= 75 ? 'bg-emerald-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-rose-500')) }}" style="width: {{ $pct ?? 0 }}%"></span></span>
                        <span class="w-12 text-right font-semibold">{{ $pct === null ? '—' : $pct.'%' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-3">
        @foreach($students as $s)
            @php $list = $attempts[$s->id] ?? collect(); $t = $list->sortByDesc('score')->first(); @endphp
            <details class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" @if($t?->needs_review) open @endif>
                <summary class="flex cursor-pointer items-center gap-3 p-4">
                    <span class="mr-auto font-medium">{{ $s->full_name }}</span>
                    @if(! $t)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500">Not taken</span>
                    @elseif($t->needs_review)<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Check essay</span>
                    @else<span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">{{ $fmt($t->score) }} / {{ $fmt($a->max_score) }}</span>@endif
                    @if($list->count() > 1)<span class="text-xs text-slate-500">{{ $list->count() }} attempts (best counts)</span>@endif
                </summary>
                @if($t)
                    <form method="post" action="{{ route('quiz.review', $t) }}" class="space-y-2 border-t border-slate-100 p-4 dark:border-slate-800">@csrf
                        @foreach($questions as $q)
                            @php $m = $t->marks[$q->id] ?? null; @endphp
                            <div class="flex flex-wrap items-start gap-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-white/5">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ $loop->iteration }}. {{ $q->text }}</p>
                                    <p class="mt-1 whitespace-pre-line text-slate-600 dark:text-slate-300">{{ \App\Support\QuizView::answerText($q, $t->answers[$q->id] ?? null) }}</p>
                                    @if($q->type !== 'essay')<p class="text-xs text-emerald-700">Correct: {{ \App\Support\QuizView::correctText($q) }}</p>@endif
                                </div>
                                <label class="flex items-center gap-1 text-xs">Points <input name="marks[{{ $q->id }}]" value="{{ $m }}" type="number" step="0.25" min="0" max="{{ $q->points }}" class="input h-9 w-20 {{ $m === null ? 'border-amber-400' : '' }}"> / {{ $q->points + 0 }}</label>
                            </div>
                        @endforeach
                        <button class="btn btn-primary btn-sm">Save points</button>
                    </form>
                @endif
            </details>
        @endforeach
    </div>
</x-layouts.app>
