<x-layouts.app :title="$a->title">
    <a href="{{ route('portal.class', $a->class_subject_id) }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />{{ $a->classSubject->subject->name }}</a>
    <div class="mx-auto mt-4 max-w-2xl">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="h-1.5 bg-brand-600"></div>
            <div class="p-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Quiz</p>
                <h1 class="text-2xl font-bold">{{ $a->title }}</h1>
                @if($a->instructions)<p class="mt-2 whitespace-pre-line text-slate-600 dark:text-slate-300">{{ $a->instructions }}</p>@endif
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><div class="text-xs text-slate-500">Questions</div><div class="font-bold">{{ $questions->count() }}</div></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><div class="text-xs text-slate-500">Time</div><div class="font-bold">{{ $a->time_limit ? $a->time_limit.' min' : 'No limit' }}</div></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><div class="text-xs text-slate-500">Attempts</div><div class="font-bold">{{ $attempts->count() }} / {{ $a->attempts_allowed ?: '∞' }}</div></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><div class="text-xs text-slate-500">Due</div><div class="font-bold">{{ $a->due_at?->format('M d, g:i A') ?? '—' }}</div></div>
                </div>
                <p class="mt-3 text-sm text-slate-500">{{ \App\Support\Classwork::countsToward($a) }}</p>
                @if($canRetry)
                    <form method="post" action="{{ route('quiz.start', $a) }}" class="mt-5">@csrf
                        <button class="btn btn-primary w-full min-h-12 text-base">{{ $attempts->isEmpty() ? 'Start quiz' : 'Try again' }}</button>
                        @if($a->time_limit)<p class="mt-2 text-center text-xs text-slate-500">The timer starts when you click. Your answers are turned in automatically when time is up.</p>@endif
                    </form>
                @elseif($questions->isEmpty())
                    <p class="mt-5 rounded-xl bg-slate-50 p-4 text-center text-slate-500">Your teacher hasn't added questions yet.</p>
                @endif
            </div>
        </div>

        @foreach($attempts->whereNotNull('finished_at')->sortByDesc('attempt_no') as $t)
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <div class="mr-auto"><p class="font-semibold">Attempt {{ $t->attempt_no }}</p><p class="text-xs text-slate-500">Turned in {{ $t->finished_at->format('M d, g:i A') }}</p></div>
                    @if($t->needs_review)<span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800">Teacher is checking</span>
                    @elseif($a->show_results !== 'never')<span class="rounded-full bg-emerald-100 px-3 py-1 text-lg font-bold text-emerald-700">{{ rtrim(rtrim(number_format($t->score, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $a->max_score, 2), '0'), '.') }}</span>@endif
                </div>
                @if($showResults)
                    <div class="mt-4 space-y-3">
                        @foreach($questions as $id => $q)
                            @php $ans = $t->answers[$id] ?? null; $m = $t->marks[$id] ?? null; $right = $m !== null && $m >= $q->points; @endphp
                            <div class="rounded-xl border p-3 text-sm {{ $m === null ? 'border-slate-200' : ($right ? 'border-emerald-200 bg-emerald-50/50' : ($m > 0 ? 'border-amber-200 bg-amber-50/50' : 'border-rose-200 bg-rose-50/50')) }} dark:bg-transparent">
                                <div class="flex gap-2"><span class="font-medium">{{ $loop->iteration }}. {{ $q->text }}</span><span class="ml-auto shrink-0 text-xs text-slate-500">{{ $m ?? '?' }}/{{ $q->points + 0 }}</span></div>
                                <p class="mt-1 text-slate-600 dark:text-slate-300">Your answer: <b>{{ \App\Support\QuizView::answerText($q, $ans) }}</b></p>
                                @unless($right || $q->type === 'essay')<p class="text-emerald-700">Correct: {{ \App\Support\QuizView::correctText($q) }}</p>@endunless
                                @if($q->feedback)<p class="mt-1 text-slate-500">💡 {{ $q->feedback }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.app>
