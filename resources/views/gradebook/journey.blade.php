<x-layouts.app :title="$student->full_name.' – journey'">
    @php
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $period = $term ? \App\Support\School::periodName($terms).' '.$term : 'Whole year';
        $done = $avgByTerm->filter()->count();
        $avg = $term ? $avgByTerm[$term] : ($done ? round($avgByTerm->filter()->avg(), 2) : null);
        $honor = \App\Services\Grading::honor($avg);
        $compColor = ['ww' => 'bg-indigo-500', 'pt' => 'bg-emerald-500', 'qa' => 'bg-amber-500'];
        $compName = ['ww' => 'Quiz', 'pt' => 'Project', 'qa' => 'Exam'];
    @endphp
    <a href="{{ url()->previous() }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />Back to top achievers</a>

    {{-- Hero --}}
    <div class="mt-3 overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-6 text-white shadow-lg sm:p-8">
        <div class="flex flex-wrap items-center gap-6">
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-white/20 text-2xl font-bold ring-4 ring-white/30">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</div>
            <div class="mr-auto">
                <p class="text-sm text-white/75">Journey to the top · {{ $period }}</p>
                <h1 class="text-3xl font-bold">{{ $student->full_name }}</h1>
                <p class="text-white/85">{{ $e->section->label() }}</p>
            </div>
            <div class="text-right">
                <div class="text-5xl font-extrabold">{{ $avg ?? '—' }}</div>
                <div class="text-sm text-white/80">{{ ! $term && $done < $terms ? 'year average so far ('.$done.' of '.$terms.')' : 'average' }}</div>
                @if($honor)<span class="mt-1 inline-block rounded-full bg-white/20 px-3 py-1 text-sm font-semibold">🏅 {{ $honor }}</span>@endif
            </div>
        </div>
        <form class="mt-5 flex flex-wrap gap-2" x-data x-ref="f">
            @foreach(range(1, $terms) as $t)
                <a href="?term={{ $t }}" class="rounded-full px-3 py-1 text-sm font-semibold {{ $term === $t ? 'bg-white text-indigo-700' : 'bg-white/15 hover:bg-white/25' }}">{{ \App\Support\School::term($t, $terms) }} @if($avgByTerm[$t])<span class="opacity-70">· {{ $avgByTerm[$t] }}</span>@endif</a>
            @endforeach
            <a href="?term=final" class="rounded-full px-3 py-1 text-sm font-semibold {{ $term === null ? 'bg-white text-indigo-700' : 'bg-white/15 hover:bg-white/25' }}">Whole year</a>
        </form>
    </div>

    {{-- Trend across periods --}}
    <div class="card mt-5">
        <h2 class="h2 mb-4">Average each grading period</h2>
        <div class="flex h-40 items-end gap-4">
            @foreach($avgByTerm as $t => $g)
                <div class="flex flex-1 flex-col items-center gap-2">
                    <span class="text-sm font-bold">{{ $g ?? '—' }}</span>
                    <div class="w-full max-w-20 rounded-t-xl {{ $g ? ($g >= 90 ? 'bg-amber-400' : 'bg-brand-500') : 'bg-slate-100 dark:bg-slate-800' }}" style="height: {{ $g ? max(8, ($g - 60) * 3) : 8 }}px"></div>
                    <span class="text-xs text-slate-500">{{ \App\Support\School::term($t, $terms) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Every graded item, per subject --}}
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach($subjects as $s)
            @php $g = $term ? ($s['terms'][$term] ?? null) : \App\Services\Grading::runningFinal($s['terms']); @endphp
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" x-data="{ open: {{ $loop->index < 2 ? 'true' : 'false' }} }">
                <button type="button" class="flex w-full items-center gap-3 p-5 text-left" x-on:click="open = !open">
                    <div class="mr-auto"><p class="font-semibold">{{ $s['cs']->subject->name }}</p><p class="text-xs text-slate-500">{{ $s['cs']->teacher?->name }} · {{ $s['items']->count() }} graded {{ str('item')->plural($s['items']->count()) }}</p></div>
                    <span class="rounded-xl px-3 py-1 text-xl font-bold {{ $g === null ? 'bg-slate-100 text-slate-400' : ($g >= 90 ? 'bg-amber-100 text-amber-800' : 'bg-brand-50 text-brand-700') }}">{{ $g ?? '—' }}</span>
                </button>
                <div x-show="open" x-cloak class="space-y-2 border-t border-slate-100 p-5 dark:border-slate-800">
                    @forelse($s['items'] as $it)
                        @php $pct = $it['score'] !== null && $it['a']->max_score > 0 ? $it['score'] / $it['a']->max_score * 100 : null; @endphp
                        <div class="flex items-center gap-3 text-sm">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $compColor[$it['a']->component] ?? 'bg-slate-400' }}" title="{{ $compName[$it['a']->component] ?? '' }}"></span>
                            <span class="min-w-0 flex-1 truncate">{{ $it['a']->title }} <span class="text-xs text-slate-400">{{ $compName[$it['a']->component] ?? '' }}@unless($term) · {{ \App\Support\School::term($it['a']->term, $terms) }}@endunless</span></span>
                            <span class="h-2 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full {{ $pct === null ? '' : ($pct >= 90 ? 'bg-amber-400' : ($pct >= 75 ? 'bg-emerald-500' : 'bg-rose-400')) }}" style="width: {{ $pct ?? 0 }}%"></span></span>
                            <span class="w-16 text-right font-semibold">{{ $it['score'] !== null ? $fmt($it['score']) : '—' }}<span class="font-normal text-slate-400">/{{ $fmt($it['a']->max_score) }}</span></span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No graded items in this period.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 flex flex-wrap gap-4 text-xs text-slate-500"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>Quizzes / written work</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Projects / performance tasks</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Exams</span></p>
</x-layouts.app>
