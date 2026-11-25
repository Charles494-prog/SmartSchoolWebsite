<x-layouts.app title="School calendar">
    @php
        $style = [
            'holiday' => ['bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200', 'bg-rose-500'],
            'exam' => ['bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200', 'bg-amber-500'],
            'meeting' => ['bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200', 'bg-sky-500'],
            'event' => ['bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200', 'bg-emerald-500'],
        ];
        $on = fn ($d) => $events->filter(fn ($e) => $d->between($e->starts_on->copy()->startOfDay(), ($e->ends_on ?? $e->starts_on)->copy()->endOfDay()));
        $upcoming = \App\Models\Event::whereDate('starts_on', '>=', today())->orderBy('starts_on')->limit(8)->get();
    @endphp

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <div class="mr-auto">
            <h1 class="h1">{{ $month->format('F') }} <span class="font-normal text-slate-400">{{ $month->format('Y') }}</span></h1>
            <p class="muted">Holidays, exams, meetings and school events</p>
        </div>
        <div class="flex items-center rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900">
            <a href="?month={{ $month->copy()->subMonth()->format('Y-m') }}" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-white/10" aria-label="Previous month"><x-icon name="chevron-left" class="h-4 w-4" /></a>
            <a href="?month={{ now()->format('Y-m') }}" class="rounded-lg px-3 py-1.5 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-white/10">Today</a>
            <a href="?month={{ $month->copy()->addMonth()->format('Y-m') }}" class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-white/10" aria-label="Next month"><x-icon name="chevron" class="h-4 w-4" /></a>
        </div>
        @if(auth()->user()->hasRole('admin', 'registrar'))<a href="{{ route('crud.create', 'events') }}" class="btn btn-primary">+ Add event</a>@endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        {{-- Month --}}
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-7 border-b border-slate-200 dark:border-slate-800">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $d)
                    <div class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider {{ in_array($i, [0, 6]) ? 'text-slate-400' : 'text-slate-500' }}">{{ $d }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @for($d = $start->copy(); $d <= $end; $d->addDay())
                    @php $out = $d->month !== $month->month; $list = $on($d); @endphp
                    <div class="min-h-28 border-r border-b border-slate-100 p-2 dark:border-slate-800 {{ $out ? 'bg-slate-50/70 dark:bg-white/[.02]' : ($d->isWeekend() ? 'bg-slate-50/40 dark:bg-white/[.01]' : '') }}">
                        <div class="mb-1 flex">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold {{ $d->isToday() ? 'bg-brand-600 text-white' : ($out ? 'text-slate-300 dark:text-slate-600' : 'text-slate-700 dark:text-slate-200') }}">{{ $d->day }}</span>
                        </div>
                        <div class="space-y-1">
                            @foreach($list->take(3) as $e)
                                <div class="truncate rounded-md px-2 py-1 text-xs font-medium {{ $style[$e->type][0] ?? $style['event'][0] }} {{ $out ? 'opacity-50' : '' }}" title="{{ $e->title }}">{{ $e->title }}</div>
                            @endforeach
                            @if($list->count() > 3)<div class="px-2 text-xs text-slate-500">+{{ $list->count() - 3 }} more</div>@endif
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Side: upcoming + legend (also the phone view) --}}
        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 font-semibold">Coming up</h2>
                <ul class="space-y-3">
                    @forelse($upcoming as $e)
                        <li class="flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-slate-100 dark:bg-white/5">
                                <span class="text-[10px] font-bold uppercase text-slate-500">{{ $e->starts_on->format('M') }}</span>
                                <span class="text-lg font-bold leading-none">{{ $e->starts_on->format('d') }}</span>
                            </div>
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $e->title }}</div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <span class="h-2 w-2 rounded-full {{ $style[$e->type][1] ?? 'bg-emerald-500' }}"></span>
                                    {{ \App\Models\Event::TYPES[$e->type] ?? $e->type }} · {{ $e->starts_on->isToday() ? 'today' : $e->starts_on->diffForHumans(['parts' => 1]) }}{{ $e->ends_on ? ' · until '.$e->ends_on->format('M d') : '' }}
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Nothing scheduled.</li>
                    @endforelse
                </ul>
            </div>
            <div class="flex flex-wrap gap-3 px-1 text-xs text-slate-500">
                @foreach($style as $type => [, $dot])<span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>{{ \App\Models\Event::TYPES[$type] ?? ucfirst($type) }}</span>@endforeach
            </div>
        </aside>
    </div>
</x-layouts.app>
