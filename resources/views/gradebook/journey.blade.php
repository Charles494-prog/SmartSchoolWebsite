<x-layouts.app :title="$student->full_name.' – journey'">
    @php
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $done = $lockedTerms->count();
        $yearAvg = $done ? round($lockedTerms->map(fn ($t) => $avgByTerm[$t])->filter()->avg(), 2) : null;
        $avg = $term ? $avgByTerm[$term] : $yearAvg;
        $honor = \App\Services\Grading::honor($avg);
        $period = $term ? \App\Support\School::periodName($terms).' '.$term : 'Whole year';
        $compDot = ['ww' => 'bg-indigo-500', 'pt' => 'bg-emerald-500', 'qa' => 'bg-amber-500'];
        $compName = ['ww' => 'Quiz', 'pt' => 'Project', 'qa' => 'Exam'];
        $isLocked = fn ($t) => $lockedTerms->contains($t);
    @endphp
    <a href="{{ url()->previous() }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />Back to top achievers</a>

    {{-- Header --}}
    <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center gap-5">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-xl font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</div>
            <div class="mr-auto">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $student->full_name }}</h1>
                <p class="text-slate-500">{{ $e->section->label() }}</p>
            </div>
            <div class="text-right">
                <div class="text-4xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $avg ?? '—' }}</div>
                <div class="text-sm text-slate-500">{{ $term ? $period.' average' : ($done < $terms ? 'Year so far · '.$done.' of '.$terms.' periods' : 'Year average') }}</div>
                @if($honor)<div class="mt-1 text-sm font-semibold text-amber-700">🏅 {{ $honor }}</div>@endif
            </div>
        </div>
        <div class="mt-5 inline-flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
            @foreach(range(1, $terms) as $t)
                <a href="?term={{ $t }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $term === $t ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300' }}">{{ \App\Support\School::term($t, $terms) }}</a>
            @endforeach
            <a href="?term=final" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $term === null ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300' }}">Whole year</a>
        </div>
    </div>

    {{-- Periods --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-{{ $terms + 1 }}">
        @foreach($avgByTerm as $t => $g)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between text-xs text-slate-500"><span>{{ \App\Support\School::periodName($terms) }} {{ $t }}</span>
                    <span class="{{ $isLocked($t) ? 'text-emerald-600' : 'text-slate-400' }}">{{ $isLocked($t) ? 'Final' : ($g !== null ? 'In progress' : 'Not started') }}</span></div>
                <div class="mt-1 text-2xl font-bold tabular-nums {{ $g === null ? 'text-slate-300 dark:text-slate-600' : 'text-slate-900 dark:text-white' }}">{{ $g ?? '—' }}</div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full {{ $isLocked($t) ? 'bg-slate-800 dark:bg-slate-200' : 'bg-slate-300' }}" style="width: {{ $g ? max(0, min(100, ($g - 60) * 2.5)) : 0 }}%"></div></div>
            </div>
        @endforeach
        <div class="rounded-2xl border border-slate-900 bg-slate-900 p-4 text-white dark:border-white dark:bg-white dark:text-slate-900">
            <div class="text-xs opacity-70">Year so far</div>
            <div class="mt-1 text-2xl font-bold tabular-nums">{{ $yearAvg ?? '—' }}</div>
            <div class="mt-2 text-xs opacity-70">average of finished periods</div>
        </div>
    </div>

    {{-- Every graded item, per subject --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @foreach($subjects as $s)
            @php
                $subLocked = array_intersect_key($s['terms'], array_flip($s['cs']->submissions()->whereIn('status', ['submitted', 'approved'])->pluck('term')->map(fn ($t) => (int) $t)->all()));
                $g = $term ? ($s['terms'][$term] ?? null) : \App\Services\Grading::runningFinal($subLocked);
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" x-data="{ open: {{ $loop->index < 2 ? 'true' : 'false' }} }">
                <button type="button" class="flex w-full items-center gap-3 p-5 text-left" x-on:click="open = !open" :aria-expanded="open">
                    <div class="mr-auto"><p class="font-semibold text-slate-900 dark:text-white">{{ $s['cs']->subject->name }}</p><p class="text-xs text-slate-500">{{ $s['cs']->teacher?->name }} · {{ $s['items']->count() }} graded {{ str('item')->plural($s['items']->count()) }}</p></div>
                    <span class="text-2xl font-bold tabular-nums {{ $g === null ? 'text-slate-300' : 'text-slate-900 dark:text-white' }}">{{ $g ?? '—' }}</span>
                    <span class="text-slate-400 transition" :class="open && 'rotate-90'"><x-icon name="chevron" class="h-4 w-4" /></span>
                </button>
                <div x-show="open" x-cloak class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    @forelse($s['items'] as $it)
                        @php $pct = $it['score'] !== null && $it['a']->max_score > 0 ? $it['score'] / $it['a']->max_score * 100 : null; @endphp
                        <div class="flex items-center gap-3 py-1.5 text-sm">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $compDot[$it['a']->component] ?? 'bg-slate-400' }}"></span>
                            <span class="min-w-0 flex-1 truncate text-slate-700 dark:text-slate-200">{{ $it['a']->title }} <span class="text-xs text-slate-400">{{ $compName[$it['a']->component] ?? '' }}@unless($term) · {{ \App\Support\School::term($it['a']->term, $terms) }}@endunless</span></span>
                            <span class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full bg-slate-700 dark:bg-slate-300" style="width: {{ $pct ?? 0 }}%"></span></span>
                            <span class="w-16 text-right tabular-nums font-medium">{{ $it['score'] !== null ? $fmt($it['score']) : '—' }}<span class="font-normal text-slate-400">/{{ $fmt($it['a']->max_score) }}</span></span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-slate-500">No graded items in this period.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500"><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Quizzes / written work</span><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Projects / performance</span><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Exams</span><span>Year so far counts only finished (submitted) periods.</span></p>
</x-layouts.app>
